<?php require dirname(__DIR__) . '/guard.php'; ?>
<?php if (in_array($view, ['fields', 'field-group'], true)): ?>
    <?php $pageNames = $db->query('SELECT id, title FROM pages ORDER BY id')->fetchAll(PDO::FETCH_KEY_PAIR); ?>
<?php else: ?>
    <div class="page-heading">
        <div><h1><?= e($title) ?></h1><p>Управляйте содержимым сайта.</p></div>
        <?php if ($view === 'pages'): ?><a class="button button-primary" href="<?= e(adminUrl('page')) ?>"><?php icon('plus'); ?> Добавить</a><?php endif; ?>
    </div>
<?php endif; ?>
<?php if ($view === 'fields'): ?>
    <?php $groups = array_filter(fieldGroups($db), static fn(array $group): bool => $group['scope'] === 'page'); ?>
    <div class="page-heading fields-heading">
        <div><h1>Группы полей</h1><p>Создавайте поля и назначайте их страницам.</p></div>
        <a class="button button-primary" href="<?= e(adminUrl('field-group')) ?>"><?php icon('plus'); ?> Новая группа</a>
    </div>
    <section class="panel groups-panel" data-group-list aria-label="Группы полей">
        <div class="fields-panel-head groups-toolbar">
            <div class="fields-panel-title">
                <span class="fields-tile"><?php icon('layers'); ?></span>
                <h2>Все группы</h2>
                <span class="fields-count" data-group-count role="status" aria-label="Количество групп"><?= count($groups) ?></span>
            </div>
            <?php if ($groups): ?>
                <div class="groups-filters" data-group-filters hidden>
                    <label class="groups-search">
                        <?php icon('search'); ?>
                        <input type="search" aria-label="Поиск групп" placeholder="Поиск групп" data-group-search>
                    </label>
                    <div class="field">
                        <label class="fields-sr-only" for="groups-target">Страница</label>
                        <select id="groups-target" data-custom-select data-group-filter>
                            <option value="">Любая страница</option>
                            <option value="0">На всех страницах</option>
                            <?php foreach ($pageNames as $pageId => $pageTitle): ?>
                                <option value="<?= (int) $pageId ?>"><?= e($pageTitle) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($groups): ?>
            <table class="groups-table">
                <thead><tr><th scope="col">Название группы</th><th scope="col">Где показывать</th><th scope="col">Поля</th><th scope="col"><span class="fields-sr-only">Действие</span></th></tr></thead>
                <tbody>
                    <?php foreach ($groups as $group): ?>
                        <?php $target = (int) $group['target_id']; ?>
                        <tr data-group-row data-target="<?= $target ?>">
                            <th scope="row"><a class="group-name" href="<?= e(adminUrl('field-group', (int) $group['id'])) ?>" data-group-name><?= e($group['title']) ?></a></th>
                            <td><span class="group-location"><?php icon($target ? 'file' : 'globe'); ?> <?= e($target ? ($pageNames[$target] ?? 'Удалённая страница') : 'Все страницы') ?></span></td>
                            <td><span class="fields-count"><?= count($group['fields']) ?></span></td>
                            <td><a class="icon-button group-edit" href="<?= e(adminUrl('field-group', (int) $group['id'])) ?>" aria-label="<?= e('Редактировать группу «' . $group['title'] . '»') ?>" title="Редактировать"><?php icon('edit'); ?></a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="fields-empty" data-group-empty hidden>
                <p><strong>Группы не найдены</strong></p>
                <button class="button button-secondary" type="button" data-group-reset>Сбросить фильтры</button>
            </div>
        <?php else: ?>
            <div class="fields-empty">
                <span class="fields-tile"><?php icon('layers'); ?></span>
                <p><strong>Групп полей пока нет</strong></p>
                <p>Добавьте первую группу и назначьте её странице.</p>
            </div>
        <?php endif; ?>
    </section>
<?php elseif ($view === 'pages'): ?>
    <div class="panel field-list">
    <?php foreach ($db->query('SELECT * FROM pages ORDER BY id')->fetchAll() as $item): ?>
        <a class="field-list-row" href="<?= e(adminUrl('page', (int) $item['id'])) ?>"><strong><?= e($item['title']) ?></strong><span><?= e($item['slug']) ?></span><small><?= $item['status'] === 'published' ? 'Опубликована' : 'Черновик' ?></small></a>
    <?php endforeach; ?>
    </div>
<?php elseif ($view === 'field-group'): ?>
    <a class="text-link back-link" href="<?= e(adminUrl('fields')) ?>"><?php icon('chevron'); ?> К группам полей</a>
    <form class="group-form" method="post" data-group-form>
        <?php csrfField(); ?>
        <input type="hidden" name="definition" value="<?= e($record['definition']) ?>">
        <div class="page-heading fields-heading">
            <div><h1><?= e($title) ?></h1><p>Настройте поля и выберите, где их заполнять.</p></div>
            <button class="button button-primary" name="action" value="save-group"><?php icon('check'); ?> Сохранить группу</button>
        </div>
        <div class="group-layout">
            <section class="panel builder-panel" aria-labelledby="builder-heading">
                <div class="fields-panel-head">
                    <div class="fields-panel-title">
                        <span class="fields-tile"><?php icon('layers'); ?></span>
                        <h2 id="builder-heading">Поля группы</h2>
                        <span class="fields-count" data-builder-count aria-label="Количество полей" hidden></span>
                    </div>
                    <button class="button button-secondary builder-add" type="button" data-add-definition hidden><?php icon('plus'); ?> Добавить поле</button>
                </div>
                <div data-definition-builder></div>
                <noscript><p class="fields-empty">Для создания полей включите JavaScript.</p></noscript>
            </section>
            <section class="panel group-settings" aria-labelledby="group-settings-heading">
                <div class="fields-panel-head">
                    <div class="fields-panel-title">
                        <span class="fields-tile"><?php icon('settings'); ?></span>
                        <h2 id="group-settings-heading">Настройки группы</h2>
                    </div>
                </div>
                <div class="group-settings-body">
                    <div class="field">
                        <label for="group-title">Название группы</label>
                        <input id="group-title" name="title" value="<?= e($record['title']) ?>" placeholder="Например, Главный экран" maxlength="120" required>
                    </div>
                    <div class="field">
                        <label for="group-target">Где показывать</label>
                        <select id="group-target" name="target_id" data-custom-select aria-describedby="group-target-help">
                            <option value="0">На всех страницах</option>
                            <?php $target = (int) $record['target_id']; ?>
                            <?php if ($target && !isset($pageNames[$target])): ?>
                                <option value="<?= $target ?>" selected>Удалённая страница</option>
                            <?php endif; ?>
                            <?php foreach ($pageNames as $pageId => $pageTitle): ?>
                                <option value="<?= (int) $pageId ?>" <?= $target === (int) $pageId ? 'selected' : '' ?>><?= e($pageTitle) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small id="group-target-help">Поля появятся в редакторе выбранной страницы.</small>
                    </div>
                </div>
            </section>
        </div>
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
