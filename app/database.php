<?php
declare(strict_types=1);

function dataDirectory(): string
{
    return dirname(__DIR__) . '/var';
}

function ensurePrivateDirectory(string $path): void
{
    if (!is_dir($path) && !@mkdir($path, 0700, true) && !is_dir($path)) {
        throw new SetupException('Дайте PHP права на запись в папку var в корне проекта.');
    }
}

function openDatabase(): PDO
{
    if (!extension_loaded('pdo_sqlite')) {
        throw new SetupException('Включите расширение pdo_sqlite в настройках PHP.');
    }

    ensurePrivateDirectory(dataDirectory());
    $db = new PDO('sqlite:' . dataDirectory() . '/plugy.sqlite', options: [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $db->exec('PRAGMA busy_timeout = 5000');
    $version = (int) $db->query('PRAGMA user_version')->fetchColumn();
    if ($version === 0) {
        installDatabase($db);
    } elseif ($version !== 1) {
        throw new SetupException('Версия базы данных несовместима с этой версией Plugy Engine.');
    }

    return $db;
}

function writeTransaction(PDO $db, callable $write): mixed
{
    $db->exec('BEGIN IMMEDIATE');
    try {
        $result = $write();
        $db->exec('COMMIT');
        return $result;
    } catch (Throwable $error) {
        $db->exec('ROLLBACK');
        throw $error;
    }
}

function installDatabase(PDO $db): void
{
    writeTransaction($db, static function () use ($db): void {
        if ((int) $db->query('PRAGMA user_version')->fetchColumn() !== 0) {
            return;
        }

        $configured = getenv('PLUGY_ADMIN_PASSWORD');
        $demo = $configured === false && isLocalDevelopment();
        if (!$demo && (!isHttps() || $configured === false || !validNewPassword($configured))) {
            throw new SetupException('Для первого запуска настройте HTTPS и задайте надёжный пароль в переменной PLUGY_ADMIN_PASSWORD.');
        }
        $passwordHash = hashAdminPassword($demo ? 'admin' : $configured);
        $db->exec(file_get_contents(__DIR__ . '/schema.sql'));
        $db->prepare('INSERT INTO admin (id, password_hash, demo) VALUES (1, ?, ?)')
            ->execute([$passwordHash, (int) $demo]);
        $seed = require __DIR__ . '/seed.php';
        $now = time();
        $block = $db->prepare('INSERT INTO blocks (kind, eyebrow, title, body, link_label, link_url, position, visible, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)');
        foreach ($seed['blocks'] as $position => $fields) {
            $block->execute([...$fields, $position + 1, $now]);
        }
        $article = $db->prepare('INSERT INTO articles (title, slug, excerpt, body, status, published_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?)');
        foreach ($seed['articles'] as [$title, $slug, $excerpt, $body, $status, $age]) {
            $date = $now - $age * 86400;
            $article->execute([$title, $slug, $excerpt, $body, $status, $status === 'published' ? $date : null, $date]);
        }
        $db->exec('PRAGMA user_version = 1');
    });
}
