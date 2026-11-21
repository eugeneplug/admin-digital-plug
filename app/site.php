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
if (array_key_exists('theme_asset', $_GET) || array_key_exists('article', $_GET)) {
    problem(404, 'Страница не найдена', 'Проверьте ссылку.', $root);
}
if (array_key_exists('media', $_GET)) serveFieldImage(query('media'));
$db = openSite($root);
$slug = query('page') ?: 'home';
$read = $db->prepare("SELECT * FROM pages WHERE slug = ? AND status = 'published'");
$read->execute([$slug]);
$page = $read->fetch();
if (!$page) {
    problem(404, 'Страница не найдена', 'Проверьте ссылку.', $root);
}
fieldContext('page:' . $page['id']);

$level = ob_get_level();
ob_start();
try {
    template('index', ['page' => $page, 'title' => $page['title'] . ' · Plugy Engine']);
    ob_end_flush();
} catch (Throwable $error) {
    while (ob_get_level() > $level) ob_end_clean();
    error_log($error::class . ': ' . $error->getMessage());
    problem(500, 'Ошибка шаблона', 'Проверьте PHP-шаблон сайта в редакторе кода админки.', $root, publicErrorReason($error));
}
