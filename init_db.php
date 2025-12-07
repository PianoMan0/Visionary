<?php
$dataDir = __DIR__ . '/data';
if (!is_dir($dataDir)) mkdir($dataDir, 0755, true);
$dbPath = $dataDir . '/visionary.db';


$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE IF NOT EXISTS ideas (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    description TEXT,
    author_name TEXT,
    developer_name TEXT,
    status TEXT NOT NULL DEFAULT 'open',
    created_at TEXT,
    updated_at TEXT
)");

// create users table
$pdo->exec("CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE,
    password_hash TEXT,
    role TEXT,
    github_id TEXT,
    created_at TEXT
)");

$now = date('c');
$stmt = $pdo->prepare('INSERT INTO ideas (title, description, author_name, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)');
$stmt->execute(["Example: Instant mockups", "A tool to create quick mockups from text prompts.", "Alice", 'open', $now, $now]);
$stmt->execute(["Example: Open API for widgets", "An API to share reusable UI widgets for teams.", "Bob", 'open', $now, $now]);

// add sample user (username: demo, password: demo)
$check = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = ?');
$check->execute(['demo']);
if ($check->fetchColumn() == 0) {
    $pw = password_hash('demo', PASSWORD_DEFAULT);
    $pdo->prepare('INSERT INTO users (username, password_hash, role, created_at) VALUES (?, ?, ?, ?)')
        ->execute(['demo', $pw, 'both', $now]);
}

echo "Initialized DB at: $dbPath\n";
