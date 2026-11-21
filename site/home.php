<?php defined('PLUGY_ENTRY') || exit;
$hasHeading = false;
foreach ($blocks as $block) {
    $heading = $block['kind'] === 'hero' && !$hasHeading ? 'h1' : 'h2';
    $hasHeading = $hasHeading || $heading === 'h1';
    component('block', ['block' => $block, 'heading' => $heading]);
}
component('benefits', ['rows' => get_field('benefits', 'options')]);
?>
<?php if ($page['body'] !== ''): ?><section class="site-block"><div class="article-body"><?= e($page['body']) ?></div></section><?php endif; ?>
<section class="journal" id="journal">
    <div class="journal-heading">
        <?php if ($hasHeading): ?><h2>Журнал</h2><?php else: ?><h1>Журнал</h1><?php endif; ?>
        <p>Заметки о дизайне и работе над сайтами.</p>
    </div>
    <?php if (!$articles): ?><p class="empty-compact">Здесь появятся опубликованные статьи.</p><?php endif; ?>
    <div class="journal-grid">
        <?php foreach ($articles as $index => $item) component('article-card', ['item' => $item, 'index' => $index]); ?>
    </div>
</section>
