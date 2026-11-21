<?php
require dirname(__DIR__) . '/guard.php';

?>
<div class="page-heading">
    <div><h1>Обзор</h1><p>Редактируйте страницы и PHP-шаблоны.</p></div>
    <a class="button button-primary" href="<?= e(adminUrl('page')) ?>"><?php icon('plus'); ?> Новая страница</a>
</div>
<section class="welcome">
    <div class="welcome-copy">
        <h2>Ваш сайт под рукой</h2>
        <p>В редакторе кода можно изменить главную, журнал и компоненты сайта.</p>
        <a class="button button-on-dark" href="<?= e(themeEditorUrl('content.php')) ?>"><?php icon('code'); ?> Редактировать тексты</a>
    </div>
    <div class="welcome-art" aria-hidden="true">
        <span class="art-orbit"></span><span class="art-tile art-tile-bottom"></span><span class="art-tile art-tile-middle"></span>
        <span class="art-tile art-tile-top"></span>
        <span class="art-spark"></span><span class="art-spark art-spark-two"></span>
    </div>
</section>
<div class="dashboard-bottom">
    <section class="panel quick">
        <div class="panel-heading"><h2>Быстрые действия</h2></div>
        <div class="quick-list">
            <a href="<?= e(adminUrl('fields')) ?>"><span class="tile-icon tint-blue"><?php icon('layers'); ?></span><strong>Поля страниц</strong><?php icon('chevron', 'row-chevron'); ?></a>
            <a href="../" target="_blank" rel="noopener"><span class="tile-icon tint-violet"><?php icon('globe'); ?></span><strong>Посмотреть сайт</strong><?php icon('external', 'row-chevron'); ?></a>
            <a href="<?= e(adminUrl('settings')) ?>"><span class="tile-icon tint-neutral"><?php icon('shield'); ?></span><strong>Безопасность</strong><?php icon('chevron', 'row-chevron'); ?></a>
        </div>
    </section>
</div>
