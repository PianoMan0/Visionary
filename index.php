<?php
?>
<?php
require_once __DIR__ . '/auth.php';
$user = current_user();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="description" content="Visionary - A platform for developers and idea crafters to collaborate on innovative ideas.">
  <title>Visionary — Home</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
  <header>
    <h1>Visionary</h1>
    <p>Post ideas. Developers claim them and implement.</p>
  </header>

  <main style="max-width:900px;margin:24px auto;padding:0 16px">
    <section>
      <?php if ($user): ?>
        <p>Welcome, <strong><?=htmlspecialchars($user['username'])?></strong> — role: <em><?=htmlspecialchars($user['role'])?></em></p>
        <p><a href="ideas.php">Go to Ideas board</a> · <a href="logout.php">Log out</a></p>
      <?php else: ?>
        <p><a href="login.php">Sign in</a> or <a href="signup.php">create an account</a> — or continue anonymously to browse ideas.</p>
        <p><a href="ideas.php">Browse Ideas</a></p>
      <?php endif; ?>
    </section>
  </main>
</body>
</html>
  <script src="js/app.js"></script>
