<?php
declare(strict_types=1);

const THEME_FILES = [
    'index.php' => ['PHP', 'Общий каркас', 'application/x-httpd-php'],
    'header.php' => ['PHP', 'Шапка сайта', 'application/x-httpd-php'],
    'footer.php' => ['PHP', 'Подвал сайта', 'application/x-httpd-php'],
    'home.php' => ['PHP', 'Главная и журнал', 'application/x-httpd-php'],
    'page.php' => ['PHP', 'Страница', 'application/x-httpd-php'],
    'article.php' => ['PHP', 'Статья', 'application/x-httpd-php'],
    'components/block.php' => ['PHP', 'Блок главной', 'application/x-httpd-php'],
    'components/article-card.php' => ['PHP', 'Карточка статьи', 'application/x-httpd-php'],
    'components/benefits.php' => ['PHP', 'Повторитель преимуществ', 'application/x-httpd-php'],
    'base.css' => ['CSS', 'Базовые стили и шрифт', 'css'],
    'styles.css' => ['CSS', 'Стили сайта', 'css'],
    'script.js' => ['JS', 'Скрипты сайта', 'javascript'],
];

function themeFiles(): array
{
    $files = THEME_FILES;
    foreach ([dirname(__DIR__) . '/site/components', dataDirectory() . '/theme/components'] as $directory) {
        if (is_link($directory)) continue;
        foreach (glob($directory . '/*.php') ?: [] as $path) {
            $name = basename($path);
            if (!is_link($path) && preg_match('/\A[a-z][a-z0-9-]{0,49}\.php\z/', $name)) {
                $files['components/' . $name] ??= ['PHP', 'Компонент', 'application/x-httpd-php'];
            }
        }
    }
    return $files;
}

function createComponent(string $name): string
{
    if (!preg_match('/\A[a-z][a-z0-9-]{0,49}\z/', $name)) throw new ValidationException('В имени компонента используйте латинские буквы, цифры и дефисы.');
    $file = 'components/' . $name . '.php';
    if (isset(themeFiles()[$file])) throw new ValidationException('Компонент с таким именем уже существует.');
    $directory = dataDirectory() . '/theme'; ensurePrivateDirectory($directory); ensurePrivateDirectory($directory . '/components');
    $handle = @fopen($directory . '/' . $file, 'x');
    if ($handle === false) throw new SetupException('Не удалось создать компонент.');
    $source = "<?php defined('PLUGY_ENTRY') || exit; ?>\n<section>\n    <!-- Содержимое компонента -->\n</section>\n";
    try { if (fwrite($handle, $source) !== strlen($source)) throw new SetupException('Не удалось записать компонент.'); }
    finally { fclose($handle); }
    return $file;
}

function themeEditorUrl(string $file): string
{
    return adminUrl('code') . '&file=' . rawurlencode($file);
}

function readThemeFile(string $file): string
{
    $source = @file_get_contents(themePath($file));
    if ($source === false) throw new RuntimeException('Unable to read theme file.');
    return $source;
}

function themePath(string $file): string
{
    if (!isset(themeFiles()[$file])) {
        throw new LogicException('Unknown theme file.');
    }
    $directory = dataDirectory() . '/theme';
    $path = $directory . '/' . $file;
    if (is_link($directory) || is_link($path)) {
        throw new RuntimeException('Invalid theme file location.');
    }
    if (is_link(dirname($path))) throw new RuntimeException('Invalid theme directory.');
    return is_file($path) ? $path : dirname(__DIR__) . '/site/' . $file;
}

function saveThemeFile(string $file, string $source, string $revision): void
{
    if (strlen($source) > 262144 || str_contains($source, "\0") || preg_match('//u', $source) !== 1) {
        throw new ValidationException('Файл должен быть текстом UTF-8 размером до 256 КБ.');
    }
    if (!isset(themeFiles()[$file])) throw new ValidationException('Файл не найден.');
    if (str_ends_with($file, '.php')) {
        try { token_get_all($source, TOKEN_PARSE); }
        catch (ParseError $error) { throw new ValidationException('Ошибка PHP, строка ' . $error->getLine() . ': ' . $error->getMessage()); }
    }

    $directory = dataDirectory() . '/theme';
    ensurePrivateDirectory($directory);
    ensurePrivateDirectory(dirname($directory . '/' . $file));
    $lock = @fopen($directory . '/.editor.lock', 'c');
    if ($lock === false) {
        throw new SetupException('Не удалось открыть папку темы для записи.');
    }

    $temporary = $directory . '/.edit-' . bin2hex(random_bytes(8));
    try {
        if (!flock($lock, LOCK_EX)) {
            throw new SetupException('Файл занят. Попробуйте сохранить его ещё раз.');
        }
        if (!hash_equals(hash('sha256', readThemeFile($file)), $revision)) {
            throw new ValidationException('Файл уже изменился. Скопируйте свои правки и откройте свежую версию перед сохранением.');
        }
        $source = str_replace(["\r\n", "\r"], "\n", $source);
        if (@file_put_contents($temporary, $source) !== strlen($source) || !@rename($temporary, $directory . '/' . $file)) {
            throw new SetupException('Файл не сохранился. Проверьте права PHP на запись в папку темы.');
        }
        if (function_exists('opcache_invalidate')) @opcache_invalidate($directory . '/' . $file, true);
    } finally {
        if (is_file($temporary)) {
            @unlink($temporary);
        }
        fclose($lock);
    }
}

function template(string $name, array $variables = []): void
{
    $file = $name . '.php';
    $path = themePath($file);
    (static function (string $__path, array $__variables): void {
        extract($__variables, EXTR_SKIP);
        require $__path;
    })($path, $variables);
}

function component(string $name, array $variables = []): void { template('components/' . $name, $variables); }
