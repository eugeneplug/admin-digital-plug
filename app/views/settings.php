<?php require dirname(__DIR__) . '/guard.php'; ?>
<div class="page-heading">
    <div><h1>Настройки</h1><p>Управляйте доступом к панели Plugy Engine.</p></div>
</div>
<section class="panel settings-card">
    <div class="settings-heading">
        <span class="tile-icon tint-blue"><?php icon('shield'); ?></span>
        <div><h2>Пароль администратора</h2><p>Учётная запись <strong>admin</strong></p></div>
    </div>
    <?php if ((bool) $account['demo']): ?>
        <div class="feedback feedback-info">Сейчас используется локальный пароль. Смените его перед переносом этой базы на сервер.</div>
    <?php endif; ?>
    <form class="settings-form" method="post" action="<?= e(adminUrl('settings')) ?>">
        <?php csrfField(); ?>
        <div class="field">
            <label for="current-password">Текущий пароль</label>
            <input id="current-password" name="current_password" type="password" autocomplete="current-password" maxlength="72" required>
        </div>
        <div class="field">
            <label for="new-password">Новый пароль</label>
            <input id="new-password" name="new_password" type="password" autocomplete="new-password" minlength="15" maxlength="72" aria-describedby="password-hint" required>
            <small id="password-hint">Минимум 15 символов, максимум 72 байта.</small>
        </div>
        <div class="field">
            <label for="repeat-password">Повторите новый пароль</label>
            <input id="repeat-password" name="repeat_password" type="password" autocomplete="new-password" minlength="15" maxlength="72" required>
        </div>
        <div class="form-actions">
            <button class="button button-primary" type="submit" name="action" value="password"><?php icon('check'); ?> Сменить пароль</button>
        </div>
    </form>
</section>
