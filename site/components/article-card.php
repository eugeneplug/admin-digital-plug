<?php defined('PLUGY_ENTRY') || exit; ?>
<article class="journal-card">
    <a href="<?= e(articleUrl($item['slug'])) ?>">
        <span class="journal-art journal-art-<?= $index % 3 ?>" aria-hidden="true"><i></i><i></i><i></i></span>
        <div class="journal-copy">
            <time datetime="<?= date('Y-m-d', (int) $item['published_at']) ?>"><?= e(dateLabel((int) $item['published_at'])) ?></time>
            <h3><?= e($item['title']) ?></h3>
            <?php if ($item['excerpt'] !== ''): ?><span><?= e($item['excerpt']) ?></span><?php endif; ?>
        </div>
    </a>
</article>
