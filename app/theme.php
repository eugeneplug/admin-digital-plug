<?php
declare(strict_types=1);

const THEME_FILES = [
    'home.html' => ['HTML', 'Главная и журнал', 'htmlmixed'],
    'block.html' => ['HTML', 'Блок главной', 'htmlmixed'],
    'article.html' => ['HTML', 'Страница статьи', 'htmlmixed'],
    'article-card.html' => ['HTML', 'Карточка в журнале', 'htmlmixed'],
    'index.html' => ['HTML', 'Шапка и подвал', 'htmlmixed'],
    'styles.css' => ['CSS', 'Стили сайта', 'css'],
    'script.js' => ['JS', 'Скрипты сайта', 'javascript'],
];

function themeEditorUrl(string $file): string
{
    return adminUrl('code') . '&file=' . rawurlencode($file);
}

function readThemeFile(string $file): string
{
    if (!isset(THEME_FILES[$file])) {
        throw new LogicException('Unknown theme file.');
    }
    $directory = dataDirectory() . '/theme';
    $path = $directory . '/' . $file;
    if (is_link($directory) || is_link($path)) {
        throw new RuntimeException('Invalid theme file location.');
    }
    $source = @file_get_contents(is_file($path) ? $path : __DIR__ . '/theme/' . $file);
    if ($source === false) {
        throw new RuntimeException('Unable to read theme file.');
    }
    return $source;
}

function saveThemeFile(string $file, string $source, string $revision): void
{
    if (strlen($source) > 262144 || str_contains($source, "\0") || preg_match('//u', $source) !== 1) {
        throw new ValidationException('Файл должен быть текстом UTF-8 размером до 256 КБ.');
    }
    if ($file === 'index.html' && !str_contains($source, '{{content}}')) {
        throw new ValidationException('Оставьте {{content}} в шаблоне. На его месте Plugy выводит блоки и статьи.');
    }

    $directory = dataDirectory() . '/theme';
    ensurePrivateDirectory($directory);
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
    } finally {
        if (is_file($temporary)) {
            @unlink($temporary);
        }
        fclose($lock);
    }
}
