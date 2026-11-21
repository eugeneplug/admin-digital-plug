<?php
declare(strict_types=1);

$root = '../';
$app = dirname(__DIR__) . '/app';
require $app . '/bootstrap.php';
securityHeaders(admin: true);

$path = explode('?', $_SERVER['REQUEST_URI'], 2)[0];
if (str_ends_with($path, '/admin')) {
    $search = http_build_query($_GET, '', '&', PHP_QUERY_RFC3986);
    redirect('admin/' . ($search === '' ? '' : '?' . $search), 308);
}

if (($_SERVER['PATH_INFO'] ?? '') !== '') {
    $siteRoot = rtrim(dirname($_SERVER['SCRIPT_NAME'], 2), '/') . '/';
    problem(404, 'Страница не найдена', 'Проверьте адрес или вернитесь на главную.', $siteRoot);
}

$db = openSite($root);
try {
    startAdminSession();
} catch (SetupException $error) {
    problem(503, 'Сессии ещё не настроены', $error->getMessage(), $root);
}
$account = adminAccount($db);
$signedIn = isAuthenticated($account);
$method = $_SERVER['REQUEST_METHOD'];
$login = query('view') === 'login';
$username = '';
$error = '';

if ($method === 'POST' && !hash_equals(csrfToken(), post('csrf'))) {
    problem(403, 'Форма устарела', 'Откройте страницу заново и повторите действие.', $root);
}

if (!$signedIn) {
    if (!$login) {
        redirect(adminUrl('login'));
    }
    $installed = isset($_SESSION['installation_complete']);
    unset($_SESSION['installation_complete']);
    $demoBlocked = (bool) $account['demo'] && !isLocalDevelopment();
    if ($method === 'POST') {
        $username = substr(trim(post('username')), 0, 100);
        if ($demoBlocked) {
            http_response_code(403);
        } elseif (authenticate($db, $account, $username, post('password'))) {
            redirect(adminUrl());
        } else {
            $error = http_response_code() === 429
                ? 'Слишком много попыток. Попробуйте позже.'
                : 'Проверьте логин и пароль.';
        }
    }
    require $app . '/views/login.php';
    exit;
}

if ($login) {
    redirect(adminUrl());
}

if ($method === 'POST' && post('action') === 'upload-image') {
    header('Content-Type: application/json; charset=UTF-8');
    try {
        echo json_encode(['url' => uploadFieldImage($_FILES['image'] ?? [])], JSON_THROW_ON_ERROR);
    } catch (ValidationException | SetupException $validation) {
        http_response_code(422); echo json_encode(['error' => $validation->getMessage()], JSON_THROW_ON_ERROR);
    }
    exit;
}

$view = query('view') ?: 'dashboard';
if (in_array($view, ['fields', 'field-group', 'options', 'pages', 'page'], true)) {
    require $app . '/fields-admin.php';
    exit;
}
$pages = [
    'dashboard' => 'Обзор',
    'blocks' => 'Блоки сайта',
    'block' => 'Редактор блока',
    'articles' => 'Статьи',
    'article' => 'Редактор статьи',
    'code' => 'Редактор кода',
    'settings' => 'Настройки',
];
if (!isset($pages[$view])) {
    problem(404, 'Страница не найдена', 'Вернитесь на главную страницу.', $root);
}

if ($view === 'code') {
    $themeFile = query('file') ?: 'home.php';
    if (!isset(themeFiles()[$themeFile])) {
        problem(404, 'Файл не найден', 'Выберите файл из списка в редакторе кода.', $root);
    }
    $themeSource = readThemeFile($themeFile);
    $themeRevision = hash('sha256', $themeSource);
}

$id = filter_var(query('id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
if (array_key_exists('id', $_GET) && !$id) {
    problem(404, 'Запись не найдена', 'Проверьте адрес страницы.', $root);
}

$notice = $_SESSION['notice'] ?? '';
unset($_SESSION['notice']);
$record = [];
$savedSlug = '';
$editing = in_array($view, ['block', 'article'], true);
if ($editing) {
    $record = $id ? findContent($db, $view, $id) : match ($view) {
        'block' => ['kind' => 'text', 'eyebrow' => '', 'title' => '', 'body' => '', 'link_label' => '', 'link_url' => '', 'visible' => 1],
        'article' => ['title' => '', 'slug' => '', 'excerpt' => '', 'body' => '', 'status' => 'draft'],
    };
    if ($record === false) {
        problem(404, 'Запись не найдена', 'Возможно, запись уже удалена.', $root);
    }
    $savedSlug = $record['slug'] ?? '';
}

if ($method === 'POST') {
    $action = post('action');
    try {
        if ($view === 'code' && $action === 'create-component') {
            redirect(themeEditorUrl(createComponent(trim(post('component_name')))));
        }
        if ($view === 'code' && $action === 'save-code' && is_string($_POST['source'] ?? null)) {
            $themeSource = post('source');
            $themeRevision = post('revision');
            saveThemeFile($themeFile, $themeSource, $themeRevision);
            $_SESSION['notice'] = 'Файл «' . $themeFile . '» сохранён. Изменения опубликованы.';
            redirect(themeEditorUrl($themeFile));
        }
        if ($action === 'logout' && $view === 'dashboard') {
            endAdminSession();
            redirect(adminUrl('login'));
        }
        if ($action === 'password' && $view === 'settings') {
            changeAdminPassword($db, $account);
            redirect(adminUrl('settings'));
        }
        if ($action === 'save' && $editing) {
            $record = array_replace($record, editorData($view));
            if ($view === 'block') {
                saveBlock($db, $id, $record);
                $_SESSION['notice'] = 'Блок сохранён.';
                redirect(adminUrl('blocks'));
            }
            $customValues = prepareFieldValues($db, 'article', $id, post('field_values'));
            writeTransaction($db, static function () use ($db, $id, $record, $customValues): void {
                $savedId = saveArticle($db, $id, $record);
                storeFieldValues($db, 'article:' . $savedId, $customValues);
            });
            $_SESSION['notice'] = $record['status'] === 'published' ? 'Статья опубликована.' : 'Черновик сохранён.';
            redirect(adminUrl('articles'));
        }
        if ($action === 'delete' && $editing && $id) {
            deleteContent($db, $view, $id);
            $_SESSION['notice'] = $view === 'block' ? 'Блок удалён.' : 'Статья удалена.';
            redirect(adminUrl($view === 'block' ? 'blocks' : 'articles'));
        }
        if (in_array($action, ['up', 'down'], true) && $view === 'blocks' && $id) {
            moveBlock($db, $id, $action === 'up' ? -1 : 1);
            redirect(adminUrl('blocks'));
        }
        problem(400, 'Действие недоступно', 'Вернитесь назад и обновите страницу.', $root);
    } catch (ValidationException | SetupException $validation) {
        http_response_code(422);
        $error = $validation->getMessage();
    }
}

$active = match ($view) {
    'block' => 'blocks',
    'article' => 'articles',
    default => $view,
};
$title = $editing ? match ($view) {
    'block' => $id ? 'Редактировать блок' : 'Новый блок',
    'article' => $id ? 'Редактировать статью' : 'Новая статья',
} : $pages[$view];

if ($view === 'dashboard' || $view === 'blocks') {
    $blocks = siteBlocks($db);
}
if ($view === 'dashboard' || $view === 'articles') {
    $articles = articles($db);
}
require $app . '/views/admin.php';
