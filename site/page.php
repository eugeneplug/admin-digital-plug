<?php defined('PLUGY_ENTRY') || exit; ?>
<article class="site-article">
    <h1><?= e($page['title']) ?></h1>
    <div class="article-body"><?= e($page['body']) ?></div>
    <?php // Здесь field('ключ') выводит поле текущей страницы. ?>
</article>
