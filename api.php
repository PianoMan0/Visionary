<?php
// API for Visionary app using SQLite + session-based auth
header('Content-Type: application/json');
require_once __DIR__ . '/auth.php';

$pdo = getPDO();

// simple debug logger to data/api_debug.log
function debug_log($label, $data) {
    $dir = __DIR__ . '/data';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $path = $dir . '/api_debug.log';
    $entry = date('c') . " | " . $label . " | " . json_encode($data) . "\n";
    file_put_contents($path, $entry, FILE_APPEND | LOCK_EX);
}

$action = $_REQUEST['action'] ?? ($_SERVER['REQUEST_METHOD'] === 'GET' ? 'list' : null);

// Log incoming request for debugging
debug_log('request_start', ['action'=>$action, 'method'=>$_SERVER['REQUEST_METHOD'], 'remote'=>$_SERVER['REMOTE_ADDR'] ?? null, 'session'=>$_SESSION ?? null]);

function jsonResponse($data) {
    echo json_encode($data);
    exit;
}

// support JSON body
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

if ($action === 'list') {
    $stmt = $pdo->query('SELECT * FROM ideas ORDER BY created_at DESC');
    $ideas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    debug_log('list', ['count'=>count($ideas)]);
    jsonResponse(['success' => true, 'data' => $ideas]);
}

if ($action === 'current_user') {
    $u = current_user();
    debug_log('current_user', ['user'=>$u]);
    if ($u) jsonResponse(['success' => true, 'data' => $u]);
    jsonResponse(['success' => true, 'data' => null]);
}

