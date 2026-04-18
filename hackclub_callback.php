<?php
require_once __DIR__ . '/auth.php';
$cfg = @include __DIR__ . '/config.php';
$clientId = $cfg['hackclub_client_id'] ?? '';
$clientSecret = $cfg['hackclub_client_secret'] ?? '';
$discoveryUrl = $cfg['hackclub_discovery_url'] ?? '';
$authorizeUrl = $cfg['hackclub_authorize_url'] ?? '';
$tokenUrl = $cfg['hackclub_token_url'] ?? '';
$userinfoUrl = $cfg['hackclub_userinfo_url'] ?? '';
$jwksUrl = $cfg['hackclub_jwks_url'] ?? '';
$scope = $cfg['hackclub_scope'] ?? 'openid profile email';

function detect_base_url_hc($cfg) {
    if (!empty($cfg['base_url'])) return rtrim($cfg['base_url'], '/');
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
    return $https . '://' . $host;
}

function parse_oauth_response($text) {
    $data = json_decode($text, true);
    if (is_array($data)) return $data;
    parse_str($text, $data);
    return $data;
}

function fetch_json($url, $headers = []) {
    $headers = array_merge(['Accept: application/json', 'User-Agent: Visionary-App'], $headers);
    if (function_exists('curl_version')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $res = curl_exec($ch);
        if ($res === false) return null;
        return json_decode($res, true);
    }
    $opts = ['http' => ['method' => 'GET', 'header' => implode("\r\n", $headers) . "\r\n", 'timeout' => 15]];
    $res = @file_get_contents($url, false, stream_context_create($opts));
    return $res ? json_decode($res, true) : null;
}

function base64url_decode($input) {
    $remainder = strlen($input) % 4;
    if ($remainder) $input .= str_repeat('=', 4 - $remainder);
    return base64_decode(strtr($input, '-_', '+/'));
}

function asn1_len($len) {
    if ($len <= 0x7F) return chr($len);
    $tmp = ltrim(pack('N', $len), "\x00");
    return chr(0x80 | strlen($tmp)) . $tmp;
}

function asn1_encode_integer($data) {
    $data = ltrim($data, "\x00");
    if ($data === '') $data = "\x00";
    if (ord($data[0]) > 0x7f) $data = "\x00" . $data;
    return "\x02" . asn1_len(strlen($data)) . $data;
}

function jwk_to_pem($jwk) {
    if (($jwk['kty'] ?? '') !== 'RSA') return null;
    $n = base64url_decode($jwk['n']);
    $e = base64url_decode($jwk['e']);
    $modInt = asn1_encode_integer($n);
    $expInt = asn1_encode_integer($e);
    $seq = $modInt . $expInt;
    $rsaPubKey = "\x30" . asn1_len(strlen($seq)) . $seq;
    $algoSeq = "\x30\x0d\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00";
    $bitstring = "\x03" . asn1_len(strlen($rsaPubKey)) . "\x00" . $rsaPubKey;
    $spki = "\x30" . asn1_len(strlen($algoSeq . $bitstring)) . $algoSeq . $bitstring;
    $pem = "-----BEGIN PUBLIC KEY-----\r\n" . chunk_split(base64_encode($spki), 64) . "-----END PUBLIC KEY-----\r\n";
    return $pem;
}

function validate_id_token($idToken, $clientId, $issuer = null, $jwksUrl = null, $discovery = null) {
    $parts = explode('.', $idToken);
    if (count($parts) !== 3) return [false, 'invalid_token_format'];
    list($h64, $p64, $s64) = $parts;
    $header = json_decode(base64url_decode($h64), true);
    $payload = json_decode(base64url_decode($p64), true);
    if (!$header || !$payload) return [false, 'invalid_token_decode'];
    $kid = $header['kid'] ?? null;
    $jwks_uri = $jwksUrl ?: ($discovery['jwks_uri'] ?? null);
    if (!$jwks_uri) return [false, 'jwks_uri_missing'];
    $jwks = fetch_json($jwks_uri);
    if (empty($jwks['keys'])) return [false, 'jwks_fetch_failed'];
    $key = null;
    foreach ($jwks['keys'] as $k) {
        if ($kid && ($k['kid'] ?? '') === $kid) { $key = $k; break; }
    }
    if (!$key) { $key = $jwks['keys'][0]; }
    $pem = jwk_to_pem($key);
    if (!$pem) return [false, 'unsupported_jwk'];
    $signed = $h64 . '.' . $p64;
    $sig = base64url_decode($s64);
    $ok = openssl_verify($signed, $sig, $pem, OPENSSL_ALGO_SHA256);
    if ($ok !== 1) return [false, 'signature_invalid'];

    $now = time();
    if (!empty($payload['exp']) && $payload['exp'] < $now) return [false, 'token_expired'];
    if (!empty($payload['nbf']) && $payload['nbf'] > $now) return [false, 'token_not_yet_valid'];
    if (!empty($payload['aud'])) {
        $aud = $payload['aud'];
        if (is_array($aud)) {
            if (!in_array($clientId, $aud, true)) return [false, 'aud_mismatch'];
        } else {
            if ($aud !== $clientId) return [false, 'aud_mismatch'];
        }
    }
    if ($issuer) {
        if (!empty($payload['iss']) && $payload['iss'] !== $issuer) return [false, 'issuer_mismatch'];
    }
    return [$payload, null];
}

