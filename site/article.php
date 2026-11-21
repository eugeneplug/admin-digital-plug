<?php defined('PLUGY_ENTRY') || exit; ?>
<article class="site-article">
    <a class="text-link back-link" href="./#journal"><?php icon('chevron'); ?> К журналу</a>
    <p class="site-eyebrow"><time datetime="<?= date('Y-m-d', (int) $article['published_at']) ?>"><?= e(dateLabel((int) $article['published_at'])) ?></time></p>
    <h1><?= e($article['title']) ?></h1>
    <?php if ($article['excerpt'] !== ''): ?><p class="article-lead"><?= e($article['excerpt']) ?></p><?php endif; ?>
    <div class="article-body"><?= e($article['body']) ?></div>
</article>
