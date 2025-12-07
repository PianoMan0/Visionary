<?php
// Handles GitHub OAuth callback and exchange. Requires client id/secret in config.php
require_once __DIR__ . '/auth.php';
$cfg = @include __DIR__ . '/config.php';
$clientId = $cfg['github_client_id'] ?? '';
$clientSecret = $cfg['github_client_secret'] ?? '';

// Determine base URL dynamically from the current request when possible.
// This makes OAuth work regardless of which host/port you run the PHP server on.
function detect_base_url($cfg) {
    // prefer explicit config if provided
    if (!empty($cfg['base_url'])) return rtrim($cfg['base_url'], '/');
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
    return $https . '://' . $host;
}

$base = detect_base_url($cfg);

if (!empty($_GET['oauth'])) {
    // start OAuth by redirecting to GitHub
    if (!$clientId) {
        echo "GitHub OAuth not configured. Set GITHUB_CLIENT_ID and GITHUB_CLIENT_SECRET in environment or edit config.php.";
        exit;
    }
    $redirect = $base . '/github_callback.php';
    $state = bin2hex(random_bytes(8));
    $_SESSION['gh_oauth_state'] = $state;
    $url = 'https://github.com/login/oauth/authorize?client_id=' . urlencode($clientId) . '&redirect_uri=' . urlencode($redirect) . '&state=' . $state . '&scope=read:user%20user:email';
    header('Location: ' . $url);
    exit;
}

// handle callback
if (!empty($_GET['code'])) {
    if (empty($_GET['state']) || $_GET['state'] !== ($_SESSION['gh_oauth_state'] ?? '')) {
        echo "Invalid OAuth state"; exit;
    }
    $code = $_GET['code'];
    $tokenUrl = 'https://github.com/login/oauth/access_token';
    // include redirect_uri to ensure GitHub accepts the token exchange in stricter setups
    $postParams = ['client_id'=>$clientId,'client_secret'=>$clientSecret,'code'=>$code,'redirect_uri'=>$base . '/github_callback.php'];
    $post = http_build_query($postParams);
    $opts = [
        'http'=>[
            'method'=>'POST',
            'header'=>"Accept: application/json\r\nContent-Type: application/x-www-form-urlencoded\r\n",
            'content'=>$post,
            'timeout'=>10
        ]
    ];
    $res = @file_get_contents($tokenUrl, false, stream_context_create($opts));
    $data = $res ? json_decode($res, true) : null;
    if (empty($data['access_token'])) { echo "OAuth failed"; exit; }
    $token = $data['access_token'];
    // fetch user
    $opts = [
        'http'=>[
            'method'=>'GET',
            'header'=>"User-Agent: Visionary-App\r\nAuthorization: token $token\r\nAccept: application/json\r\n",
            'timeout'=>10
        ]
    ];
    $userRes = @file_get_contents('https://api.github.com/user', false, stream_context_create($opts));
    $user = json_decode($userRes, true);
    if (empty($user['id'])) { echo "Failed to get GitHub user"; exit; }
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT id FROM users WHERE github_id = ?');
    $stmt->execute([$user['id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        login_user_by_id($row['id']);
        session_regenerate_id(true);
        header('Location: ideas.php'); exit;
    }
    // create new user with role 'both' by default
    $username = $user['login'] ?? ('gh' . $user['id']);
    $now = date('c');
    try {
        $stmt = $pdo->prepare('INSERT INTO users (username, role, github_id, created_at) VALUES (?, ?, ?, ?)');
        $stmt->execute([$username, 'both', $user['id'], $now]);
        $id = $pdo->lastInsertId();
    } catch (Exception $e) {
        // fallback: username collision, append github id
        $username = $username . '_' . $user['id'];
        $stmt = $pdo->prepare('INSERT INTO users (username, role, github_id, created_at) VALUES (?, ?, ?, ?)');
        $stmt->execute([$username, 'both', $user['id'], $now]);
        $id = $pdo->lastInsertId();
    }
    login_user_by_id($id);
    session_regenerate_id(true);
    header('Location: ideas.php'); exit;
}

echo "No OAuth code provided.";