if ($action === 'create') {
    $user = current_user();
    debug_log('create_attempt', ['user'=>$user, 'input'=>$input]);
    if (!$user) jsonResponse(['success' => false, 'error' => 'Login required']);
    // require poster role
    if (!in_array($user['role'], ['poster','both'])) jsonResponse(['success' => false, 'error' => 'Not authorized to post ideas']);
    $title = trim($input['title'] ?? '');
    if ($title === '') jsonResponse(['success' => false, 'error' => 'Title required']);
    $description = $input['description'] ?? '';
    $author = $user['username'];
    $now = date('c');
    $stmt = $pdo->prepare('INSERT INTO ideas (title, description, author_name, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$title, $description, $author, 'open', $now, $now]);
    $id = $pdo->lastInsertId();
    $stmt = $pdo->prepare('SELECT * FROM ideas WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    debug_log('create_success', ['id'=>$id, 'row'=>$row]);
    jsonResponse(['success' => true, 'data' => $row]);
}

if ($action === 'claim') {
    $user = current_user();
    debug_log('claim_attempt', ['user'=>$user, 'input'=>$input]);
    if (!$user) jsonResponse(['success' => false, 'error' => 'Login required']);
    if (!in_array($user['role'], ['dev','both'])) jsonResponse(['success' => false, 'error' => 'Only developers can claim ideas']);
    $id = (int)($input['id'] ?? 0);
    if (!$id) jsonResponse(['success' => false, 'error' => 'Invalid id']);
    $dev = $user['username'];
    $now = date('c');
    $stmt = $pdo->prepare('UPDATE ideas SET developer_name = ?, status = ?, updated_at = ? WHERE id = ?');
    $stmt->execute([$dev, 'in_progress', $now, $id]);
    $stmt = $pdo->prepare('SELECT * FROM ideas WHERE id = ?');
    $stmt->execute([$id]);
    debug_log('claim_success', ['id'=>$id]);
    jsonResponse(['success' => true, 'data' => $stmt->fetch(PDO::FETCH_ASSOC)]);
}

if ($action === 'complete') {
    $user = current_user();
    debug_log('complete_attempt', ['user'=>$user, 'input'=>$input]);
    if (!$user) jsonResponse(['success' => false, 'error' => 'Login required']);
    if (!in_array($user['role'], ['dev','both'])) jsonResponse(['success' => false, 'error' => 'Only developers can mark complete']);
    $id = (int)($input['id'] ?? 0);
    if (!$id) jsonResponse(['success' => false, 'error' => 'Invalid id']);
    $now = date('c');
    $stmt = $pdo->prepare('UPDATE ideas SET status = ?, updated_at = ? WHERE id = ?');
    $stmt->execute(['completed', $now, $id]);
    $stmt = $pdo->prepare('SELECT * FROM ideas WHERE id = ?');
    $stmt->execute([$id]);
    debug_log('complete_success', ['id'=>$id]);
    jsonResponse(['success' => true, 'data' => $stmt->fetch(PDO::FETCH_ASSOC)]);
}

jsonResponse(['success' => false, 'error' => 'Unknown action']);
<?php
header('Content-Type: application/json');

$dataDir = __DIR__ . '/data';
if (!is_dir($dataDir)) mkdir($dataDir, 0755, true);
$dbPath = $dataDir . '/visionary.db';

try {
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
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}

$action = $_REQUEST['action'] ?? ($_SERVER['REQUEST_METHOD'] === 'GET' ? 'list' : null);

function jsonResponse($data) {
    echo json_encode($data);
    exit;
}

if ($action === 'list') {
    $stmt = $pdo->query('SELECT * FROM ideas ORDER BY created_at DESC');
    $ideas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    jsonResponse(['success' => true, 'data' => $ideas]);
}

// Support JSON body
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

<?php
// API for Visionary app using SQLite + session-based auth
header('Content-Type: application/json');
require_once __DIR__ . '/auth.php';

$pdo = getPDO();

// simple debug logger to data/api_debug.log
function debug_log($label, $data) {
    $dir = __DIR__ . '/data';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $path = $dir . '/api_debug.log';
    $entry = date('c') . " | " . $label . " | " . json_encode($data) . "\n";
    file_put_contents($path, $entry, FILE_APPEND | LOCK_EX);
}

// Log incoming request for debugging
debug_log('request_start', ['action'=>$action, 'method'=>$_SERVER['REQUEST_METHOD'], 'remote'=>$_SERVER['REMOTE_ADDR'] ?? null, 'session'=>$_SESSION ?? null]);

$action = $_REQUEST['action'] ?? ($_SERVER['REQUEST_METHOD'] === 'GET' ? 'list' : null);

function jsonResponse($data) {
    echo json_encode($data);
    exit;
}

// support JSON body
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

if ($action === 'list') {
    $stmt = $pdo->query('SELECT * FROM ideas ORDER BY created_at DESC');
    $ideas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    debug_log('list', ['count'=>count($ideas)]);
    jsonResponse(['success' => true, 'data' => $ideas]);
}

if ($action === 'current_user') {
    $u = current_user();
    debug_log('current_user', ['user'=>$u]);
    if ($u) jsonResponse(['success' => true, 'data' => $u]);
    jsonResponse(['success' => true, 'data' => null]);
}

if ($action === 'create') {
    $user = current_user();
    debug_log('create_attempt', ['user'=>$user, 'input'=>$input]);
    if (!$user) jsonResponse(['success' => false, 'error' => 'Login required']);
    // require poster role
    if (!in_array($user['role'], ['poster','both'])) jsonResponse(['success' => false, 'error' => 'Not authorized to post ideas']);
    $title = trim($input['title'] ?? '');
    if ($title === '') jsonResponse(['success' => false, 'error' => 'Title required']);
    $description = $input['description'] ?? '';
    $author = $user['username'];
    $now = date('c');
    $stmt = $pdo->prepare('INSERT INTO ideas (title, description, author_name, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$title, $description, $author, 'open', $now, $now]);
    $id = $pdo->lastInsertId();
    $stmt = $pdo->prepare('SELECT * FROM ideas WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    debug_log('create_success', ['id'=>$id, 'row'=>$row]);
    jsonResponse(['success' => true, 'data' => $row]);
}

if ($action === 'claim') {
    $user = current_user();
    debug_log('claim_attempt', ['user'=>$user, 'input'=>$input]);
    if (!$user) jsonResponse(['success' => false, 'error' => 'Login required']);
    if (!in_array($user['role'], ['dev','both'])) jsonResponse(['success' => false, 'error' => 'Only developers can claim ideas']);
    $id = (int)($input['id'] ?? 0);
    if (!$id) jsonResponse(['success' => false, 'error' => 'Invalid id']);
    $dev = $user['username'];
    $now = date('c');
    $stmt = $pdo->prepare('UPDATE ideas SET developer_name = ?, status = ?, updated_at = ? WHERE id = ?');
    $stmt->execute([$dev, 'in_progress', $now, $id]);
    $stmt = $pdo->prepare('SELECT * FROM ideas WHERE id = ?');
    $stmt->execute([$id]);
    debug_log('claim_success', ['id'=>$id]);
    jsonResponse(['success' => true, 'data' => $stmt->fetch(PDO::FETCH_ASSOC)]);
}

if ($action === 'complete') {
    $user = current_user();
    debug_log('complete_attempt', ['user'=>$user, 'input'=>$input]);
    if (!$user) jsonResponse(['success' => false, 'error' => 'Login required']);
    if (!in_array($user['role'], ['dev','both'])) jsonResponse(['success' => false, 'error' => 'Only developers can mark complete']);
    $id = (int)($input['id'] ?? 0);
    if (!$id) jsonResponse(['success' => false, 'error' => 'Invalid id']);
    $now = date('c');
    $stmt = $pdo->prepare('UPDATE ideas SET status = ?, updated_at = ? WHERE id = ?');
    $stmt->execute(['completed', $now, $id]);
    $stmt = $pdo->prepare('SELECT * FROM ideas WHERE id = ?');
    $stmt->execute([$id]);
    debug_log('complete_success', ['id'=>$id]);
    jsonResponse(['success' => true, 'data' => $stmt->fetch(PDO::FETCH_ASSOC)]);
}

jsonResponse(['success' => false, 'error' => 'Unknown action']);
