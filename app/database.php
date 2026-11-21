<?php
declare(strict_types=1);

function installConfig(): array
{
    static $config = null;
    if ($config === null) {
        $file = __DIR__ . '/install.local.php';
        $config = is_file($file) ? require $file : [];
    }
    return $config;
}

function pathIsInside(string $path, string $directory): bool
{
    $path = str_replace('\\', '/', $path);
    $directory = rtrim(str_replace('\\', '/', $directory), '/');
    if (PHP_OS_FAMILY === 'Windows') {
        $path = strtolower($path);
        $directory = strtolower($directory);
    }
    return $path === $directory || str_starts_with($path, $directory . '/');
}

function dataDirectory(): string
{
    static $directory = null;
    if ($directory !== null) {
        return $directory;
    }

    $project = dirname(__DIR__);
    $serverRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
    $webRoot = $serverRoot === '' ? false : realpath($serverRoot);
    if ($webRoot === false) {
        throw new SetupException('В настройках хостинга укажите корневую папку сайта (DOCUMENT_ROOT).');
    }

    // An addon domain can have its own document root inside the primary site's public folder.
    $publicTree = $webRoot;
    for ($ancestor = $webRoot; dirname($ancestor) !== $ancestor; $ancestor = dirname($ancestor)) {
        if (in_array(strtolower(basename($ancestor)), ['public_html', 'httpdocs', 'wwwroot'], true)) {
            $publicTree = $ancestor;
        }
    }

    $configured = installConfig()['data_directory'] ?? '';
    if (!is_string($configured) || ($configured !== '' && !preg_match('~^(?:/|[A-Za-z]:[/\\\\])~', $configured))) {
        throw new SetupException('Укажите абсолютный путь data_directory в файле app/install.local.php.');
    }
    $candidate = $configured !== '' ? $configured : $project . '/var';
    if ($configured === '' && pathIsInside($candidate, $publicTree)) {
        $candidate = dirname($publicTree) . '/.plugy-' . substr(hash('sha256', $project), 0, 12);
    }

    $parent = @realpath(dirname($candidate));
    $name = basename($candidate);
    if ($parent === false || in_array($name, ['', '.', '..'], true)) {
        throw new SetupException('Укажите существующий путь к папке данных вне корневой папки сайта в app/install.local.php.');
    }
    $candidate = $parent . DIRECTORY_SEPARATOR . $name;
    $resolved = @realpath($candidate) ?: $candidate;
    if (pathIsInside($resolved, $publicTree) || is_link($candidate)) {
        throw new SetupException('База и сессии должны находиться в отдельной папке вне корневой папки сайта.');
    }
    return $directory = $resolved;
}

function ensurePrivateDirectory(string $path): void
{
    if (!is_dir($path) && !@mkdir($path, 0700, true) && !is_dir($path)) {
        throw new SetupException('PHP не может создать закрытую папку данных. Проверьте права или задайте data_directory в app/install.local.php.');
    }
    if (is_link($path) || !is_writable($path)) {
        throw new SetupException('Укажите доступную PHP для записи папку данных без символической ссылки.');
    }
}

function siteDatabase(): ?PDO
{
    $settings = storedMysqlSettings();
    if ($settings !== null) {
        $db = connectMysql($settings);
        if (!isInstalled($db)) {
            throw new SetupException('В подключённой базе отсутствуют таблицы Plugy Engine.');
        }
        return $db;
    }

    $database = dataDirectory() . '/plugy.sqlite';
    if (is_link($database)) {
        throw new SetupException('Проверьте права PHP на запись в файл plugy.sqlite в закрытой папке данных.');
    }
    if (!is_file($database) || filesize($database) === 0) {
        return null;
    }
    if (!extension_loaded('pdo_sqlite')) {
        throw new SetupException('Для существующей базы SQLite включите расширение pdo_sqlite в настройках PHP.');
    }
    if (!is_writable($database)) {
        throw new SetupException('Проверьте права PHP на запись в файл plugy.sqlite в закрытой папке данных.');
    }

    $db = new PDO('sqlite:' . $database, options: [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $db->exec('PRAGMA busy_timeout = 5000');
    return isInstalled($db) ? $db : null;
}

function isInstalled(PDO $db): bool
{
    $version = usesMysql($db) ? mysqlSchemaVersion($db) : (int) $db->query('PRAGMA user_version')->fetchColumn();
    if (!in_array($version, [0, 1, 2], true)) {
        throw new SetupException('Версия базы данных несовместима с этой версией Plugy Engine.');
    }
    return $version >= 1;
}

function writeTransaction(PDO $db, callable $write): mixed
{
    if (usesMysql($db)) {
        return withMysqlLock($db, 'write', static function () use ($db, $write): mixed {
            $db->beginTransaction();
            try {
                $result = $write();
                $db->commit();
                return $result;
            } catch (Throwable $error) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                throw $error;
            }
        });
    }

    $db->exec('BEGIN IMMEDIATE');
    try {
        $result = $write();
        $db->exec('COMMIT');
        return $result;
    } catch (Throwable $error) {
        $db->exec('ROLLBACK');
        throw $error;
    }
}

function seedDatabase(PDO $db, string $passwordHash): void
{
    $db->prepare('INSERT INTO admin (id, password_hash, demo) VALUES (1, ?, 0)')->execute([$passwordHash]);
    $seed = require __DIR__ . '/seed.php';
    $now = time();
    $block = $db->prepare('INSERT INTO blocks (kind, eyebrow, title, body, link_label, link_url, position, visible, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)');
    foreach ($seed['blocks'] as $position => $fields) {
        $block->execute([...$fields, $position + 1, $now]);
    }
    $article = $db->prepare('INSERT INTO articles (title, slug, excerpt, body, status, published_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?)');
    foreach ($seed['articles'] as [$title, $slug, $excerpt, $body, $status, $age]) {
        $date = $now - $age * 86400;
        $article->execute([$title, $slug, $excerpt, $body, $status, $status === 'published' ? $date : null, $date]);
    }
}
