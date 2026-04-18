<?php
// Handles Slack OAuth callback and exchange. Requires client id/secret in config.php
require_once __DIR__ . '/auth.php';
$cfg = @include __DIR__ . '/config.php';
$clientId = $cfg['slack_client_id'] ?? '';
$clientSecret = $cfg['slack_client_secret'] ?? '';

// Determine base URL from current request
function detect_base_url($cfg) {
    if (!empty($cfg['base_url'])) return rtrim($cfg['base_url'], '/');
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
    return $https . '://' . $host;
}

$base = detect_base_url($cfg);

if (!empty($_GET['oauth'])) {
    // Start OAuth by redirecting to Slack
    if (!$clientId) {
        echo "Slack OAuth not configured. Set SLACK_CLIENT_ID and SLACK_CLIENT_SECRET in environment or edit config.php.";
        exit;
    }
    $redirect = $base . '/slack_callback.php';
    $state = bin2hex(random_bytes(8));
    $_SESSION['slack_oauth_state'] = $state;
    $url = 'https://slack.com/oauth/v2/authorize?client_id=' . urlencode($clientId) . '&redirect_uri=' . urlencode($redirect) . '&state=' . $state . '&scope=openid%20profile%20email&user_scope=';
    header('Location: ' . $url);
    exit;
}

// Handle OAuth callback
if (!empty($_GET['code'])) {
    if (empty($_GET['state']) || $_GET['state'] !== ($_SESSION['slack_oauth_state'] ?? '')) {
        echo "Invalid OAuth state";
        exit;
    }
    $code = $_GET['code'];
    $tokenUrl = 'https://slack.com/api/oauth.v2.access';
    $postParams = [
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'code' => $code,
        'redirect_uri' => $base . '/slack_callback.php'
    ];
    $post = http_build_query($postParams);
    $opts = [
        'http' => [
            'method' => 'POST',
            'header' => "Accept: application/json\r\nContent-Type: application/x-www-form-urlencoded\r\n",
            'content' => $post,
            'timeout' => 10
        ]
    ];
    $res = @file_get_contents($tokenUrl, false, stream_context_create($opts));
    $data = $res ? json_decode($res, true) : null;
    if (empty($data['ok']) || empty($data['access_token'])) {
        echo "OAuth failed: " . ($data['error'] ?? 'Unknown error');
        exit;
    }
    $token = $data['access_token'];
    
    // Fetch user info
    $opts = [
        'http' => [
            'method' => 'POST',
            'header' => "Accept: application/json\r\nContent-Type: application/x-www-form-urlencoded\r\nAuthorization: Bearer $token\r\n",
            'timeout' => 10
        ]
    ];
    $userRes = @file_get_contents('https://slack.com/api/openid.connect.userInfo', false, stream_context_create($opts));
    $user = json_decode($userRes, true);
    if (empty($user['ok']) || empty($user['sub'])) {
        echo "Failed to get Slack user info: " . ($user['error'] ?? 'Unknown error');
        exit;
    }
    
    $slack_id = $user['sub']; // sub is the Slack user ID
    $email = $user['email'] ?? null;
    $name = $user['name'] ?? $user['given_name'] ?? $email ?? 'slackuser';
    
    $pdo = getPDO();
    
    // Check if user exists by slack_id (store in bio or as a field)
    $stmt = $pdo->prepare('SELECT id FROM users WHERE bio LIKE ?');
    $stmt->execute(['%slack_id:' . $slack_id . '%']);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($row) {
        // User exists, log them in
        login_user_by_id($row['id']);
        session_regenerate_id(true);
        header('Location: ideas.php');
        exit;
    }
    
    // Create new user with Slack info
    $username = preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower(substr($name, 0, 20)));
    if ($username === '') $username = 'slack_' . substr($slack_id, 0, 8);
    
    // Check if username already exists
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        $username = $username . '_' . substr($slack_id, 0, 6);
    }
    
    // Check if username contains forbidden words and log violations for admins
    if ($bad = check_forbidden($username, 'username', null, 'Slack OAuth signup')) {
        $username = 'slack_' . substr($slack_id, 0, 8);
    }
    
    $now = date('c');
    $bio = 'slack_id:' . $slack_id;
    
    try {
        $stmt = $pdo->prepare('INSERT INTO users (username, role, bio, created_at) VALUES (?, ?, ?, ?)');
        $stmt->execute([$username, 'both', $bio, $now]);
        $id = $pdo->lastInsertId();
    } catch (Exception $e) {
        // Retry with a fallback username
        $username = 'slack_' . substr($slack_id, 0, 8) . '_' . time();
        $stmt = $pdo->prepare('INSERT INTO users (username, role, bio, created_at) VALUES (?, ?, ?, ?)');
        $stmt->execute([$username, 'both', $bio, $now]);
        $id = $pdo->lastInsertId();
    }
    
    login_user_by_id($id);
    session_regenerate_id(true);
    header('Location: ideas.php');
    exit;
}

// If no code or oauth param, show error
echo "Invalid Slack callback";
exit;
