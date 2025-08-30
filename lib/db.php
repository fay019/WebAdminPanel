<?php
declare(strict_types=1);
function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $path = __DIR__ . '/../data/sites.db';
    $pdo = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    return $pdo;
}
function migrate(): void {
    $pdo = db();
    $pdo->exec("CREATE TABLE IF NOT EXISTS users(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password_hash TEXT NOT NULL,
        notes TEXT NULL,
        created_at TEXT NOT NULL
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS sites(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT UNIQUE NOT NULL,
        server_names TEXT NOT NULL,
        root TEXT NOT NULL,
        php_version TEXT NOT NULL,
        client_max_body_size INTEGER NOT NULL DEFAULT 20,
        with_logs INTEGER NOT NULL DEFAULT 1,
        enabled INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS audit(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL,
        action TEXT NOT NULL,
        payload TEXT,
        created_at TEXT NOT NULL
    )");

    // Backward-compatible migration: add 'notes' column if DB was created before this field
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN notes TEXT NULL");
    } catch (Exception $e) {
        // Ignore if column already exists (SQLite raises an error we can safely swallow)
    }

    // Ensure case-insensitive uniqueness on usernames (SQLite NOCASE)
    try {
        $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS users_username_nocase ON users(username COLLATE NOCASE)");
    } catch (Exception $e) {
        // If duplicates differing only by case already exist, index creation will fail; handle manually and re-run migrate().
    }
}
