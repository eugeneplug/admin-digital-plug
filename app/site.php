<?php
declare(strict_types=1);

$root = './';
require __DIR__ . '/bootstrap.php';
$setup = query('setup') === '1';
securityHeaders(admin: $setup);
if (($_SERVER['PATH_INFO'] ?? '') !== '') {
    $siteRoot = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/';
    problem(404, 'Страница не найдена', 'Проверьте адрес или вернитесь на главную.', $siteRoot);
}
if ($setup) {
    require __DIR__ . '/install.php';
    exit;
}
if (array_key_exists('theme_asset', $_GET)) {
    $file = query('theme_asset');
    $type = match ($file) {
        'base.css', 'styles.css' => 'text/css',
        'script.js' => 'text/javascript',
        default => null,
    };
    if ($type === null) {
        problem(404, 'Файл не найден', 'Проверьте адрес файла.', $root);
    }
    header('Content-Type: ' . $type . '; charset=UTF-8');
    header('Cache-Control: no-cache');
    $source = readThemeFile($file);
    echo $file === 'base.css' ? str_replace('{{font_url}}', assetUrl('fonts/inter-variable.ttf', $root), $source) : $source;
    exit;
}
if (array_key_exists('media', $_GET)) serveFieldImage(query('media'));
$db = openSite($root);
$showArticle = array_key_exists('article', $_GET);
if (!$showArticle) {
    $slug = query('page') ?: 'home';
    $read = $db->prepare("SELECT * FROM pages WHERE slug = ? AND status = 'published'");
    $read->execute([$slug]); $page = $read->fetch();
    if (!$page) problem(404, 'Страница не найдена', 'Проверьте ссылку.', $root);
    fieldContext('page:' . $page['id']);
    $view = $slug === 'home' ? 'home' : 'page';
    $data = ['page' => $page];
    $title = $page['title'];
}
$level = ob_get_level();
ob_start();
try {
    if ($showArticle || $view === 'home') {
        $content = require themePath('content.php');
        $journal = [];
        foreach ($content['articles'] as [$titleText, $articleSlug, $excerpt, $body, $date]) {
            $journal[$articleSlug] = [
                'title' => $titleText,
                'slug' => $articleSlug,
                'excerpt' => $excerpt,
                'body' => $body,
                'published_at' => strtotime($date),
            ];
        }
        if ($showArticle) {
            $article = $journal[query('article')] ?? null;
            if ($article === null) {
                ob_end_clean();
                problem(404, 'Статья не найдена', 'Проверьте ссылку.', $root);
            }
            $view = 'article';
            $title = $article['title'];
            $data = ['article' => $article];
        } else {
            $data['blocks'] = array_map(static fn(array $block): array => array_combine(
                ['kind', 'eyebrow', 'title', 'body', 'link_label', 'link_url'], $block
            ), $content['blocks']);
            $data['articles'] = array_values($journal);
        }
    }
    template('index', ['view' => $view, 'data' => $data, 'title' => $title . ' · Plugy Engine']);
    ob_end_flush();
} catch (Throwable $error) {
    while (ob_get_level() > $level) ob_end_clean();
    error_log($error::class . ': ' . $error->getMessage());
    problem(500, 'Ошибка шаблона', 'Проверьте PHP-шаблон сайта в редакторе кода админки.', $root, publicErrorReason($error));
}
