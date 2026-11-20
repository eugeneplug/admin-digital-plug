<?php
$forBlocks = $view === 'blocks';
$items = $forBlocks ? $blocks : $articles;
$type = $forBlocks ? 'block' : 'article';
?>
<div class="page-heading">
    <div>
        <h1><?= e($title) ?></h1>
        <p><?= $forBlocks ? 'Управляйте секциями главной страницы и их порядком.' : 'Пишите черновики и публикуйте записи в журнале.' ?></p>
    </div>
    <a class="button button-primary" href="<?= e(adminUrl($type)) ?>"><?php icon('plus'); ?> <?= $forBlocks ? 'Добавить блок' : 'Новая статья' ?></a>
</div>
<section class="panel list-panel">
    <?php if (!$items): ?>
        <div class="empty">
            <span class="tile-icon tint-blue"><?php icon($forBlocks ? 'layers' : 'file'); ?></span>
            <h2><?= $forBlocks ? 'Пока нет блоков' : 'Пока нет статей' ?></h2>
            <p><?= $forBlocks ? 'Добавьте первый блок для главной страницы.' : 'Создайте первую запись для журнала.' ?></p>
        </div>
    <?php else: ?>
        <div class="list-labels" aria-hidden="true">
            <span><?= $forBlocks ? 'Блок' : 'Статья' ?></span><span>Статус</span><span><?= $forBlocks ? 'Порядок' : 'Обновлено' ?></span>
        </div>
        <ul class="content-list">
            <?php foreach ($items as $index => $item): ?>
                <li class="list-row">
                    <a class="list-title" href="<?= e(adminUrl($type, (int) $item['id'])) ?>">
                        <span class="tile-icon tint-<?= $forBlocks ? 'blue' : 'neutral' ?>"><?php icon($forBlocks ? 'layers' : 'file'); ?></span>
                        <span><strong><?= e($item['title']) ?></strong><small><?= e($forBlocks ? BLOCK_TYPES[$item['kind']] : $item['slug']) ?></small></span>
                    </a>
                    <div>
                        <?php if ($forBlocks): ?>
                            <?php statusBadge((bool) $item['visible'], 'На сайте', 'Скрыт'); ?>
                        <?php else: ?>
                            <?php statusBadge($item['status'] === 'published', 'Опубликована', 'Черновик'); ?>
                        <?php endif; ?>
                    </div>
                    <?php if ($forBlocks): ?>
                        <form class="row-actions" method="post" action="<?= e(adminUrl('blocks', (int) $item['id'])) ?>">
                            <?php csrfField(); ?>
                            <button class="icon-button" type="submit" name="action" value="up" aria-label="Поднять блок «<?= e($item['title']) ?>»" title="Поднять блок" <?= $index === 0 ? 'disabled' : '' ?>><?php icon('up'); ?></button>
                            <button class="icon-button" type="submit" name="action" value="down" aria-label="Опустить блок «<?= e($item['title']) ?>»" title="Опустить блок" <?= $index === count($items) - 1 ? 'disabled' : '' ?>><?php icon('down'); ?></button>
                        </form>
                    <?php else: ?>
                        <time class="list-date" datetime="<?= e(date('Y-m-d', (int) $item['updated_at'])) ?>"><?= e(dateLabel((int) $item['updated_at'])) ?></time>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
