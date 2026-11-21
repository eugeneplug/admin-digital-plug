<?php
require __DIR__ . '/guard.php';

$active = 'record-types';
$id = filter_var(query('id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
$editing = $view === 'record-type';
$title = $editing ? ($id ? 'Редактировать группу записей' : 'Новая группа записей') : 'Группы записей';
$type = $id ? findFieldTarget($db, 'record', $id) : ['title' => '', 'slug' => ''];
if (!$type || (array_key_exists('id', $_GET) && (!$id || !$editing))) {
    problem(404, 'Группа не найдена', 'Откройте список групп записей.', $root);
}
$notice = $_SESSION['notice'] ?? '';
unset($_SESSION['notice']);

if ($method === 'POST') {
    try {
        $action = post('action');
        if ($editing && $action === 'save-type') {
            $type = ['title' => trim(post('title')), 'slug' => trim(post('slug'))];
            textField($type['title'], 120, 'Название группы', true);
            $type['slug'] = $type['slug'] === '' ? makeSlug($type['title']) : $type['slug'];
            if (!preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', $type['slug']) || strlen($type['slug']) > 100) {
                throw new ValidationException('Используйте строчные латинские буквы, цифры и дефисы в ключе группы.');
            }
            writeTransaction($db, static function () use ($db, $id, $type): void {
                $query = $db->prepare('SELECT id FROM record_types WHERE slug = ? AND id <> ?');
                $query->execute([$type['slug'], $id]);
                if ($query->fetchColumn()) throw new ValidationException('Группа с таким ключом уже есть.');
                if ($id) {
                    if (!findFieldTarget($db, 'record', $id)) throw new ValidationException('Эта группа уже удалена.');
                    $db->prepare('UPDATE record_types SET title = ?, slug = ? WHERE id = ?')->execute([$type['title'], $type['slug'], $id]);
                } else {
                    $db->prepare('INSERT INTO record_types (title, slug) VALUES (?, ?)')->execute([$type['title'], $type['slug']]);
                }
            });
            $_SESSION['notice'] = 'Группа записей сохранена.';
            redirect(adminUrl('record-types'));
        }
        if ($editing && $id && $action === 'delete-type') {
            writeTransaction($db, static function () use ($db, $id): void {
                $query = $db->prepare('SELECT id FROM records WHERE type_id = ? LIMIT 1');
                $query->execute([$id]);
                if ($query->fetchColumn() || fieldGroups($db, 'record', $id)) {
                    throw new ValidationException('Чтобы удалить группу, сначала уберите записи и привязанные группы полей.');
                }
                $db->prepare('DELETE FROM record_types WHERE id = ?')->execute([$id]);
            });
            $_SESSION['notice'] = 'Группа записей удалена.';
            redirect(adminUrl('record-types'));
        }
        throw new ValidationException('Действие недоступно.');
    } catch (ValidationException | SetupException $validation) {
        http_response_code(422);
        $error = $validation->getMessage();
    }
}
require __DIR__ . '/views/admin.php';
