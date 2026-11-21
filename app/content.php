<?php
declare(strict_types=1);

const BLOCK_TYPES = ['hero' => 'Первый экран', 'text' => 'Текстовая секция', 'callout' => 'Акцентный блок'];

function siteBlocks(PDO $db, bool $visibleOnly = false): array
{
    return $db->query('SELECT * FROM blocks' . ($visibleOnly ? ' WHERE visible = 1' : '') . ' ORDER BY position, id')->fetchAll();
}

function articles(PDO $db, bool $publishedOnly = false): array
{
    $sql = $publishedOnly
        ? "SELECT * FROM articles WHERE status = 'published' ORDER BY published_at DESC, id DESC"
        : 'SELECT * FROM articles ORDER BY updated_at DESC, id DESC';
    return $db->query($sql)->fetchAll();
}

function publishedArticle(PDO $db, string $slug): array|false
{
    $read = $db->prepare("SELECT * FROM articles WHERE slug = ? AND status = 'published'");
    $read->execute([$slug]);
    return $read->fetch();
}

function findContent(PDO $db, string $type, int $id): array|false
{
    $sql = $type === 'block' ? 'SELECT * FROM blocks WHERE id = ?' : 'SELECT * FROM articles WHERE id = ?';
    $read = $db->prepare($sql);
    $read->execute([$id]);
    return $read->fetch();
}

function textField(string $value, int $limit, string $label, bool $required = false): string
{
    if ($required && $value === '') {
        throw new ValidationException('Заполните поле «' . $label . '».');
    }
    if (str_contains($value, "\0") || preg_match('/\A.{0,' . $limit . '}\z/su', $value) !== 1) {
        throw new ValidationException('Проверьте поле «' . $label . '». Допустимо до ' . $limit . ' символов.');
    }
    return $value;
}

function editorData(string $type): array
{
    return $type === 'block'
        ? [
            'kind' => post('kind'),
            'eyebrow' => trim(post('eyebrow')),
            'title' => trim(post('title')),
            'body' => trim(post('body')),
            'link_label' => trim(post('link_label')),
            'link_url' => trim(post('link_url')),
            'visible' => (int) (post('visible') === '1'),
        ]
        : [
            'title' => trim(post('title')),
            'slug' => trim(post('slug')),
            'excerpt' => trim(post('excerpt')),
            'body' => trim(post('body')),
            'status' => post('status'),
        ];
}

function goodLink(string $url): bool
{
    if (preg_match('/[\x00-\x20\x7F\\\\]/', $url) !== 0) {
        return false;
    }
    if (str_starts_with($url, '#') || (str_starts_with($url, '/') && !str_starts_with($url, '//'))) {
        return true;
    }
    return str_starts_with($url, 'https://') && filter_var($url, FILTER_VALIDATE_URL) !== false
        && parse_url($url, PHP_URL_USER) === null;
}

