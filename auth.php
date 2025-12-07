<?php
// Authentication helpers for Visionary
session_start();

function getPDO() {
    $dataDir = __DIR__ . '/data';
    if (!is_dir($dataDir)) mkdir($dataDir, 0755, true);
    $dbPath = $dataDir . '/visionary.db';
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // ensure users table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE,
        password_hash TEXT,
        role TEXT,
        github_id TEXT,
        created_at TEXT
    )");
    return $pdo;
}

function current_user() {
    if (!empty($_SESSION['user_id'])) {
        static $user = null;
        if ($user === null) {
            $pdo = getPDO();
            $stmt = $pdo->prepare('SELECT id, username, role, github_id FROM users WHERE id = ?');
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: false;
        }
        return $user;
    }
    return null;
}

function login_user_by_id($id) {
    $_SESSION['user_id'] = $id;
}

function logout_user() {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}
