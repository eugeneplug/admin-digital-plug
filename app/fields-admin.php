<?php
require __DIR__ . '/guard.php';
$id = filter_var(query('id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
$recordMode = in_array($view, ['records', 'record'], true);
$entryEditor = in_array($view, ['page', 'record'], true);
$typeId = 0;
if ($recordMode) {
    $typeId = filter_var(query('type'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
    $recordType = $typeId ? findFieldTarget($db, 'record', $typeId) : false;
    if (!$recordType) problem(404, 'Группа не найдена', 'Откройте группу из меню.', $root);
}
$entryScope = $recordMode ? 'record' : 'page';
$entryTable = $recordMode ? 'records' : 'pages';
$entriesUrl = $recordMode ? recordAdminUrl('records', $typeId) : adminUrl('pages');
$findEntry = static function () use ($db, $entryTable, $recordMode, $id, $typeId): array|false {
    $query = $db->prepare("SELECT * FROM $entryTable WHERE id = ?" . ($recordMode ? ' AND type_id = ?' : ''));
    $query->execute($recordMode ? [$id, $typeId] : [$id]);
    return $query->fetch();
};
$active = match ($view) {
    'field-group' => 'fields', 'page' => 'pages', 'records', 'record' => 'records-' . $typeId, default => $view,
};
$title = match ($view) {
    'fields' => 'Custom Fields',
    'field-group' => $id ? 'Редактировать группу' : 'Новая группа',
    'pages' => 'Страницы',
    'page' => $id ? 'Редактировать страницу' : 'Новая страница',
    'records' => $recordType['title'],
    'record' => $id ? 'Редактировать запись' : 'Новая запись',
};
$notice = $_SESSION['notice'] ?? ''; unset($_SESSION['notice']);
$record = [];
if ($entryEditor) {
    $record = $id ? $findEntry() : ['title' => '', 'slug' => '', 'body' => '', 'status' => 'draft'];
}
if ($view === 'field-group') {
    $record = ['title' => '', 'scope' => 'page', 'target_id' => 0, 'definition' => '[]'];
    if ($id) {
        $query = $db->prepare("SELECT * FROM field_groups WHERE id = ? AND scope IN ('page', 'record')"); $query->execute([$id]); $record = $query->fetch();
    }
}
if ($record === false || (array_key_exists('id', $_GET) && !$id)) problem(404, 'Запись не найдена', 'Откройте список заново.', $root);
if ($method === 'POST') {
    try {
        $action = post('action');
        if ($view === 'field-group' && $action === 'save-group') {
            $record = array_replace($record, ['title' => post('title'), 'definition' => post('definition')]);
            if (!preg_match('/\A(page|record):([0-9]+)\z/', post('location'), $location)) {
                throw new ValidationException('Укажите, где показывать поля.');
            }
            $record['scope'] = $location[1];
            $record['target_id'] = (int) $location[2];
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
        if ($entryEditor && $action === 'save-page') {
            $home = !$recordMode && $id && $record['slug'] === 'home';
            $record = array_replace($record, [
                'title' => trim(post('title')), 'slug' => $home ? 'home' : trim(post('slug')),
                'body' => post('body'), 'status' => $home ? 'published' : post('status'),
            ]);
            textField($record['title'], 120, 'Название', true);
            textField($record['body'], 60000, 'Текст');
            $record['slug'] = $record['slug'] === '' ? makeSlug($record['title']) : $record['slug'];
            if (!preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', $record['slug']) || strlen($record['slug']) > 100
                || !in_array($record['status'], ['draft', 'published'], true)) {
                throw new ValidationException('Проверьте адрес и статус.');
            }
            writeTransaction($db, static function () use ($db, $id, $record, $entryScope, $entryTable, $recordMode, $typeId, $findEntry): void {
                if ($id && !$findEntry()) throw new ValidationException('Эта запись уже удалена.');
                if ($recordMode && !findFieldTarget($db, 'record', $typeId)) {
                    throw new ValidationException('Группа записей уже удалена.');
                }
                $duplicate = $db->prepare("SELECT id FROM $entryTable WHERE slug = ? AND id <> ?");
                $duplicate->execute([$record['slug'], $id]);
                if ($duplicate->fetchColumn()) throw new ValidationException('Этот адрес уже занят.');
                $values = prepareFieldValues($db, $entryScope, $recordMode ? $typeId : $id, post('field_values'));
                $data = [$record['title'], $record['slug'], $record['body'], $record['status'], time()];
                if ($id) {
                    $db->prepare("UPDATE $entryTable SET title = ?, slug = ?, body = ?, status = ?, updated_at = ? WHERE id = ?")->execute([...$data, $id]);
                } elseif ($recordMode) {
                    $db->prepare('INSERT INTO records (title, slug, body, status, updated_at, type_id) VALUES (?, ?, ?, ?, ?, ?)')->execute([...$data, $typeId]);
                } else {
                    $db->prepare('INSERT INTO pages (title, slug, body, status, updated_at) VALUES (?, ?, ?, ?, ?)')->execute($data);
                }
                storeFieldValues($db, $entryScope . ':' . ($id ?: (int) $db->lastInsertId()), $values);
            });
            $_SESSION['notice'] = $recordMode ? 'Запись сохранена.' : 'Страница сохранена.';
            redirect($entriesUrl);
        }
        if ($entryEditor && $id && $action === 'delete-page' && ($recordMode || $record['slug'] !== 'home')) {
            writeTransaction($db, static function () use ($db, $id, $entryScope, $entryTable): void {
                $db->prepare("DELETE FROM $entryTable WHERE id = ?")->execute([$id]);
                $db->prepare('DELETE FROM field_values WHERE context = ?')->execute([$entryScope . ':' . $id]);
            });
            redirect($entriesUrl);
        }
        throw new ValidationException('Действие недоступно.');
    } catch (ValidationException | SetupException $validation) {
        http_response_code(422); $error = $validation->getMessage();
    }
}
require __DIR__ . '/views/admin.php';
