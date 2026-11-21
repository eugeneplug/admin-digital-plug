<?php require dirname(__DIR__) . '/guard.php'; ?>
<div class="page-heading">
    <div><h1><?= e($title) ?></h1><p><?= $view === 'fields' ? 'Создавайте поля, объединяйте их в группы и используйте в PHP-шаблонах.' : 'Управляйте содержимым сайта.' ?></p></div>
    <?php if (in_array($view, ['fields', 'pages'], true)): ?><a class="button button-primary" href="<?= e(adminUrl($view === 'fields' ? 'field-group' : 'page')) ?>"><?php icon('plus'); ?> Добавить</a><?php endif; ?>
</div>
<?php if ($view === 'fields'): ?>
    <?php $groups = array_filter(fieldGroups($db), static fn(array $group): bool => $group['scope'] === 'page'); ?>
    <?php if (!$groups): ?><p class="field-help">Групп полей пока нет.</p><?php endif; ?>
    <div class="panel field-list">
    <?php foreach ($groups as $group): ?>
        <a class="field-list-row" href="<?= e(adminUrl('field-group', (int) $group['id'])) ?>"><strong><?= e($group['title']) ?></strong><span><?= $group['target_id'] ? 'Страница #' . (int) $group['target_id'] : 'Все страницы' ?></span><small><?= count($group['fields']) ?> полей</small></a>
    <?php endforeach; ?>
    </div>
    <p class="field-help"><code>get_field('ключ')</code> получает значение поля страницы. <code>field('ключ')</code> выводит его.</p>
<?php elseif ($view === 'pages'): ?>
    <div class="panel field-list">
    <?php foreach ($db->query('SELECT * FROM pages ORDER BY id')->fetchAll() as $item): ?>
        <a class="field-list-row" href="<?= e(adminUrl('page', (int) $item['id'])) ?>"><strong><?= e($item['title']) ?></strong><span><?= e($item['slug']) ?></span><small><?= $item['status'] === 'published' ? 'Опубликована' : 'Черновик' ?></small></a>
    <?php endforeach; ?>
    </div>
<?php elseif ($view === 'field-group'): ?>
    <form class="panel editor-form" method="post" data-group-form>
        <?php csrfField(); ?>
        <div class="field"><label for="group-title">Название группы</label><input id="group-title" name="title" value="<?= e($record['title']) ?>" maxlength="120" required></div>
        <div class="field">
            <label for="group-target">Где показывать</label>
            <select id="group-target" name="target_id">
                <option value="0">На всех страницах</option>
                <?php foreach ($db->query('SELECT id, title FROM pages ORDER BY id')->fetchAll() as $item): ?>
                    <option value="<?= (int) $item['id'] ?>" <?= (int) $record['target_id'] === (int) $item['id'] ? 'selected' : '' ?>><?= e($item['title']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <input type="hidden" name="definition" value="<?= e($record['definition']) ?>">
        <div data-definition-builder></div>
        <noscript>Для создания полей включите JavaScript.</noscript>
        <button class="button button-primary" name="action" value="save-group">Сохранить группу</button>
    </form>
<?php elseif ($view === 'page'): ?>
    <?php if ($id && $record['status'] === 'published'): ?><p class="field-help"><a class="text-link" href="<?= e('../?page=' . rawurlencode($record['slug'])) ?>" target="_blank" rel="noopener">Открыть страницу ↗</a></p><?php endif; ?>
    <form class="panel editor-form" method="post"><?php csrfField(); ?>
        <div class="field"><label for="page-title">Название</label><input id="page-title" name="title" value="<?= e($record['title']) ?>" required maxlength="120"></div>
        <div class="field-grid">
            <div class="field"><label for="page-slug">Адрес</label><input id="page-slug" name="slug" value="<?= e($record['slug']) ?>" maxlength="100" <?= $record['slug'] === 'home' ? 'readonly' : '' ?>></div>
            <div class="field"><label for="page-status">Статус</label><select id="page-status" name="status"><option value="draft" <?= $record['status'] === 'draft' ? 'selected' : '' ?>>Черновик</option><option value="published" <?= $record['status'] === 'published' ? 'selected' : '' ?>>Опубликована</option></select></div>
        </div>
        <div class="field"><label for="page-body">Текст</label><textarea id="page-body" name="body" rows="6" maxlength="60000"><?= e($record['body']) ?></textarea></div>
        <?php fieldValuesForm($db, 'page', $id); ?>
        <button class="button button-primary" name="action" value="save-page">Сохранить страницу</button>
    </form>
<?php endif; ?>
<?php if ($id && ($view === 'field-group' || ($view === 'page' && $record['slug'] !== 'home'))): ?>
    <details class="delete-section"><summary>Удалить <?= $view === 'page' ? 'страницу' : 'группу' ?></summary><form method="post"><?php csrfField(); ?><p>Запись и её значения будут удалены без возможности восстановления.</p><button class="button button-danger" name="action" value="<?= $view === 'page' ? 'delete-page' : 'delete-group' ?>">Подтвердить удаление</button></form></details>
<?php endif; ?>
