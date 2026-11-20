<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $article ? e($article['title']) . ' · ' : '' ?>Студия Пиксель</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="site-page">
    <header class="site-header">
        <div class="site-container site-nav">
            <a class="site-brand" href="./"><span class="pixel-mark" aria-hidden="true"></span>пиксель<span>.</span></a>
            <nav aria-label="Навигация сайта">
                <a href="./#journal">Журнал</a>
                <a class="site-admin-link" href="admin/"><?php icon('lock'); ?> Админка</a>
            </nav>
        </div>
    </header>
    <main class="site-container">
        <?php if ($article): ?>
            <article class="site-article">
                <a class="text-link back-link" href="./#journal"><?php icon('chevron'); ?> К журналу</a>
                <p class="site-eyebrow"><time datetime="<?= e(date('Y-m-d', (int) $article['published_at'])) ?>"><?= e(dateLabel((int) $article['published_at'])) ?></time></p>
                <h1><?= e($article['title']) ?></h1>
                <?php if ($article['excerpt'] !== ''): ?><p class="article-lead"><?= e($article['excerpt']) ?></p><?php endif; ?>
                <div class="article-body"><?= e($article['body']) ?></div>
            </article>
        <?php else: ?>
            <?php $firstHero = true; ?>
            <?php foreach ($blocks as $block): ?>
                <section class="site-block site-block-<?= e($block['kind']) ?>">
                    <div class="site-block-content">
                        <?php if ($block['eyebrow'] !== ''): ?><p class="site-eyebrow"><?= e($block['eyebrow']) ?></p><?php endif; ?>
                        <?php if ($block['kind'] === 'hero' && $firstHero): ?>
                            <h1><?= e($block['title']) ?></h1>
                            <?php $firstHero = false; ?>
                        <?php else: ?>
                            <h2><?= e($block['title']) ?></h2>
                        <?php endif; ?>
                        <?php if ($block['body'] !== ''): ?><p class="block-text"><?= e($block['body']) ?></p><?php endif; ?>
                        <?php if ($block['link_label'] !== '' && goodLink($block['link_url'])): ?>
                            <a class="button <?= $block['kind'] === 'callout' ? 'button-on-dark' : 'button-primary' ?>" href="<?= e($block['link_url']) ?>"><?= e($block['link_label']) ?></a>
                        <?php endif; ?>
                    </div>
                    <?php if ($block['kind'] === 'hero'): ?>
                        <div class="site-hero-art" aria-hidden="true"><i></i><i></i><i></i><b class="pixel-mark"></b></div>
                    <?php endif; ?>
                </section>
            <?php endforeach; ?>
            <section class="journal" id="journal">
                <div class="journal-heading">
                    <?php if ($firstHero): ?><h1>Журнал</h1><?php else: ?><h2>Журнал</h2><?php endif; ?>
                    <p>Заметки о дизайне и работе над сайтами.</p>
                </div>
                <?php if (!$articles): ?>
                    <p class="empty-compact">Здесь появятся опубликованные статьи.</p>
                <?php else: ?>
                    <div class="journal-grid">
                        <?php foreach ($articles as $index => $item): ?>
                            <article class="journal-card">
                                <a href="<?= e(articleUrl($item['slug'])) ?>">
                                    <span class="journal-art journal-art-<?= $index % 3 ?>" aria-hidden="true"><i></i><i></i><i></i></span>
                                    <div class="journal-copy">
                                        <time datetime="<?= e(date('Y-m-d', (int) $item['published_at'])) ?>"><?= e(dateLabel((int) $item['published_at'])) ?></time>
                                        <h3><?= e($item['title']) ?></h3>
                                        <?php if ($item['excerpt'] !== ''): ?><span><?= e($item['excerpt']) ?></span><?php endif; ?>
                                    </div>
                                </a>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </main>
    <footer class="site-container site-footer">
        <a class="site-brand" href="./"><span class="pixel-mark" aria-hidden="true"></span>пиксель<span>.</span></a>
        <span>Демо-сайт на Plugy Engine</span>
    </footer>
</body>
</html>
