<?php
declare(strict_types=1);

$root = './';
require dirname(__DIR__) . '/app/bootstrap.php';
securityHeaders();
if (($_SERVER['PATH_INFO'] ?? '') !== '') {
    $siteRoot = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/';
    problem(404, 'Страница не найдена', 'Проверьте адрес или вернитесь на главную.', $siteRoot);
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
require dirname(__DIR__) . '/app/views/site.php';
