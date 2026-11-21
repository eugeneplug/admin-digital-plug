<?php defined('PLUGY_ENTRY') || exit;
template('header', ['title' => $title]); ?>
<main class="site-container">
    <?php template($view, $data); ?>
</main>
<?php template('footer'); ?>
