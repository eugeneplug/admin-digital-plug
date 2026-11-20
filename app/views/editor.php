<?php
$forBlock = $view === 'block';
$listUrl = adminUrl($forBlock ? 'blocks' : 'articles');
$formUrl = adminUrl($view, $id);
?>
<a class="text-link back-link" href="<?= e($listUrl) ?>"><?php icon('chevron'); ?> <?= $forBlock ? 'К блокам' : 'К статьям' ?></a>
<div class="page-heading">
    <div>
        <h1><?= e($title) ?></h1>
        <p><?= $forBlock ? 'Настройте содержимое секции на главной странице.' : 'Заполните статью и выберите статус публикации.' ?></p>
    </div>
    <?php if (!$forBlock && $id && !empty($record['published_at'])): ?>
        <a class="button button-secondary" href="<?= e(articleUrl($savedSlug, '../')) ?>" target="_blank" rel="noopener"><?php icon('external'); ?> Открыть на сайте</a>
    <?php endif; ?>
</div>
<form class="panel editor-form" method="post" action="<?= e($formUrl) ?>">
    <?php csrfField(); ?>
    <?php if ($forBlock): ?>
        <div class="field-grid">
            <div class="field">
                <label for="kind">Тип блока</label>
                <select id="kind" name="kind">
                    <?php foreach (BLOCK_TYPES as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= $record['kind'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="eyebrow">Надзаголовок</label>
                <input id="eyebrow" name="eyebrow" value="<?= e($record['eyebrow']) ?>" placeholder="Например, О студии" maxlength="60">
            </div>
        </div>
    <?php endif; ?>
    <div class="field">
        <label for="title">Заголовок</label>
        <input id="title" name="title" value="<?= e($record['title']) ?>" maxlength="120" required>
    </div>
    <?php if (!$forBlock): ?>
        <div class="field">
            <label for="excerpt">Анонс</label>
            <textarea id="excerpt" name="excerpt" rows="2" maxlength="280" aria-describedby="excerpt-hint"><?= e($record['excerpt']) ?></textarea>
            <small id="excerpt-hint">Короткое описание для карточки в журнале.</small>
        </div>
    <?php endif; ?>
    <div class="field">
        <label for="body"><?= $forBlock ? 'Текст блока' : 'Текст статьи' ?></label>
        <textarea id="body" name="body" rows="<?= $forBlock ? '5' : '10' ?>" maxlength="<?= $forBlock ? '6000' : '60000' ?>"><?= e($record['body']) ?></textarea>
    </div>
    <?php if ($forBlock): ?>
        <div class="field-grid" data-block-link>
            <div class="field">
                <label for="link-label">Текст кнопки</label>
                <input id="link-label" name="link_label" value="<?= e($record['link_label']) ?>" maxlength="40">
            </div>
            <div class="field">
                <label for="link-url">Ссылка</label>
                <input id="link-url" name="link_url" value="<?= e($record['link_url']) ?>" placeholder="#journal или https://…" maxlength="400">
            </div>
        </div>
        <div class="checkbox-field">
            <input id="visible" name="visible" type="checkbox" value="1" <?= $record['visible'] ? 'checked' : '' ?>>
            <label for="visible">Показывать на сайте</label>
        </div>
    <?php else: ?>
        <div class="field-grid">
            <div class="field">
                <label for="slug">Адрес статьи</label>
                <input id="slug" name="slug" value="<?= e($record['slug']) ?>" placeholder="moya-statya" maxlength="100" aria-describedby="slug-hint">
                <small id="slug-hint">Если оставить поле пустым, адрес создастся из заголовка.</small>
            </div>
            <div class="field">
                <label for="status">Статус</label>
                <select id="status" name="status">
                    <option value="draft" <?= $record['status'] === 'draft' ? 'selected' : '' ?>>Черновик</option>
                    <option value="published" <?= $record['status'] === 'published' ? 'selected' : '' ?>>Опубликована</option>
                </select>
            </div>
        </div>
    <?php endif; ?>
    <div class="form-actions">
        <button class="button button-primary" type="submit" name="action" value="save">
            <?php icon('check'); ?>
            <span <?= $forBlock ? '' : 'data-save-status' ?>><?= $forBlock ? 'Сохранить блок' : ($record['status'] === 'published' ? 'Сохранить публикацию' : 'Сохранить черновик') ?></span>
        </button>
        <a class="button button-secondary" href="<?= e($listUrl) ?>">Отмена</a>
    </div>
</form>
<?php if ($id): ?>
    <details class="delete-section">
        <summary><?php icon('trash'); ?> <?= $forBlock ? 'Удалить блок' : 'Удалить статью' ?></summary>
        <form method="post" action="<?= e($formUrl) ?>">
            <?php csrfField(); ?>
            <p>Запись будет удалена без возможности восстановления.</p>
            <button class="button button-danger" type="submit" name="action" value="delete">Подтвердить удаление</button>
        </form>
    </details>
<?php endif; ?>
