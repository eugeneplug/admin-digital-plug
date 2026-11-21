<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli-server') {
    http_response_code(404);
    exit;
}

$project = dirname(__DIR__);
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$entry = match ($path) {
    '/', '/index.php' => '/index.php',
    '/admin', '/admin/', '/admin/index.php' => '/admin/index.php',
    default => null,
};

if ($entry !== null) {
    $_SERVER['SCRIPT_NAME'] = $entry;
    unset($_SERVER['PATH_INFO']);
    require $project . $entry;
    return;
}

$assetPrefix = '/admin/assets/';
if (is_string($path) && str_starts_with($path, $assetPrefix)
    && in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD'], true)) {
    $name = rawurldecode(substr($path, strlen($assetPrefix)));
    if (preg_match('~\A(?:[a-z0-9_-]+/)*[a-z0-9_.-]+\z~i', $name) === 1) {
        $directory = realpath($project . $assetPrefix);
        $file = $directory === false ? false : realpath($directory . DIRECTORY_SEPARATOR . $name);
        $type = match (pathinfo($name, PATHINFO_EXTENSION)) {
            'css' => 'text/css; charset=UTF-8',
            'js' => 'text/javascript; charset=UTF-8',
            'ttf' => 'font/ttf',
            'txt' => 'text/plain; charset=UTF-8',
            default => null,
        };
        if ($type !== null && $file !== false && is_file($file)
            && str_starts_with($file, $directory . DIRECTORY_SEPARATOR)) {
            header('Content-Type: ' . $type);
            header('X-Content-Type-Options: nosniff');
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                readfile($file);
            }
            return;
        }
    }
}

http_response_code(404);
header('Content-Type: text/plain; charset=UTF-8');
echo 'Страница не найдена.';
