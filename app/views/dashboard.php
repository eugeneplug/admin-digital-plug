<?php
require dirname(__DIR__) . '/guard.php';

$published = count(array_filter($articles, static fn(array $item): bool => $item['status'] === 'published'));
$stats = [
    ['layers', 'Блоки сайта', count($blocks), 'На главной странице', 'blocks', 'blue'],
    ['file', 'Статьи', count($articles), $published . ' опубликовано', 'articles', 'violet'],
    ['clock', 'Черновики', count($articles) - $published, 'Статьи в работе', 'articles', 'amber'],
];
?>
<div class="page-heading">
    <div><h1>Обзор</h1><p>Управляйте главной страницей и журналом сайта.</p></div>
    <a class="button button-primary" href="<?= e(adminUrl('article')) ?>"><?php icon('plus'); ?> Новая статья</a>
</div>
<section class="welcome">
    <div class="welcome-copy">
        <h2>Ваш сайт под рукой</h2>
        <p>Обновите главную страницу, поменяйте порядок блоков или опубликуйте новую запись в журнале.</p>
        <a class="button button-on-dark" href="<?= e(adminUrl('blocks')) ?>"><?php icon('layers'); ?> Редактировать главную</a>
    </div>
    <div class="welcome-art" aria-hidden="true">
        <span class="art-orbit"></span><span class="art-tile art-tile-bottom"></span><span class="art-tile art-tile-middle"></span>
        <span class="art-tile art-tile-top"></span>
        <span class="art-spark"></span><span class="art-spark art-spark-two"></span>
    </div>
</section>
<div class="stats-grid">
    <?php foreach ($stats as [$glyph, $label, $value, $support, $target, $tint]): ?>
        <a class="panel stat-card" href="<?= e(adminUrl($target)) ?>">
            <span class="tile-icon tint-<?= e($tint) ?>"><?php icon($glyph); ?></span>
            <span class="stat-label"><?= e($label) ?></span>
            <strong class="stat-value"><?= e($value) ?></strong>
            <span class="stat-support"><?= e($support) ?></span>
        </a>
    <?php endforeach; ?>
</div>
<div class="dashboard-bottom">
    <section class="panel recent">
        <div class="panel-heading">
            <h2>Последние статьи</h2>
            <a class="text-link" href="<?= e(adminUrl('articles')) ?>">Все статьи <?php icon('chevron'); ?></a>
        </div>
        <?php if ($articles): ?>
            <div class="recent-list">
                <?php foreach (array_slice($articles, 0, 4) as $item): ?>
                    <a class="recent-row" href="<?= e(adminUrl('article', (int) $item['id'])) ?>">
                        <span class="tile-icon tint-neutral"><?php icon('file'); ?></span>
                        <span class="recent-text"><strong><?= e($item['title']) ?></strong><span><?= e(dateLabel((int) $item['updated_at'])) ?></span></span>
                        <?php statusBadge($item['status'] === 'published', 'Опубликована', 'Черновик'); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="empty-compact">Здесь появятся ваши статьи.</p>
        <?php endif; ?>
    </section>
    <section class="panel quick">
        <div class="panel-heading"><h2>Быстрые действия</h2></div>
        <div class="quick-list">
            <a href="<?= e(adminUrl('block')) ?>"><span class="tile-icon tint-blue"><?php icon('plus'); ?></span><strong>Добавить блок</strong><?php icon('chevron', 'row-chevron'); ?></a>
            <a href="../" target="_blank" rel="noopener"><span class="tile-icon tint-violet"><?php icon('globe'); ?></span><strong>Посмотреть сайт</strong><?php icon('external', 'row-chevron'); ?></a>
            <a href="<?= e(adminUrl('settings')) ?>"><span class="tile-icon tint-neutral"><?php icon('shield'); ?></span><strong>Безопасность</strong><?php icon('chevron', 'row-chevron'); ?></a>
        </div>
    </section>
</div>
