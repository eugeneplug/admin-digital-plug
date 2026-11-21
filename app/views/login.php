<?php require dirname(__DIR__) . '/guard.php'; ?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Вход · Plugy Engine</title>
    <link rel="stylesheet" href="<?= e(assetUrl('style.css', $root)) ?>">
    <script src="<?= e(assetUrl('app.js', $root)) ?>" defer></script>
</head>
<body class="login-page">
    <main class="login-shell">
        <section class="login-main">
            <a class="brand" href="<?= e(adminUrl('login')) ?>"><?php brand(); ?></a>
            <div class="login-content">
                <h1>Вход в админку</h1>
                <p class="login-description">Войдите, чтобы управлять контентом сайта.</p>
                <?php if ($installed): ?>
                    <p class="feedback feedback-success" role="status"><?php icon('check'); ?> Plugy Engine установлен. Войдите с паролем, который вы задали.</p>
                <?php endif; ?>
                <?php if ($demoBlocked): ?>
                    <p class="feedback feedback-error" role="alert">Смените пароль локальной учётной записи в настройках перед переносом сайта на сервер.</p>
                <?php else: ?>
                    <?php if ($error !== ''): ?>
                        <p class="feedback feedback-error" role="alert"><?= e($error) ?></p>
                    <?php endif; ?>
                    <form class="login-form" method="post" action="<?= e(adminUrl('login')) ?>">
                        <?php csrfField(); ?>
                        <div class="field">
                            <label for="username">Логин</label>
                            <div class="input-wrap">
                                <?php icon('user', 'input-icon'); ?>
                                <input id="username" name="username" placeholder="Введите логин" autocomplete="username" maxlength="100" value="<?= e($username) ?>" required>
                            </div>
                        </div>
                        <div class="field">
                            <label for="password">Пароль</label>
                            <div class="input-wrap">
                                <?php icon('lock', 'input-icon'); ?>
                                <input id="password" name="password" type="password" placeholder="Введите пароль" autocomplete="current-password" maxlength="72" required>
                                <button class="password-toggle" type="button" data-password="password" aria-label="Показать пароль" hidden>
                                    <?php icon('eye'); icon('eye-off'); ?>
                                </button>
                            </div>
                        </div>
                        <button class="button button-primary login-submit" type="submit">Войти</button>
                    </form>
                    <?php if ((bool) $account['demo']): ?>
                        <p class="demo-hint"><?php icon('lock'); ?> Локальный вход <code>admin / admin</code></p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <a class="login-footer" href="../"><?php icon('globe'); ?> Перейти на сайт <?php icon('external', 'small-icon'); ?></a>
        </section>
        <div class="login-visual" aria-hidden="true">
            <div class="visual-frame"></div>
            <div class="login-rings"><i></i><i></i><i></i><i></i></div>
            <span class="visual-point point-one"></span>
            <span class="visual-point point-two"></span>
            <span class="visual-point point-three"></span>
        </div>
    </main>
</body>
</html>
