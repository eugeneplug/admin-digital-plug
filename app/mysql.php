<?php
declare(strict_types=1);

function usesMysql(PDO $db): bool
{
    return $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
}

function mysqlFormSettings(): array
{
    $settings = [
        'host' => trim(post('db_host')),
        'port' => trim(post('db_port')),
        'name' => trim(post('db_name')),
        'user' => trim(post('db_user')),
        'password' => post('db_password'),
    ];
    if (filter_var($settings['host'], FILTER_VALIDATE_IP) === false
        && preg_match('/\A[a-z0-9][a-z0-9.-]{0,252}\z/i', $settings['host']) !== 1) {
        throw new ValidationException('Укажите хост базы данных, например localhost. Порт укажите в отдельном поле.');
    }
    $port = filter_var($settings['port'] === '' ? '3306' : $settings['port'], FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => 65535],
    ]);
    if ($port === false) {
        throw new ValidationException('Порт базы данных должен быть числом от 1 до 65535.');
    }
    $settings['port'] = $port;

    if (preg_match('/\A[a-z0-9_$-]{1,64}\z/i', $settings['name']) !== 1) {
        throw new ValidationException('Проверьте название базы в панели хостинга.');
    }
    if ($settings['user'] === '' || strlen($settings['user']) > 128 || str_contains($settings['user'], "\0")) {
        throw new ValidationException('Проверьте имя пользователя базы данных.');
    }
    if (strlen($settings['password']) > 4096 || str_contains($settings['password'], "\0")
        || preg_match('//u', $settings['user'] . $settings['password']) !== 1) {
        throw new ValidationException('Проверьте логин и пароль базы данных.');
    }
    return $settings;
}

function connectMysql(array $settings): PDO
{
    if (!extension_loaded('pdo_mysql')) {
        throw new SetupException('Включите расширение pdo_mysql в настройках PHP на хостинге.');
    }

    $host = $settings['host'];
    if (str_contains($host, ':')) {
        $host = '[' . $host . ']';
    } elseif (strtolower($host) === 'localhost' && $settings['port'] !== 3306) {
        $host = '127.0.0.1';
    }
    $dsn = 'mysql:host=' . $host . ';port=' . $settings['port'] . ';dbname=' . $settings['name'] . ';charset=utf8mb4';
    try {
        return new PDO($dsn, $settings['user'], $settings['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 5,
        ]);
    } catch (PDOException $error) {
        $message = match ((int) ($error->errorInfo[1] ?? 0)) {
            1044, 1045 => 'MySQL отказал в доступе. Проверьте логин, пароль и доступ пользователя к базе.',
            1049 => 'База с таким названием не найдена. Создайте её в панели хостинга и проверьте название.',
            default => 'Не удалось подключиться к MySQL. Проверьте хост, порт и доступность сервера базы данных.',
        };
        throw new SetupException($message);
    }
}

function storedMysqlSettings(string $filename = 'database.json'): ?array
{
    $file = dataDirectory() . '/' . $filename;
    if (is_link($file)) {
        throw new SetupException('Файл подключения к базе должен находиться в закрытой папке данных.');
    }
    if (!file_exists($file)) {
        return null;
    }

    $contents = is_file($file) ? @file_get_contents($file) : false;
    $settings = $contents === false ? null : json_decode($contents, true);
    if (!is_array($settings) || !is_string($settings['host'] ?? null) || !is_int($settings['port'] ?? null)
        || !is_string($settings['name'] ?? null) || !is_string($settings['user'] ?? null) || !is_string($settings['password'] ?? null)) {
        throw new SetupException('Не удалось прочитать сохранённые параметры подключения к базе.');
    }
    return $settings;
}

function mysqlSchemaVersion(PDO $db): int
{
    try {
        return (int) $db->query('SELECT version FROM plugy_installation WHERE id = 1')->fetchColumn();
    } catch (PDOException $error) {
        if ((int) ($error->errorInfo[1] ?? 0) === 1146) {
            return 0;
        }
        throw $error;
    }
}

function withMysqlLock(PDO $db, string $purpose, callable $work): mixed
{
    $schema = (string) $db->query('SELECT DATABASE()')->fetchColumn();
    $name = 'plugy:' . $purpose . ':' . substr(hash('sha256', strtolower($schema)), 0, 48);
    $lock = $db->prepare('SELECT GET_LOCK(?, 8)');
    $lock->execute([$name]);
    $acquired = (int) $lock->fetchColumn() === 1;
    $lock->closeCursor();
    if (!$acquired) {
        throw new SetupException('База занята другой операцией. Попробуйте ещё раз.');
    }
    try {
        return $work();
    } finally {
        $db->prepare('DO RELEASE_LOCK(?)')->execute([$name]);
    }
}

function checkMysqlInstallTarget(PDO $db, bool $resuming): bool
{
    if (isInstalled($db)) {
        if (!$resuming) {
            throw new SetupException('В этой базе уже установлен Plugy Engine. Выберите отдельную пустую базу.');
        }
        return true;
    }

    $tables = $db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    if ($tables && (!$resuming || !in_array('plugy_installation', $tables, true))) {
        throw new SetupException('Выбранная база уже содержит таблицы. Укажите пустую базу для Plugy Engine.');
    }
    return false;
}

function installMysql(array $settings, string $adminPassword): bool
{
    $directory = dataDirectory();
    ensurePrivateDirectory($directory);
    $lock = @fopen($directory . '/install.lock', 'c');
    if ($lock === false || !flock($lock, LOCK_EX)) {
        if (is_resource($lock)) {
            fclose($lock);
        }
        throw new SetupException('Не удалось получить доступ к папке установки. Проверьте права PHP.');
    }

    try {
        if (siteDatabase() !== null) {
            return false;
        }

        $db = connectMysql($settings);
        return withMysqlLock($db, 'install', static function () use ($db, $settings, $adminPassword, $directory): bool {
            $pending = $directory . '/database.pending.json';
            $resuming = storedMysqlSettings('database.pending.json') === $settings;

            if (checkMysqlInstallTarget($db, $resuming)) {
                if (!verifyAdminPassword($adminPassword, adminAccount($db)['password_hash'])) {
                    throw new ValidationException('Таблицы уже созданы. Введите пароль администратора из предыдущей попытки, чтобы завершить установку.');
                }
            } else {
                if (!$resuming) {
                    $config = json_encode($settings, JSON_THROW_ON_ERROR) . PHP_EOL;
                    $temporary = $directory . '/.database-' . bin2hex(random_bytes(8));
                    try {
                        if (@file_put_contents($temporary, $config) !== strlen($config) || !@rename($temporary, $pending)) {
                            throw new SetupException('Не удалось сохранить подключение. Проверьте права PHP на папку данных.');
                        }
                    } finally {
                        if (is_file($temporary)) {
                            @unlink($temporary);
                        }
                    }
                }

                $schema = require __DIR__ . '/schema.php';
                foreach ($schema as $statement) {
                    $db->exec($statement);
                }
                writeTransaction($db, static function () use ($db, $adminPassword): void {
                    seedDatabase($db, hashAdminPassword($adminPassword));
                    $db->exec('INSERT INTO plugy_installation (id, version) VALUES (1, 1)');
                });
            }

            if (!@rename($pending, $directory . '/database.json')) {
                throw new SetupException('Таблицы созданы, но подключение не сохранилось. Проверьте права PHP и повторите этот шаг.');
            }
            return true;
        });
    } finally {
        fclose($lock);
    }
}
