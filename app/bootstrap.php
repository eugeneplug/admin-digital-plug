<?php
declare(strict_types=1);

require __DIR__ . '/guard.php';

date_default_timezone_set('UTC');
ini_set('display_errors', '0');
umask(0077);

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/mysql.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/content.php';
require_once __DIR__ . '/view.php';
require_once __DIR__ . '/theme.php';
require_once __DIR__ . '/fields.php';
require_once __DIR__ . '/media.php';

final class SetupException extends RuntimeException {}
final class ValidationException extends RuntimeException {}

function isLocalDevelopment(): bool
{
    return PHP_SAPI === 'cli-server'
        && in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)
        && preg_match('/\A(?:127\.0\.0\.1|localhost|\[::1\])(?::[0-9]+)?\z/i', $_SERVER['HTTP_HOST'] ?? '') === 1
        && !isset($_SERVER['HTTP_X_FORWARDED_FOR'])
        && !isset($_SERVER['HTTP_X_FORWARDED_HOST'])
        && !isset($_SERVER['HTTP_X_FORWARDED_PROTO'])
        && !isset($_SERVER['HTTP_FORWARDED']);
}

function isHttps(): bool
{
    return in_array(strtolower($_SERVER['HTTPS'] ?? ''), ['on', '1'], true);
}

function securityHeaders(bool $admin = false): void
{
    header_remove('X-Powered-By');
    header('Content-Type: text/html; charset=UTF-8');
    header("Content-Security-Policy: default-src 'none'; style-src 'self'; script-src 'self'; connect-src 'self'; img-src 'self' https: http:; font-src 'self'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'");
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    if ($admin) {
        header('Cache-Control: no-store');
    }
}

function post(string $key): string
{
    return is_string($_POST[$key] ?? null) ? $_POST[$key] : '';
}

function query(string $key): string
{
    return is_string($_GET[$key] ?? null) ? $_GET[$key] : '';
}

function redirect(string $location, int $status = 303): never
{
    header('Location: ' . $location, true, $status);
    exit;
}

function problem(int $status, string $title, string $message, string $root, string $reason = ''): never
{
    http_response_code($status);
    require __DIR__ . '/views/problem.php';
    exit;
}

function publicErrorReason(Throwable $error): string
{
    if ($error instanceof PDOException) {
        $code = (int) ($error->errorInfo[1] ?? 0);
        $reason = match ($code) {
            1044, 1045, 1142, 1143 => 'Операция запрещена для пользователя базы данных.',
            1049 => 'Указанная база данных не найдена.',
            1054, 1146 => 'В базе не найдена нужная таблица или колонка.',
            1062 => 'В базе уже есть запись с таким ключом.',
            1205, 1213, 3058 => 'База занята другой операцией.',
            2002, 2003, 2006, 2013 => 'Связь с сервером базы данных недоступна.',
            3057 => 'Сервер БД отклонил имя блокировки.',
            default => 'База данных не выполнила запрос.',
        };
        return $reason . ($code ? ' Код БД: ' . $code . '.' : '');
    }
    return match (true) {
        $error instanceof ParseError => 'Ошибка синтаксиса PHP.',
        $error instanceof TypeError => 'PHP получил значение неподходящего типа.',
        $error instanceof JsonException => 'Не удалось прочитать данные в формате JSON.',
        default => 'Внутренняя ошибка при выполнении PHP.',
    };
}

function openSite(string $root): PDO
{
    try {
        $db = siteDatabase();
        if ($db === null) {
            redirect($root . '?setup=1');
        }
        try {
            migrateFields($db);
        } catch (PDOException $error) {
            error_log($error::class . ': ' . $error->getMessage());
            problem(500, 'Что-то пошло не так', 'Попробуйте обновить страницу чуть позже.', $root,
                'Не удалось обновить схему БД. ' . publicErrorReason($error));
        }
        return $db;
    } catch (SetupException $error) {
        problem(503, 'Сайт ещё не настроен', 'Проверьте настройки и обновите страницу.', $root, $error->getMessage());
    }
}

set_exception_handler(static function (Throwable $error): void {
    error_log($error::class . ': ' . $error->getMessage());
    problem(500, 'Что-то пошло не так', 'Попробуйте обновить страницу чуть позже.', $GLOBALS['root'] ?? './', publicErrorReason($error));
});
