<?php defined('PLUGY_ENTRY') || exit; ?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?></title>
</head>
<body>
<main>
    <?php // Создайте текстовое поле test_title и заполните его в редакторе страницы. ?>
    <h1><?php field('test_title', default: 'Тест полей'); ?></h1>
</main>
</body>
</html>
