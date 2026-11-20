<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?= e($title) ?> · Plugy Engine</title>
    <link rel="stylesheet" href="<?= e($root) ?>assets/style.css">
</head>
<body class="problem-page">
    <main class="panel problem-card">
        <div class="brand"><?php brand(); ?></div>
        <h1><?= e($title) ?></h1>
        <p><?= e($message) ?></p>
        <a class="button button-primary" href="<?= e($root) ?>">На главную</a>
    </main>
</body>
</html>
