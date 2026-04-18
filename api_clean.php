<?php
header('Content-Type: application/json');
require_once __DIR__ . '/auth.php';

$pdo = getPDO();

function debug_log($label, $data) {
    $dir = __DIR__ . '/data';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $path = $dir . '/api_debug.log';
    $entry = date('c') . " | " . $label . " | " . json_encode($data) . "\n";
    @file_put_contents($path, $entry, FILE_APPEND | LOCK_EX);
}

function jsonResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

$action = $_REQUEST['action'] ?? ($_SERVER['REQUEST_METHOD'] === 'GET' ? 'list' : null);
debug_log('request_start', ['action'=>$action, 'method'=>$_SERVER['REQUEST_METHOD'], 'remote'=>$_SERVER['REMOTE_ADDR'] ?? null]);

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

try {
    if ($action === 'list') {
        $stmt = $pdo->query('SELECT * FROM ideas ORDER BY created_at DESC');
        $ideas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        debug_log('list', ['count'=>count($ideas)]);
        jsonResponse(['success' => true, 'data' => $ideas]);
    }

    if ($action === 'current_user') {
        $u = current_user();
        debug_log('current_user', ['user'=>$u]);
        jsonResponse(['success' => true, 'data' => $u ?: null]);
    }

    if ($action === 'create') {
        $user = current_user();
        debug_log('create_attempt', ['user'=>$user, 'input'=>$input]);
        if (!$user) jsonResponse(['success' => false, 'error' => 'Login required'], 401);
        if (!in_array($user['role'], ['poster','both'])) jsonResponse(['success' => false, 'error' => 'Not authorized to post ideas'], 403);
        $title = trim($input['title'] ?? '');
        if ($title === '') jsonResponse(['success' => false, 'error' => 'Title required'], 400);
        $description = $input['description'] ?? '';
        $author = $user['username'];
        $now = date('c');
        $stmt = $pdo->prepare('INSERT INTO ideas (title, description, author_name, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$title, $description, $author, 'open', $now, $now]);
        $id = $pdo->lastInsertId();
        $stmt = $pdo->prepare('SELECT * FROM ideas WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        debug_log('create_success', ['id'=>$id]);
        jsonResponse(['success' => true, 'data' => $row]);
    }

    if ($action === 'claim') {
        $user = current_user();
        debug_log('claim_attempt', ['user'=>$user, 'input'=>$input]);
        if (!$user) jsonResponse(['success' => false, 'error' => 'Login required'], 401);
        if (!in_array($user['role'], ['dev','both'])) jsonResponse(['success' => false, 'error' => 'Only developers can claim ideas'], 403);
        $id = (int)($input['id'] ?? 0);
        if (!$id) jsonResponse(['success' => false, 'error' => 'Invalid id'], 400);
        $dev = $user['username'];
        $now = date('c');
        $stmt = $pdo->prepare('UPDATE ideas SET developer_name = ?, status = ?, updated_at = ? WHERE id = ?');
        $stmt->execute([$dev, 'in_progress', $now, $id]);
        $stmt = $pdo->prepare('SELECT * FROM ideas WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        debug_log('claim_success', ['id'=>$id]);
        jsonResponse(['success' => true, 'data' => $row]);
    }

    if ($action === 'complete') {
        $user = current_user();
        debug_log('complete_attempt', ['user'=>$user, 'input'=>$input]);
        if (!$user) jsonResponse(['success' => false, 'error' => 'Login required'], 401);
        if (!in_array($user['role'], ['dev','both'])) jsonResponse(['success' => false, 'error' => 'Only developers can mark complete'], 403);
        $id = (int)($input['id'] ?? 0);
        if (!$id) jsonResponse(['success' => false, 'error' => 'Invalid id'], 400);
        $now = date('c');
        $stmt = $pdo->prepare('UPDATE ideas SET status = ?, updated_at = ? WHERE id = ?');
        $stmt->execute(['completed', $now, $id]);
        $stmt = $pdo->prepare('SELECT * FROM ideas WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        debug_log('complete_success', ['id'=>$id]);
        jsonResponse(['success' => true, 'data' => $row]);
    }

    jsonResponse(['success' => false, 'error' => 'Unknown action'], 400);

} catch (Throwable $e) {
    debug_log('exception', ['message'=>$e->getMessage(), 'file'=>$e->getFile(), 'line'=>$e->getLine()]);
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
    exit;
}
