<?php
declare(strict_types=1);

require __DIR__ . '/guard.php';

$loginUrl = $root . 'admin/?view=login';
$setupUrl = $root . '?setup=1';
try {
    if (siteDatabase() !== null) {
        redirect($loginUrl);
    }
    if (!extension_loaded('pdo_mysql')) {
        throw new SetupException('Включите расширение pdo_mysql в настройках PHP на хостинге.');
    }
    startAdminSession();
} catch (SetupException $error) {
    problem(503, 'Установка пока недоступна', $error->getMessage(), $root);
}

$pending = $_SESSION['mysql_setup'] ?? null;
if ($pending !== null && $pending['expires'] < time()) {
    unset($_SESSION['mysql_setup']);
    $pending = null;
}
$step = $pending !== null && query('step') !== 'database' ? 'admin' : 'database';
$settings = $pending['settings'] ?? ['host' => 'localhost', 'port' => 3306, 'name' => '', 'user' => ''];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals(csrfToken(), post('csrf'))) {
        problem(403, 'Форма устарела', 'Откройте страницу установки заново.', $root);
    }

    try {
        if (post('step') === 'database') {
            $step = 'database';
            unset($_SESSION['mysql_setup']);
            $settings = ['host' => post('db_host'), 'port' => post('db_port'), 'name' => post('db_name'), 'user' => post('db_user')];
            $settings = mysqlFormSettings();
            checkMysqlInstallTarget(connectMysql($settings), storedMysqlSettings('database.pending.json') === $settings);
            $_SESSION['mysql_setup'] = ['settings' => $settings, 'expires' => time() + 900];
            redirect($setupUrl);
        }

        if (post('step') !== 'admin' || $pending === null) {
            $step = 'database';
            throw new ValidationException('Данные подключения устарели. Введите их ещё раз.');
        }
        $password = post('password');
        if (!validNewPassword($password)) {
            throw new ValidationException('Пароль должен содержать от 15 символов и занимать до 72 байт.');
        }
        if (!hash_equals($password, post('repeat_password'))) {
            throw new ValidationException('Пароли не совпадают.');
        }
        if (installMysql($pending['settings'], $password)) {
            $_SESSION['installation_complete'] = true;
        }
        unset($_SESSION['mysql_setup']);
        redirect($loginUrl);
    } catch (ValidationException | SetupException $failure) {
        $error = $failure->getMessage();
    } catch (PDOException) {
        $error = 'Не удалось создать таблицы. Проверьте права пользователя базы на создание таблиц и запись.';
    }
    http_response_code(422);
}

require __DIR__ . '/views/install.php';
