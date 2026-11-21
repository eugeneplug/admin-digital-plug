<?php require dirname(__DIR__) . '/guard.php'; ?>
<?php if ($editing): ?>
    <a class="text-link back-link" href="<?= e(adminUrl('record-types')) ?>"><?php icon('chevron'); ?> К группам записей</a>
<?php endif; ?>
<div class="page-heading">
    <div><h1><?= e($title) ?></h1><p>У каждой группы свой раздел в меню и набор полей.</p></div>
    <?php if (!$editing): ?>
        <a class="button button-primary" href="<?= e(adminUrl('record-type')) ?>"><?php icon('plus'); ?> Новая группа</a>
    <?php endif; ?>
</div>
<?php if ($editing): ?>
    <form class="panel editor-form" method="post">
        <?php csrfField(); ?>
        <div class="field">
            <label for="record-type-title">Название группы</label>
            <input id="record-type-title" name="title" value="<?= e($type['title']) ?>" placeholder="Например, Тарифы" maxlength="120" required>
        </div>
        <div class="field">
            <label for="record-type-slug">Ключ для PHP</label>
            <input id="record-type-slug" name="slug" value="<?= e($type['slug']) ?>" placeholder="tarify" maxlength="100" pattern="[a-z0-9]+(?:-[a-z0-9]+)*">
            <small>Если оставить пустым, ключ создастся из названия. В шаблоне он используется в get_records().</small>
        </div>
        <div class="form-actions"><button class="button button-primary" name="action" value="save-type"><?php icon('check'); ?> Сохранить группу</button></div>
    </form>
    <?php if ($id): ?>
        <details class="delete-section">
            <summary>Удалить группу записей</summary>
            <form method="post">
                <?php csrfField(); ?>
                <p>Удалить можно пустую группу без привязанных полей.</p>
                <button class="button button-danger" name="action" value="delete-type">Подтвердить удаление</button>
            </form>
        </details>
    <?php endif; ?>
<?php else: ?>
    <section class="panel groups-panel" aria-label="Группы записей">
        <table class="groups-table">
            <thead><tr><th scope="col">Название группы</th><th scope="col">Ключ для PHP</th><th scope="col"><span class="fields-sr-only">Действие</span></th></tr></thead>
            <tbody>
                <?php foreach ($sidebarRecordTypes as $item): ?>
                    <tr>
                        <th scope="row"><a class="group-name" href="<?= e(recordAdminUrl('records', (int) $item['id'])) ?>"><?= e($item['title']) ?></a></th>
                        <td><code class="builder-key"><?= e($item['slug']) ?></code></td>
                        <td><a class="icon-button group-edit" href="<?= e(adminUrl('record-type', (int) $item['id'])) ?>" aria-label="<?= e('Редактировать группу «' . $item['title'] . '»') ?>" title="Редактировать"><?php icon('edit'); ?></a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (!$sidebarRecordTypes): ?><p class="fields-empty">Групп записей пока нет.</p><?php endif; ?>
    </section>
<?php endif; ?>
