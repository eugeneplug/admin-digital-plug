<?php require dirname(__DIR__) . '/guard.php'; ?>
<div class="page-heading code-heading">
    <div>
        <h1>Редактор кода</h1>
        <p>Шаблоны главной, блоков и статей. Сохранённые изменения сразу появятся на сайте.</p>
    </div>
    <button class="button button-primary" form="theme-editor" type="submit"><?php icon('check'); ?> Сохранить</button>
</div>

<div class="code-layout">
    <form class="code-panel" id="theme-editor" action="<?= e(themeEditorUrl($themeFile)) ?>" method="post" data-unsaved="<?= $error === '' ? 'false' : 'true' ?>">
        <?php csrfField(); ?>
        <input type="hidden" name="action" value="save-code">
        <input type="hidden" name="revision" value="<?= e($themeRevision) ?>">
        <div class="code-toolbar">
            <label for="theme-source"><?php icon('code'); ?> <span><small>app/theme/</small><?= e($themeFile) ?></span></label>
            <button class="code-tool" type="button" data-code-search hidden><?php icon('search'); ?> Найти</button>
        </div>
        <textarea id="theme-source" name="source" data-mode="<?= e(THEME_FILES[$themeFile][2]) ?>" spellcheck="false" autocapitalize="off">
<?= e($themeSource) ?></textarea>
        <div class="code-status">
            <span data-code-status role="status"><?= $error === '' ? 'Сохранено' : 'Изменения не сохранены' ?></span>
            <span data-code-position hidden></span>
        </div>
    </form>

    <aside class="panel code-files" aria-label="Файлы сайта">
        <h2>Файлы сайта</h2>
        <nav aria-label="Файлы темы">
            <?php foreach (THEME_FILES as $filename => [$format, $description]): ?>
                <a class="code-file" href="<?= e(themeEditorUrl($filename)) ?>" <?= $themeFile === $filename ? 'aria-current="page"' : '' ?>>
                    <span class="code-file-format" aria-hidden="true"><?= e($format) ?></span>
                    <span class="code-file-description"><strong><?= e($filename) ?></strong><small><?= e($description) ?></small></span>
                </a>
            <?php endforeach; ?>
        </nav>
        <?php if (THEME_FILES[$themeFile][0] === 'HTML'): ?>
            <p class="code-file-help">Переменные в <code>{{ }}</code> подставляют данные или разметку из других шаблонов.</p>
        <?php endif; ?>
    </aside>
</div>
