<?php
require __DIR__ . '/guard.php';
$id = filter_var(query('id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
$active = match ($view) { 'field-group' => 'fields', 'page' => 'pages', default => $view };
$title = ['fields' => 'Поля', 'field-group' => $id ? 'Редактировать группу' : 'Новая группа', 'options' => 'Данные сайта', 'pages' => 'Страницы', 'page' => $id ? 'Редактировать страницу' : 'Новая страница'][$view];
$notice = $_SESSION['notice'] ?? ''; unset($_SESSION['notice']);
$record = [];
if ($view === 'page') {
    $record = $id ? findFieldTarget($db, 'page', $id) : ['title' => '', 'slug' => '', 'body' => '', 'status' => 'draft'];
}
if ($view === 'field-group') {
    $record = ['title' => '', 'scope' => 'page', 'target_id' => 0, 'definition' => '[]'];
    if ($id) {
        $query = $db->prepare('SELECT * FROM field_groups WHERE id = ?'); $query->execute([$id]); $record = $query->fetch();
    }
}
if ($record === false || (array_key_exists('id', $_GET) && !$id)) problem(404, 'Запись не найдена', 'Откройте список заново.', $root);
if ($method === 'POST') {
    try {
        $action = post('action');
        if ($view === 'field-group' && $action === 'save-group') {
            $record = array_replace($record, ['title' => post('title'), 'scope' => post('scope'), 'target_id' => post('target_id'), 'definition' => post('definition')]);
            writeTransaction($db, static fn() => saveFieldGroup($db, $id, $record));
            $_SESSION['notice'] = 'Группа полей сохранена.'; redirect(adminUrl('fields'));
        }
        if ($view === 'field-group' && $id && $action === 'delete-group') {
            writeTransaction($db, static function () use ($db, $id): void {
                $db->prepare('DELETE FROM field_values WHERE group_id = ?')->execute([$id]);
                $db->prepare('DELETE FROM field_groups WHERE id = ?')->execute([$id]);
            });
            $_SESSION['notice'] = 'Группа удалена.'; redirect(adminUrl('fields'));
        }
        if ($view === 'options' && $action === 'save-values') {
            $values = prepareFieldValues($db, 'options', 0, post('field_values'));
            writeTransaction($db, static fn() => storeFieldValues($db, 'options', $values));
            $_SESSION['notice'] = 'Данные сайта сохранены.'; redirect(adminUrl('options'));
        }
        if ($view === 'page' && $action === 'save-page') {
            $home = $id && $record['slug'] === 'home';
            $record = array_replace($record, ['title' => post('title'), 'slug' => $home ? 'home' : post('slug'), 'body' => post('body'), 'status' => $home ? 'published' : post('status')]);
            textField($record['title'], 120, 'Название', true); textField($record['body'], 60000, 'Текст');
            $record['slug'] = $record['slug'] ?: makeSlug($record['title']);
            if (!preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', $record['slug']) || strlen($record['slug']) > 100 || !in_array($record['status'], ['draft', 'published'], true)) throw new ValidationException('Проверьте адрес и статус страницы.');
            $duplicate = $db->prepare('SELECT id FROM pages WHERE slug = ? AND id <> ?'); $duplicate->execute([$record['slug'], $id]);
            if ($duplicate->fetchColumn()) throw new ValidationException('Адрес страницы уже занят.');
            $values = prepareFieldValues($db, 'page', $id, post('field_values'));
            writeTransaction($db, static function () use ($db, $id, $record, $values): void {
                $data = [$record['title'], $record['slug'], $record['body'], $record['status'], time()];
                if ($id) $db->prepare('UPDATE pages SET title = ?, slug = ?, body = ?, status = ?, updated_at = ? WHERE id = ?')->execute([...$data, $id]);
                else $db->prepare('INSERT INTO pages (title, slug, body, status, updated_at) VALUES (?, ?, ?, ?, ?)')->execute($data);
                storeFieldValues($db, 'page:' . ($id ?: (int) $db->lastInsertId()), $values);
            });
            $_SESSION['notice'] = 'Страница сохранена.'; redirect(adminUrl('pages'));
        }
        if ($view === 'page' && $id && $action === 'delete-page' && $record['slug'] !== 'home') {
            writeTransaction($db, static function () use ($db, $id): void {
                $db->prepare('DELETE FROM pages WHERE id = ?')->execute([$id]);
                $db->prepare('DELETE FROM field_values WHERE context = ?')->execute(['page:' . $id]);
            });
            redirect(adminUrl('pages'));
        }
        throw new ValidationException('Действие недоступно.');
    } catch (ValidationException | SetupException $validation) {
        http_response_code(422); $error = $validation->getMessage();
    }
}
require __DIR__ . '/views/admin.php';
