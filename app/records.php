<?php
declare(strict_types=1);

function migrateRecordTypes(PDO $db): void
{
    $mysql = usesMysql($db);
    $version = static fn(): int => $mysql ? mysqlSchemaVersion($db) : (int) $db->query('PRAGMA user_version')->fetchColumn();
    if ($version() >= 3) return;

    $migrate = static function () use ($db, $mysql, $version): void {
        if ($version() >= 3) return;
        $id = $mysql ? 'INT PRIMARY KEY AUTO_INCREMENT' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $tail = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
        $db->exec("CREATE TABLE IF NOT EXISTS record_types (id $id, title VARCHAR(120) NOT NULL, slug VARCHAR(100) NOT NULL UNIQUE)" . $tail);
        $db->exec("CREATE TABLE IF NOT EXISTS records (id $id, type_id INT NOT NULL, title VARCHAR(120) NOT NULL, slug VARCHAR(100) NOT NULL UNIQUE, body MEDIUMTEXT NOT NULL, status VARCHAR(12) NOT NULL, updated_at BIGINT NOT NULL, FOREIGN KEY (type_id) REFERENCES record_types(id))" . $tail);
        $db->exec($mysql ? 'UPDATE plugy_installation SET version = 3 WHERE id = 1' : 'PRAGMA user_version = 3');
    };
    if ($mysql) withMysqlLock($db, 'records', $migrate);
    else writeTransaction($db, $migrate);
}

function recordTypes(PDO $db): array
{
    return $db->query('SELECT * FROM record_types ORDER BY id')->fetchAll();
}

function recordAdminUrl(string $view, int $typeId, int $id = 0): string
{
    return adminUrl($view, $id) . '&type=' . $typeId;
}

function recordUrl(string $slug, string $root = './'): string
{
    return $root . '?record=' . rawurlencode($slug);
}

function get_records(string $group): array
{
    $db = $GLOBALS['db'];
    $query = $db->prepare("SELECT r.id, r.title, r.slug, r.body FROM records r JOIN record_types t ON t.id = r.type_id WHERE t.slug = ? AND r.status = 'published' ORDER BY r.updated_at DESC, r.id DESC");
    $query->execute([$group]);
    $records = $query->fetchAll();
    foreach ($records as &$record) $record['url'] = recordUrl($record['slug']);
    return $records;
}
