<?php
declare(strict_types=1);

const FIELD_TYPES = ['text' => 'Текст', 'textarea' => 'Большой текст', 'image' => 'Изображение', 'url' => 'Ссылка', 'number' => 'Число', 'boolean' => 'Переключатель', 'repeater' => 'Повторитель'];
const FIELD_SCOPES = ['options' => 'Весь сайт', 'page' => 'Страницы', 'article' => 'Статьи'];

function migrateFields(PDO $db): void
{
    $version = usesMysql($db) ? mysqlSchemaVersion($db) : (int) $db->query('PRAGMA user_version')->fetchColumn();
    if ($version >= 2) return;
    $migrate = static function () use ($db): void {
        $id = usesMysql($db) ? 'INT PRIMARY KEY AUTO_INCREMENT' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $tail = usesMysql($db) ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
        $db->exec("CREATE TABLE IF NOT EXISTS pages (id $id, title VARCHAR(120) NOT NULL, slug VARCHAR(100) NOT NULL UNIQUE, body TEXT NOT NULL, status VARCHAR(12) NOT NULL, updated_at BIGINT NOT NULL)" . $tail);
        $db->exec("CREATE TABLE IF NOT EXISTS field_groups (id $id, title VARCHAR(120) NOT NULL, scope VARCHAR(12) NOT NULL, target_id INT NOT NULL DEFAULT 0, definition TEXT NOT NULL)" . $tail);
        $db->exec('CREATE TABLE IF NOT EXISTS field_values (group_id INT NOT NULL, context VARCHAR(60) NOT NULL, payload TEXT NOT NULL, PRIMARY KEY (group_id, context))' . $tail);
        writeTransaction($db, static function () use ($db): void {
            if (!$db->query("SELECT id FROM pages WHERE slug = 'home'")->fetchColumn()) {
                $db->prepare("INSERT INTO pages (title, slug, body, status, updated_at) VALUES (?, 'home', '', 'published', ?)")->execute(['Главная', time()]);
            }
            // A starter group demonstrates real fields without changing existing content.
            if (!(int) $db->query('SELECT COUNT(*) FROM field_groups')->fetchColumn()) {
                $definition = [['key' => 'footer_text', 'label' => 'Подпись в подвале', 'type' => 'text', 'required' => false],
                    ['key' => 'benefits', 'label' => 'Преимущества', 'type' => 'repeater', 'required' => false, 'children' => [
                        ['key' => 'title', 'label' => 'Заголовок', 'type' => 'text', 'required' => true],
                        ['key' => 'description', 'label' => 'Описание', 'type' => 'textarea', 'required' => false],
                    ]]];
                $db->prepare('INSERT INTO field_groups (title, scope, target_id, definition) VALUES (?, ?, 0, ?)')->execute(['Сайт', 'options', json_encode($definition, JSON_THROW_ON_ERROR)]);
            }
            $db->exec(usesMysql($db) ? 'UPDATE plugy_installation SET version = 2 WHERE id = 1' : 'PRAGMA user_version = 2');
        });
    };
    // MySQL limits GET_LOCK names to 64 characters, including the database hash.
    if (usesMysql($db)) withMysqlLock($db, 'fields', $migrate);
    else $migrate();
}

function fieldGroups(PDO $db, ?string $scope = null, int $id = 0): array
{
    $query = $db->prepare('SELECT * FROM field_groups' . ($scope === null ? '' : ' WHERE scope = ? AND (target_id = 0 OR target_id = ?)') . ' ORDER BY id');
    $query->execute($scope === null ? [] : [$scope, $id]);
    return array_map(static function ($group) {
        $group['fields'] = json_decode($group['definition'], true, 32, JSON_THROW_ON_ERROR);
        return $group;
    }, $query->fetchAll());
}

function fieldJson(string $json): array
{
    if (strlen($json) > 60000) throw new ValidationException('Слишком много данных полей (максимум 60 КБ).');
    try { $data = json_decode($json, true, 16, JSON_THROW_ON_ERROR); }
    catch (JsonException) { throw new ValidationException('Не удалось прочитать поля. Обновите страницу.'); }
    if (!is_array($data)) throw new ValidationException('Некорректные данные полей.');
    return $data;
}

