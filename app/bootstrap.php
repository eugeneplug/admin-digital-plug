<?php
declare(strict_types=1);

date_default_timezone_set('UTC');
ini_set('display_errors', '0');
umask(0077);

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/content.php';
require_once __DIR__ . '/view.php';

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
    header("Content-Security-Policy: default-src 'none'; style-src 'self'; script-src 'self'; img-src 'self'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'");
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    if (isHttps()) {
        header('Strict-Transport-Security: max-age=31536000');
    }
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

function problem(int $status, string $title, string $message, string $root): never
{
    http_response_code($status);
    require __DIR__ . '/views/problem.php';
    exit;
}

function openSite(string $root): PDO
{
    try {
        return openDatabase();
    } catch (SetupException $error) {
        problem(503, 'Сайт ещё не настроен', $error->getMessage(), $root);
    }
}

set_exception_handler(static function (Throwable $error): void {
    error_log($error::class . ': ' . $error->getMessage());
    problem(500, 'Что-то пошло не так', 'Попробуйте обновить страницу чуть позже.', $GLOBALS['root'] ?? './');
});
