<?php defined('PLUGY_ENTRY') || exit; ?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?></title>
    <link rel="stylesheet" href="./?theme_asset=base.css">
    <link rel="stylesheet" href="./?theme_asset=styles.css">
    <script src="./?theme_asset=script.js" defer></script>
</head>
<body class="site-page">
<header class="site-header">
    <div class="site-container site-nav">
        <a class="brand" href="./"><?php brand(); ?></a>
        <nav aria-label="Навигация сайта">
            <a href="./#journal">Журнал</a>
            <a class="site-admin-link" href="admin/"><?php icon('lock'); ?> Админка</a>
        </nav>
    </div>
</header>