function validateDefinitions(array $fields, int $depth = 0): array
{
    if (!$fields || count($fields) > 30 || !array_is_list($fields)) throw new ValidationException('Добавьте от 1 до 30 полей.');
    $result = []; $keys = [];
    foreach ($fields as $field) {
        if (!is_array($field)) throw new ValidationException('Некорректное поле.');
        $key = is_string($field['key'] ?? null) ? $field['key'] : '';
        $type = is_string($field['type'] ?? null) ? $field['type'] : '';
        if (!preg_match('/\A[a-z][a-z0-9_]{0,49}\z/', $key) || isset($keys[$key])) throw new ValidationException('Ключи полей должны быть уникальны: латинские буквы, цифры и подчёркивание.');
        if (!isset(FIELD_TYPES[$type]) || ($depth > 0 && $type === 'repeater')) throw new ValidationException('Вложенные повторители пока не поддерживаются.');
        $keys[$key] = true;
        $item = ['key' => $key, 'type' => $type, 'label' => textField(is_string($field['label'] ?? null) ? trim($field['label']) : '', 100, 'Название поля', true), 'required' => !empty($field['required'])];
        if ($type === 'repeater') $item['children'] = validateDefinitions(is_array($field['children'] ?? null) ? $field['children'] : [], $depth + 1);
        $result[] = $item;
    }
    return $result;
}

function saveFieldGroup(PDO $db, int $id, array $data): void
{
    $title = textField(trim($data['title']), 120, 'Название группы', true);
    $scope = $data['scope']; $target = (int) $data['target_id'];
    if (!isset(FIELD_SCOPES[$scope]) || $target < 0 || ($scope === 'options' && $target !== 0)) throw new ValidationException('Выберите область полей.');
    if ($target && !findFieldTarget($db, $scope, $target)) throw new ValidationException('Выбранная запись не найдена.');
    $fields = validateDefinitions(fieldJson($data['definition']));
    foreach (fieldGroups($db) as $other) {
        if ((int) $other['id'] === $id || $other['scope'] !== $scope) continue;
        if ($target && (int) $other['target_id'] && (int) $other['target_id'] !== $target) continue;
        if (array_intersect(array_column($fields, 'key'), array_column($other['fields'], 'key'))) throw new ValidationException('Такой ключ уже есть в другой группе этой области.');
    }
    $encoded = json_encode($fields, JSON_THROW_ON_ERROR);
    if (strlen($encoded) > 60000) throw new ValidationException('Группа слишком большая.');
    if ($id) {
        $db->prepare('UPDATE field_groups SET title = ?, scope = ?, target_id = ?, definition = ? WHERE id = ?')->execute([$title, $scope, $target, $encoded, $id]);
    } else {
        $db->prepare('INSERT INTO field_groups (title, scope, target_id, definition) VALUES (?, ?, ?, ?)')->execute([$title, $scope, $target, $encoded]);
    }
}

function findFieldTarget(PDO $db, string $scope, int $id): array|false
{
    $table = match ($scope) { 'page' => 'pages', 'article' => 'articles', default => throw new ValidationException('Некорректная область.') };
    $query = $db->prepare("SELECT * FROM $table WHERE id = ?"); $query->execute([$id]); return $query->fetch();
}

