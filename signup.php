<?php
require_once __DIR__ . '/auth.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'poster';
    if ($username === '' || $password === '') $error = 'Username and password required';
    else {
        $pdo = getPDO();
        $check = $pdo->prepare('SELECT id FROM users WHERE username = ?');
        $check->execute([$username]);
        if ($check->fetch()) $error = 'Username taken';
        else {
            $pw = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO users (username, password_hash, role, created_at) VALUES (?, ?, ?, ?)');
            $stmt->execute([$username, $pw, $role, date('c')]);
            $id = $pdo->lastInsertId();
            login_user_by_id($id);
            header('Location: ideas.php');
            exit;
        }
    }
}
?>
<!doctype html>
<html>
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Sign up — Visionary</title>
    <meta name="description" content="Visionary - A platform for developers and idea crafters to collaborate on innovative ideas.">
    <link rel="stylesheet" href="css/style.css">
  </head>
  <body>
    <main style="max-width:480px;margin:40px auto;padding:16px">
      <h2>Sign up</h2>
      <?php if(!empty($error)):?><p style="color:#b91c1c"><?=htmlspecialchars($error)?></p><?php endif;?>
      <form method="post">
        <input name="username" placeholder="Username">
        <input name="password" type="password" placeholder="Password">
        <p>Role:</p>
        <label><input type="radio" name="role" value="poster" checked> Idea Poster</label><br>
        <label><input type="radio" name="role" value="dev"> Developer</label><br>
        <label><input type="radio" name="role" value="both"> Both</label>
        <button type="submit">Create account</button>
      </form>
      <p>Or <a href="login.php">sign in</a></p>
    </main>
  </body>
</html>
