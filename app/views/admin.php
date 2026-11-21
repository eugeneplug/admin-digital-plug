<?php
require dirname(__DIR__) . '/guard.php';

$nav = [
    'dashboard' => ['grid', 'Обзор'],
    'pages' => ['file', 'Страницы'],
    'fields' => ['layers', 'Custom Fields'],
    'code' => ['code', 'Редактор кода'],
    'settings' => ['settings', 'Настройки'],
];
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?= e($title) ?> · Plugy Engine</title>
    <link rel="stylesheet" href="<?= e(assetUrl('style.css', $root, versioned: true)) ?>">
    <script src="<?= e(assetUrl('app.js', $root)) ?>" defer></script>
    <script src="<?= e(assetUrl('fields.js', $root, versioned: true)) ?>" defer></script>
    <link rel="stylesheet" href="<?= e(assetUrl('fields.css', $root, versioned: true)) ?>">
    <?php if ($view === 'code'): ?>
        <link rel="stylesheet" href="<?= e(assetUrl('codemirror/codemirror.css', $root)) ?>">
        <link rel="stylesheet" href="<?= e(assetUrl('code-editor.css', $root, versioned: true)) ?>">
        <script src="<?= e(assetUrl('codemirror/codemirror.js', $root)) ?>" defer></script>
        <script src="<?= e(assetUrl('codemirror/clike.js', $root)) ?>" defer></script>
        <script src="<?= e(assetUrl('codemirror/php.js', $root)) ?>" defer></script>
        <script src="<?= e(assetUrl('codemirror/xml-fold.js', $root, versioned: true)) ?>" defer></script>
        <script src="<?= e(assetUrl('codemirror/closetag.js', $root, versioned: true)) ?>" defer></script>
        <?php foreach (['beautify.js', 'beautify-css.js', 'beautify-html.js'] as $formatter): ?>
            <script src="<?= e(assetUrl('beautify/' . $formatter, $root)) ?>" defer></script>
        <?php endforeach; ?>
        <script src="<?= e(assetUrl('code-editor.js', $root, versioned: true)) ?>" defer></script>
    <?php endif; ?>
</head>
<body class="admin-page">
    <aside id="sidebar" class="sidebar">
        <a class="brand sidebar-brand" href="<?= e(adminUrl()) ?>"><?php brand(); ?></a>
        <button class="icon-button sidebar-close" type="button" data-menu-close aria-label="Закрыть меню" hidden><?php icon('close'); ?></button>
        <nav class="sidebar-nav" aria-label="Панель управления">
            <span class="nav-caption">Управление</span>
            <?php foreach ($nav as $name => [$glyph, $label]): ?>
                <a href="<?= e(adminUrl($name)) ?>" <?= $active === $name ? 'aria-current="page"' : '' ?>>
                    <?php icon($glyph); ?><span><?= e($label) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-footer">
            <div class="sidebar-profile">
                <div class="avatar" aria-hidden="true">A</div>
                <div><strong>admin</strong><span>Администратор</span></div>
            </div>
            <form action="<?= e(adminUrl()) ?>" method="post">
                <?php csrfField(); ?>
                <button class="sign-out" name="action" value="logout"><?php icon('logout'); ?> Выйти</button>
            </form>
        </div>
    </aside>
    <button class="mobile-overlay" type="button" data-menu-close aria-label="Закрыть меню" hidden></button>
    <div class="workspace">
        <header class="topbar">
            <button class="icon-button menu-toggle" type="button" aria-label="Открыть меню" aria-expanded="false" aria-controls="sidebar" hidden><?php icon('menu'); ?></button>
            <nav class="breadcrumbs" aria-label="Путь к странице">
                <a href="<?= e(adminUrl()) ?>">Панель управления</a>
                <?php icon('chevron'); ?>
                <span aria-current="page"><?= e($nav[$active][1]) ?></span>
            </nav>
            <a class="button button-quiet website-link" href="../" target="_blank" rel="noopener">
                <?php icon('globe'); ?><span>Открыть сайт</span><?php icon('external', 'small-icon'); ?>
            </a>
        </header>
        <main class="admin-content" id="main">
            <?php if ($notice !== ''): ?>
                <div class="feedback feedback-success" role="status"><?php icon('check'); ?> <?= e($notice) ?></div>
            <?php endif; ?>
            <?php if ($error !== ''): ?>
                <div class="feedback feedback-error" role="alert"><?= e($error) ?></div>
            <?php endif; ?>
            <?php require match ($view) {
                'dashboard' => __DIR__ . '/dashboard.php',
                'code' => __DIR__ . '/code.php',
                'settings' => __DIR__ . '/settings.php',
                'fields', 'field-group', 'pages', 'page' => __DIR__ . '/fields.php',
            }; ?>
        </main>
    </div>
</body>
</html>
