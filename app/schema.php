<?php
declare(strict_types=1);

require __DIR__ . '/guard.php';

$engine = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

return [
    'CREATE TABLE IF NOT EXISTS plugy_installation (
        id TINYINT UNSIGNED PRIMARY KEY,
        version INT UNSIGNED NOT NULL
    )' . $engine,
    'CREATE TABLE IF NOT EXISTS admin (
        id TINYINT UNSIGNED PRIMARY KEY,
        password_hash VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        demo TINYINT UNSIGNED NOT NULL,
        session_version INT UNSIGNED NOT NULL DEFAULT 1
    )' . $engine,
    'CREATE TABLE IF NOT EXISTS login_limits (
        scope VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
        attempts INT UNSIGNED NOT NULL,
        expires BIGINT UNSIGNED NOT NULL,
        INDEX (expires)
    )' . $engine,
    'CREATE TABLE IF NOT EXISTS blocks (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        kind VARCHAR(12) NOT NULL,
        eyebrow VARCHAR(60) NOT NULL,
        title VARCHAR(120) NOT NULL,
        body TEXT NOT NULL,
        link_label VARCHAR(40) NOT NULL,
        link_url VARCHAR(400) NOT NULL,
        position INT UNSIGNED NOT NULL,
        visible TINYINT UNSIGNED NOT NULL,
        updated_at BIGINT UNSIGNED NOT NULL
    )' . $engine,
    'CREATE TABLE IF NOT EXISTS articles (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(120) NOT NULL,
        slug VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
        excerpt VARCHAR(280) NOT NULL,
        body MEDIUMTEXT NOT NULL,
        status VARCHAR(12) NOT NULL,
        published_at BIGINT UNSIGNED NULL,
        updated_at BIGINT UNSIGNED NOT NULL
    )' . $engine,
];
