<?php
declare(strict_types=1);

function uploadFieldImage(array $file): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_string($file['tmp_name'] ?? null)
        || !is_uploaded_file($file['tmp_name']) || filesize($file['tmp_name']) > 5 * 1024 * 1024) {
        throw new ValidationException('Выберите изображение до 5 МБ.');
    }
    $info = @getimagesize($file['tmp_name']);
    $extension = match ($info[2] ?? 0) { IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp', default => null };
    if ($extension === null || $info[0] > 12000 || $info[1] > 12000) throw new ValidationException('Используйте JPG, PNG, GIF или WebP до 12000 пикселей по стороне.');
    $directory = dataDirectory() . '/media'; ensurePrivateDirectory($directory);
    $name = bin2hex(random_bytes(16)) . '.' . $extension;
    if (!move_uploaded_file($file['tmp_name'], $directory . '/' . $name)) throw new SetupException('Не удалось сохранить изображение.');
    $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'], 2)), '/.');
    return $base . '/?media=' . $name;
}

function serveFieldImage(string $name): never
{
    if (!preg_match('/\A[a-f0-9]{32}\.(jpg|png|gif|webp)\z/', $name, $match)) { http_response_code(404); exit; }
    $path = dataDirectory() . '/media/' . $name;
    if (!is_file($path) || is_link($path) || is_link(dirname($path))) { http_response_code(404); exit; }
    header('Content-Type: image/' . ($match[1] === 'jpg' ? 'jpeg' : $match[1]));
    header('Cache-Control: public, max-age=31536000, immutable');
    header('Content-Length: ' . filesize($path));
    readfile($path); exit;
}
