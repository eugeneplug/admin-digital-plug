<?php
declare(strict_types=1);

require dirname(__DIR__) . '/guard.php';

if ($article) {
    ob_start();
    icon('chevron');
    $backIcon = ob_get_clean();
    $published = (int) $article['published_at'];

    echo strtr(readThemeFile('article.html'), [
        '{{back_icon}}' => $backIcon,
        '{{date_iso}}' => e(date('Y-m-d', $published)),
        '{{date}}' => e(dateLabel($published)),
        '{{title}}' => e($article['title']),
        '{{excerpt}}' => e($article['excerpt']),
        '{{hide_excerpt}}' => $article['excerpt'] === '' ? ' hidden' : '',
        '{{body}}' => e($article['body']),
    ]);
    return;
}

$hasPageHeading = false;
$blockMarkup = [];
$blockTemplate = readThemeFile('block.html');
foreach ($blocks as $block) {
    $hero = $block['kind'] === 'hero';
    $heading = $hero && !$hasPageHeading ? 'h1' : 'h2';
    $hasPageHeading = $hasPageHeading || $hero;
    $showLink = $block['link_label'] !== '' && goodLink($block['link_url']);
    $blockMarkup[] = strtr($blockTemplate, [
        '{{kind}}' => e($block['kind']),
        '{{heading}}' => '<' . $heading . '>' . e($block['title']) . '</' . $heading . '>',
        '{{eyebrow}}' => e($block['eyebrow']),
        '{{hide_eyebrow}}' => $block['eyebrow'] === '' ? ' hidden' : '',
        '{{text}}' => e($block['body']),
        '{{hide_text}}' => $block['body'] === '' ? ' hidden' : '',
        '{{button_style}}' => $block['kind'] === 'callout' ? 'button-on-dark' : 'button-primary',
        '{{button_url}}' => $showLink ? e($block['link_url']) : '',
        '{{button_text}}' => e($block['link_label']),
        '{{hide_button}}' => $showLink ? '' : ' hidden',
        '{{hide_art}}' => $hero ? '' : ' hidden',
    ]);
}

$cardMarkup = [];
$cardTemplate = readThemeFile('article-card.html');
foreach ($articles as $index => $item) {
    $published = (int) $item['published_at'];
    $cardMarkup[] = strtr($cardTemplate, [
        '{{url}}' => e(articleUrl($item['slug'])),
        '{{art_variant}}' => (string) ($index % 3),
        '{{date_iso}}' => e(date('Y-m-d', $published)),
        '{{date}}' => e(dateLabel($published)),
        '{{title}}' => e($item['title']),
        '{{excerpt}}' => e($item['excerpt']),
        '{{hide_excerpt}}' => $item['excerpt'] === '' ? ' hidden' : '',
    ]);
}

echo strtr(readThemeFile('home.html'), [
    '{{blocks}}' => implode("\n", $blockMarkup),
    '{{journal_heading}}' => $hasPageHeading ? '<h2>Журнал</h2>' : '<h1>Журнал</h1>',
    '{{cards}}' => implode("\n", $cardMarkup),
    '{{hide_empty}}' => $articles ? ' hidden' : '',
    '{{hide_cards}}' => $articles ? '' : ' hidden',
]);
