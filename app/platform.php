<?php
if (PHP_VERSION_ID < 80300) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('Plugy Engine требует PHP 8.3 или новее. Выберите версию PHP в панели хостинга.');
}
