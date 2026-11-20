CREATE TABLE admin (
    id INTEGER PRIMARY KEY CHECK (id = 1),
    password_hash TEXT NOT NULL,
    demo INTEGER NOT NULL CHECK (demo IN (0, 1)),
    session_version INTEGER NOT NULL DEFAULT 1
);

CREATE TABLE login_limits (
    scope TEXT PRIMARY KEY,
    attempts INTEGER NOT NULL,
    expires INTEGER NOT NULL
);

CREATE TABLE blocks (
    id INTEGER PRIMARY KEY,
    kind TEXT NOT NULL CHECK (kind IN ('hero', 'text', 'callout')),
    eyebrow TEXT NOT NULL,
    title TEXT NOT NULL,
    body TEXT NOT NULL,
    link_label TEXT NOT NULL,
    link_url TEXT NOT NULL,
    position INTEGER NOT NULL,
    visible INTEGER NOT NULL CHECK (visible IN (0, 1)),
    updated_at INTEGER NOT NULL
);

CREATE TABLE articles (
    id INTEGER PRIMARY KEY,
    title TEXT NOT NULL,
    slug TEXT NOT NULL UNIQUE,
    excerpt TEXT NOT NULL,
    body TEXT NOT NULL,
    status TEXT NOT NULL CHECK (status IN ('draft', 'published')),
    published_at INTEGER,
    updated_at INTEGER NOT NULL
);