function validateFieldValues(array $definitions, array $input): array
{
    $values = [];
    foreach ($definitions as $field) {
        $value = $input[$field['key']] ?? null;
        if ($field['type'] === 'repeater') {
            $value ??= [];
            if (!is_array($value) || !array_is_list($value) || count($value) > 50) throw new ValidationException('В повторителе допустимо до 50 строк.');
            $rows = [];
            foreach ($value as $row) {
                if (!is_array($row)) throw new ValidationException('Некорректная строка повторителя.');
                $rows[] = validateFieldValues($field['children'], $row);
            }
            $value = $rows;
        } elseif ($field['type'] === 'boolean') {
            if (!in_array($value, [true, false, null], true)) throw new ValidationException('Некорректный переключатель.');
            $value = (bool) $value;
        } else {
            $value ??= '';
            if (!is_string($value) && !is_numeric($value)) throw new ValidationException('Некорректное значение «' . $field['label'] . '».');
            $value = textField((string) $value, $field['type'] === 'textarea' ? 10000 : 2000, $field['label']);
            if (in_array($field['type'], ['url', 'image'], true) && $value !== '' && !fieldUrl($value)) throw new ValidationException('Укажите корректный URL в «' . $field['label'] . '».');
            if ($field['type'] === 'number' && $value !== '') {
                if (!is_numeric($value) || !is_finite((float) $value)) throw new ValidationException('Введите число в «' . $field['label'] . '».');
                $value = (float) $value;
            }
        }
        if ($field['required'] && ($value === '' || $value === null || $value === [] || $value === false)) throw new ValidationException('Заполните «' . $field['label'] . '».');
        $values[$field['key']] = $value;
    }
    return $values;
}

function fieldUrl(string $url): bool
{
    return goodLink($url) || (str_starts_with($url, 'http://') && goodLink('https://' . substr($url, 7)));
}

function prepareFieldValues(PDO $db, string $scope, int $id, string $payload): array
{
    $input = fieldJson($payload); $result = [];
    foreach (fieldGroups($db, $scope, $id) as $group) {
        $values = $input[$group['id']] ?? [];
        if (!is_array($values)) throw new ValidationException('Некорректные значения группы.');
        $result[$group['id']] = validateFieldValues($group['fields'], $values);
    }
    return $result;
}

function storeFieldValues(PDO $db, string $context, array $groups): void
{
    foreach ($groups as $id => $values) {
        $json = json_encode($values, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        if (strlen($json) > 60000) throw new ValidationException('Значения группы превышают 60 КБ.');
        $db->prepare('DELETE FROM field_values WHERE group_id = ? AND context = ?')->execute([$id, $context]);
        $db->prepare('INSERT INTO field_values (group_id, context, payload) VALUES (?, ?, ?)')->execute([$id, $context, $json]);
    }
}

function loadFieldValues(PDO $db, string $context): array
{
    $query = $db->prepare('SELECT group_id, payload FROM field_values WHERE context = ?'); $query->execute([$context]); $values = [];
    foreach ($query->fetchAll() as $row) $values[$row['group_id']] = json_decode($row['payload'], true, 16, JSON_THROW_ON_ERROR);
    return $values;
}

function fieldContext(string $context): void { $GLOBALS['plugy_field_context'] = $context; }

function get_field(string $key, string|int|null $context = null, mixed $default = null): mixed
{
    $context = is_int($context) ? 'article:' . $context : ($context ?? ($GLOBALS['plugy_field_context'] ?? 'options'));
    $context = $context === 'option' ? 'options' : $context;
    $db = $GLOBALS['db'];
    [$scope, $id] = array_pad(explode(':', $context, 2), 2, '0');
    $values = loadFieldValues($db, $context);
    foreach (fieldGroups($db, $scope, (int) $id) as $group) {
        foreach ($group['fields'] as $field) {
            if ($field['key'] === $key) return $values[$group['id']][$key] ?? ($field['type'] === 'repeater' ? [] : $default);
        }
    }
    return $default;
}

function field(string $key, string|int|null $context = null, mixed $default = ''): void
{
    $value = get_field($key, $context, $default);
    if (is_scalar($value) || $value === null) echo e((string) $value);
}

function fieldValuesForm(PDO $db, string $scope, int $id): void
{
    $context = $scope === 'options' ? 'options' : "$scope:$id";
    $values = $_SERVER['REQUEST_METHOD'] === 'POST' && is_string($_POST['field_values'] ?? null) ? post('field_values') : json_encode(loadFieldValues($db, $context), JSON_THROW_ON_ERROR);
    echo '<div class="custom-fields" data-field-values data-groups="' . e(json_encode(fieldGroups($db, $scope, $id), JSON_THROW_ON_ERROR)) . '">';
    echo '<input type="hidden" name="field_values" value="' . e($values) . '"><noscript>Для редактирования полей включите JavaScript.</noscript></div>';
}
