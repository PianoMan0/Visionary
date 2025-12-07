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
  <title>Visionary — Ideas Board</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
  <header>
    <h1>Visionary</h1>
    <p>Post ideas. Developers claim them and implement.</p>
  </header>

  <main>
    <section id="post-idea">
      <h2>Share an idea</h2>
      <?php if (!$user || !in_array($user['role'], ['poster','both'])): ?>
        <p>You must <a href="login.php">sign in</a> as a poster to submit ideas.</p>
      <?php endif; ?>
      <form id="ideaForm" novalidate <?php if (!$user || !in_array($user['role'], ['poster','both'])) echo 'class="disabled"';?>>
        <input type="text" name="title" id="title" placeholder="Short title" required <?php if (!$user || !in_array($user['role'], ['poster','both'])) echo 'disabled';?>>
        <textarea name="description" id="description" placeholder="Describe the idea" rows="4" <?php if (!$user || !in_array($user['role'], ['poster','both'])) echo 'disabled';?>></textarea>
        <button type="submit" <?php if (!$user || !in_array($user['role'], ['poster','both'])) echo 'disabled';?>>Post idea</button>
      </form>
    </section>

    <section id="ideas-list">
      <h2>Ideas</h2>
      <div id="list"></div>
    </section>
  </main>

  <script>window.CURRENT_USER = <?=json_encode($user)?>;</script>
  <script src="js/app.js"></script>
</body>
</html>