$base = detect_base_url_hc($cfg);
$discovery = null;
$issuer = null;
if ($discoveryUrl && (!$authorizeUrl || !$tokenUrl || !$userinfoUrl || !$jwksUrl)) {
    $discovery = fetch_json($discoveryUrl);
    if ($discovery) {
        if (!$authorizeUrl && !empty($discovery['authorization_endpoint'])) $authorizeUrl = $discovery['authorization_endpoint'];
        if (!$tokenUrl && !empty($discovery['token_endpoint'])) $tokenUrl = $discovery['token_endpoint'];
        if (!$userinfoUrl && !empty($discovery['userinfo_endpoint'])) $userinfoUrl = $discovery['userinfo_endpoint'];
        if (!$jwksUrl && !empty($discovery['jwks_uri'])) $jwksUrl = $discovery['jwks_uri'];
        $issuer = $discovery['issuer'] ?? null;
    }
}

if (!empty($_GET['oauth'])) {
    if (!$clientId || !$authorizeUrl) {
        echo "Hack Club OAuth not configured. Set hackclub_client_id and hackclub_authorize_url in config.php.";
        exit;
    }
    $redirect = $base . '/hackclub_callback.php';
    $state = bin2hex(random_bytes(8));
    $_SESSION['hc_oauth_state'] = $state;
    $params = [
        'client_id' => $clientId,
        'redirect_uri' => $redirect,
        'response_type' => 'code',
        'scope' => $scope,
        'state' => $state,
    ];
    $url = $authorizeUrl . (strpos($authorizeUrl, '?') === false ? '?' : '&') . http_build_query($params);
    header('Location: ' . $url);
    exit;
}

if (!empty($_GET['code'])) {
    if (empty($_GET['state']) || $_GET['state'] !== ($_SESSION['hc_oauth_state'] ?? '')) {
        echo "Invalid OAuth state";
        exit;
    }
    if (!$tokenUrl) {
        echo "Token endpoint not configured.";
        exit;
    }
    $code = $_GET['code'];
    $postParams = [
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'code' => $code,
        'redirect_uri' => $base . '/hackclub_callback.php',
        'grant_type' => 'authorization_code',
    ];
    $post = http_build_query($postParams);
    $opts = [
        'http' => [
            'method' => 'POST',
            'header' => "Accept: application/json\r\nContent-Type: application/x-www-form-urlencoded\r\n",
            'content' => $post,
            'timeout' => 15,
        ],
    ];
    $res = @file_get_contents($tokenUrl, false, stream_context_create($opts));
    $data = $res ? parse_oauth_response($res) : null;
    if (empty($data) || empty($data['access_token'])) {
        $err = $data['error'] ?? $data['error_description'] ?? 'Unknown error';
        echo "OAuth token exchange failed: " . h($err);
        exit;
    }

    $accessToken = $data['access_token'];
    $idToken = $data['id_token'] ?? null;
    $user = null;

    if ($userinfoUrl && $accessToken) {
        $userRes = @file_get_contents($userinfoUrl, false, stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "Authorization: Bearer {$accessToken}\r\nAccept: application/json\r\nUser-Agent: Visionary-App\r\n",
                'timeout' => 15,
            ],
        ]));
        if ($userRes) {
            $user = json_decode($userRes, true);
            if (!is_array($user)) {
                $user = parse_oauth_response($userRes);
            }
            if (is_array($user) && isset($user['data']) && is_array($user['data'])) {
                $user = $user['data'];
            }
            if (is_array($user) && isset($user['user']) && is_array($user['user'])) {
                $user = $user['user'];
            }
        }
    }

    if (!$user && $idToken) {
        list($payload, $err) = validate_id_token($idToken, $clientId, $issuer, $jwksUrl, $discovery);
        if ($payload === false || $payload === null) {
            echo "ID token validation failed: " . ($err ?? 'unknown');
            exit;
        }
        $user = $payload;
    }

    if (!$user) {
        echo "Failed to retrieve user information from Hack Club.";
        exit;
    }

    $providerId = $user['sub'] ?? ($user['id'] ?? null);
    if (!$providerId) {
        echo "Hack Club did not return a stable user id.";
        exit;
    }

    $usernameCandidate = $user['preferred_username'] ?? $user['name'] ?? $user['nickname'] ?? $user['email'] ?? null;
    $email = $user['email'] ?? null;
    $avatar = $user['picture'] ?? $user['avatar_url'] ?? null;

    $pdo = getPDO();
    $loginKey = 'hackclub:' . $providerId;
    $stmt = $pdo->prepare('SELECT id FROM users WHERE github_id = ?');
    $stmt->execute([$loginKey]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $stmt = $pdo->prepare('UPDATE users SET avatar_url = COALESCE(avatar_url, ?), email = COALESCE(email, ?) WHERE id = ?');
        $stmt->execute([$avatar, $email, $row['id']]);
        login_user_by_id($row['id']);
        session_regenerate_id(true);
        header('Location: ideas.php');
        exit;
    }

    $username = $usernameCandidate ? preg_replace('/[^a-zA-Z0-9_\-]/', '_', mb_substr($usernameCandidate, 0, 32)) : null;
    if (!$username || strlen($username) < 3) {
        $username = 'hackclub_' . substr(sha1($providerId), 0, 8);
    }

    $now = date('c');
    try {
        $stmt = $pdo->prepare('INSERT INTO users (username, role, github_id, avatar_url, email, created_at) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$username, 'both', $loginKey, $avatar, $email, $now]);
        $id = $pdo->lastInsertId();
    } catch (Exception $e) {
        $username = $username . '_' . substr(sha1($providerId), 0, 6);
        $stmt = $pdo->prepare('INSERT INTO users (username, role, github_id, avatar_url, email, created_at) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$username, 'both', $loginKey, $avatar, $email, $now]);
        $id = $pdo->lastInsertId();
    }

    login_user_by_id($id);
    session_regenerate_id(true);
    header('Location: ideas.php');
    exit;
}

echo "No OAuth code provided.";
