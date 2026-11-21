<?php
declare(strict_types=1);

define('PLUGY_ENTRY', true);
$root = './';
require dirname(__DIR__) . '/app/bootstrap.php';
$setup = query('setup') === '1';
securityHeaders(admin: $setup);
if (($_SERVER['PATH_INFO'] ?? '') !== '') {
    $siteRoot = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/';
    problem(404, 'Страница не найдена', 'Проверьте адрес или вернитесь на главную.', $siteRoot);
}
if ($setup) {
    require dirname(__DIR__) . '/app/install.php';
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
$db = openSite($root);
$article = null;

if (array_key_exists('article', $_GET)) {
    $slug = query('article');
    $article = strlen($slug) <= 100 ? publishedArticle($db, $slug) : false;
    if (!$article) {
        problem(404, 'Статья не найдена', 'Проверьте ссылку или выберите статью в журнале.', $root);
    }
} else {
    $blocks = siteBlocks($db, visibleOnly: true);
    $articles = articles($db, publishedOnly: true);
}
ob_start();
require dirname(__DIR__) . '/app/views/site.php';
$content = ob_get_clean();

ob_start();
brand();
$brand = ob_get_clean();

ob_start();
icon('lock');
$lockIcon = ob_get_clean();

echo strtr(readThemeFile('index.html'), [
    '{{title}}' => e($article ? $article['title'] . ' · Plugy Engine' : 'Plugy Engine'),
    '{{base_css}}' => './?theme_asset=base.css',
    '{{theme_css}}' => './?theme_asset=styles.css',
    '{{theme_js}}' => './?theme_asset=script.js',
    '{{brand}}' => $brand,
    '{{lock_icon}}' => $lockIcon,
    '{{content}}' => $content,
]);
