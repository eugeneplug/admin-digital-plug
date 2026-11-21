<?php defined('PLUGY_ENTRY') || exit; ?>
<section class="site-block site-block-<?= e($block['kind']) ?>">
    <div class="site-block-content">
        <?php if ($block['eyebrow'] !== ''): ?><p class="site-eyebrow"><?= e($block['eyebrow']) ?></p><?php endif; ?>
        <?php if ($heading === 'h1'): ?><h1><?= e($block['title']) ?></h1><?php else: ?><h2><?= e($block['title']) ?></h2><?php endif; ?>
        <?php if ($block['body'] !== ''): ?><p class="block-text"><?= e($block['body']) ?></p><?php endif; ?>
        <?php if ($block['link_label'] !== '' && goodLink($block['link_url'])): ?>
            <a class="button <?= $block['kind'] === 'callout' ? 'button-on-dark' : 'button-primary' ?>" href="<?= e($block['link_url']) ?>"><?= e($block['link_label']) ?></a>
        <?php endif; ?>
    </div>
    <?php if ($block['kind'] === 'hero'): ?><div class="site-hero-art" aria-hidden="true"><i></i><i></i><i></i></div><?php endif; ?>
</section>
