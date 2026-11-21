<?php defined('PLUGY_ENTRY') || exit;
if (!$rows) return; ?>
<section class="journal-grid" aria-label="Преимущества">
    <?php foreach ($rows as $row): ?>
        <article class="journal-card journal-copy">
            <h2><?= e($row['title']) ?></h2>
            <p><?= e($row['description']) ?></p>
        </article>
    <?php endforeach; ?>
</section>
