<?php require dirname(__DIR__) . '/guard.php'; ?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Установка · Plugy Engine</title>
    <link rel="stylesheet" href="<?= e(assetUrl('style.css', $root)) ?>">
</head>
<body class="problem-page">
    <main class="panel problem-card install-card">
        <div class="brand"><?php brand(); ?></div>
        <p class="install-step">Шаг <?= $step === 'database' ? '1' : '2' ?> из 2</p>
        <h1><?= $step === 'database' ? 'Подключение к базе данных' : 'Настройка администратора' ?></h1>
        <p><?= $step === 'database'
            ? 'Введите данные пустой базы MySQL или MariaDB из панели хостинга.'
            : 'Подключение проверено. Задайте пароль для логина admin. Plugy создаст таблицы и аккаунт.' ?></p>
        <?php if ($error !== ''): ?>
            <p class="feedback feedback-error" role="alert"><?= e($error) ?></p>
        <?php endif; ?>
        <form class="install-form" method="post" action="<?= e($setupUrl) ?>">
            <?php csrfField(); ?>
            <input type="hidden" name="step" value="<?= e($step) ?>">
            <?php if ($step === 'database'): ?>
                <div class="field">
                    <label for="db-name">Название базы данных</label>
                    <input id="db-name" name="db_name" value="<?= e($settings['name']) ?>" maxlength="64" required>
                </div>
                <div class="field">
                    <label for="db-user">Пользователь базы данных</label>
                    <input id="db-user" name="db_user" value="<?= e($settings['user']) ?>" maxlength="128" required>
                </div>
                <div class="field">
                    <label for="db-password">Пароль базы данных</label>
                    <input id="db-password" name="db_password" type="password" autocomplete="off">
                </div>
                <div class="install-host">
                    <div class="field">
                        <label for="db-host">Хост базы данных</label>
                        <input id="db-host" name="db_host" value="<?= e($settings['host']) ?>" maxlength="253" required>
                    </div>
                    <div class="field">
                        <label for="db-port">Порт</label>
                        <input id="db-port" name="db_port" value="<?= e($settings['port']) ?>" placeholder="3306" inputmode="numeric" maxlength="5">
                    </div>
                </div>
                <button class="button button-primary" type="submit">Проверить подключение</button>
            <?php else: ?>
                <div class="field">
                    <label for="install-password">Пароль администратора</label>
                    <input id="install-password" name="password" type="password" autocomplete="new-password" minlength="15" maxlength="72" aria-describedby="install-password-hint" required>
                    <small id="install-password-hint">От 15 символов и до 72 байт.</small>
                </div>
                <div class="field">
                    <label for="install-repeat">Повторите пароль</label>
                    <input id="install-repeat" name="repeat_password" type="password" autocomplete="new-password" maxlength="72" required>
                </div>
                <button class="button button-primary" type="submit">Установить Plugy Engine</button>
                <a class="text-link" href="<?= e($setupUrl . '&step=database') ?>">Изменить подключение</a>
            <?php endif; ?>
        </form>
    </main>
</body>
</html>