function saveBlock(PDO $db, int $id, array $data): void
{
    $kind = $data['kind'];
    if (!isset(BLOCK_TYPES[$kind])) {
        throw new ValidationException('Выберите тип блока.');
    }
    $title = textField($data['title'], 120, 'Заголовок', true);
    $eyebrow = textField($data['eyebrow'], 60, 'Надзаголовок');
    $body = textField($data['body'], 6000, 'Текст');
    $label = $kind === 'text' ? '' : textField($data['link_label'], 40, 'Текст кнопки');
    $url = $kind === 'text' ? '' : textField($data['link_url'], 400, 'Ссылка');
    if (($label !== '' || $url !== '') && ($label === '' || !goodLink($url))) {
        throw new ValidationException('Укажите текст кнопки и корректную ссылку, например #journal, /страница или https://example.com.');
    }

    $values = [$kind, $eyebrow, $title, $body, $label, $url, $data['visible'], time()];
    if ($id) {
        $save = $db->prepare('UPDATE blocks SET kind = ?, eyebrow = ?, title = ?, body = ?, link_label = ?,
            link_url = ?, visible = ?, updated_at = ? WHERE id = ?');
        $save->execute([...$values, $id]);
        if ($save->rowCount() !== 1 && !findContent($db, 'block', $id)) {
            throw new ValidationException('Этот блок уже удалён.');
        }
        return;
    }
    writeTransaction($db, static function () use ($db, $values): void {
        $position = (int) $db->query('SELECT COALESCE(MAX(position), 0) + 1 FROM blocks')->fetchColumn();
        $db->prepare('INSERT INTO blocks (kind, eyebrow, title, body, link_label, link_url, visible, updated_at, position)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute([...$values, $position]);
    });
}

function makeSlug(string $title): string
{
    $alphabet = 'абвгдеёжзийклмнопрстуфхцчшщъыьэюяАБВГДЕЁЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯ';
    $latin = ['a','b','v','g','d','e','e','zh','z','i','y','k','l','m','n','o','p','r','s','t','u','f','h','ts','ch','sh','sch','','y','','e','yu','ya'];
    $map = array_combine(preg_split('//u', $alphabet, -1, PREG_SPLIT_NO_EMPTY), [...$latin, ...$latin]);
    $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower(strtr($title, $map)));
    return trim(substr($slug, 0, 100), '-') ?: 'article-' . bin2hex(random_bytes(5));
}

function saveArticle(PDO $db, int $id, array $data): void
{
    $title = textField($data['title'], 120, 'Заголовок', true);
    $excerpt = textField($data['excerpt'], 280, 'Анонс');
    $body = textField($data['body'], 60000, 'Текст статьи');
    $slug = $data['slug'] === '' ? makeSlug($title) : strtolower($data['slug']);
    if (strlen($slug) > 100 || preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', $slug) !== 1) {
        throw new ValidationException('В адресе статьи используйте латинские буквы, цифры и дефисы.');
    }
    $status = $data['status'];
    if (!in_array($status, ['draft', 'published'], true)) {
        throw new ValidationException('Выберите статус статьи.');
    }
    if ($status === 'published' && $body === '') {
        throw new ValidationException('Перед публикацией добавьте текст статьи.');
    }

    $now = time();
    $publishedAt = $status === 'published' ? $now : null;
    try {
        if ($id) {
            $save = $db->prepare('UPDATE articles SET title = ?, slug = ?, excerpt = ?, body = ?, status = ?,
                published_at = CASE WHEN ? IS NULL THEN NULL ELSE COALESCE(published_at, ?) END,
                updated_at = ? WHERE id = ?');
            $save->execute([$title, $slug, $excerpt, $body, $status, $publishedAt, $publishedAt, $now, $id]);
            if ($save->rowCount() !== 1 && !findContent($db, 'article', $id)) {
                throw new ValidationException('Эта статья уже удалена.');
            }
            return;
        }
        $db->prepare('INSERT INTO articles (title, slug, excerpt, body, status, published_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?)')->execute([$title, $slug, $excerpt, $body, $status, $publishedAt, $now]);
    } catch (PDOException $error) {
        if ((int) ($error->errorInfo[1] ?? 0) === 1062
            || str_contains($error->getMessage(), 'UNIQUE constraint failed: articles.slug')) {
            throw new ValidationException('Этот адрес уже занят. Укажите другой адрес статьи.');
        }
        throw $error;
    }
}

function deleteContent(PDO $db, string $type, int $id): void
{
    $sql = $type === 'block' ? 'DELETE FROM blocks WHERE id = ?' : 'DELETE FROM articles WHERE id = ?';
    $db->prepare($sql)->execute([$id]);
}

function moveBlock(PDO $db, int $id, int $direction): void
{
    writeTransaction($db, static function () use ($db, $id, $direction): void {
        $blocks = siteBlocks($db);
        $index = array_search($id, array_map('intval', array_column($blocks, 'id')), true);
        if ($index === false || !isset($blocks[$index + $direction])) {
            return;
        }
        $neighbor = $blocks[$index + $direction];
        $change = $db->prepare('UPDATE blocks SET position = ? WHERE id = ?');
        $change->execute([$neighbor['position'], $id]);
        $change->execute([$blocks[$index]['position'], $neighbor['id']]);
    });
}
