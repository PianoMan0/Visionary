<?php
<?php
require 'db.php';
session_start();

if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$id = (int)$_GET['id'];
$stmt = $pdo->prepare("SELECT i.*, u.username AS author, p.username AS programmer FROM ideas i 
    LEFT JOIN users u ON i.user_id = u.id 
    LEFT JOIN users p ON i.claimed_by = p.id 
    WHERE i.id = ?");
$stmt->execute([$id]);
$idea = $stmt->fetch();

if (!$idea) {
    echo "<h1>Idea not found.</h1>";
    exit;
}

// Claim idea logic
if (isset($_POST['claim']) && isset($_SESSION['user_id']) && 
    strpos($_SESSION['role'], 'programmer') !== false && 
    $idea['status'] === 'open' && !$idea['claimed_by']) {
    $stmt = $pdo->prepare("UPDATE ideas SET claimed_by = ?, status = 'in_progress' WHERE id = ?");
    $stmt->execute([$_SESSION['user_id'], $id]);
    header("Location: idea.php?id=$id");
    exit;
}

// Mark as completed
if (isset($_POST['complete']) && isset($_SESSION['user_id']) && 
    strpos($_SESSION['role'], 'programmer') !== false && 
    $_SESSION['user_id'] == $idea['claimed_by'] && 
    $idea['status'] === 'in_progress') {
    $stmt = $pdo->prepare("UPDATE ideas SET status = 'completed' WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: idea.php?id=$id");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title><?= htmlspecialchars($idea['title']) ?> - Visionary</title>
</head>
<body>
    <h1><?= htmlspecialchars($idea['title']) ?></h1>
    <p><strong>Description:</strong><br><?= nl2br(htmlspecialchars($idea['description'])) ?></p>
    <p><strong>Submitted by:</strong> <?= htmlspecialchars($idea['author'] ?? 'Anonymous') ?></p>
    <p><strong>Status:</strong> <?= str_replace('_', ' ', $idea['status']) ?></p>
    <?php if ($idea['claimed_by']): ?>
        <p><strong>Claimed by:</strong> <?= htmlspecialchars($idea['programmer']) ?></p>
    <?php endif; ?>

    <?php if (isset($_SESSION['user_id'])): ?>
        <?php if (strpos($_SESSION['role'], 'programmer') !== false && $idea['status'] === 'open' && !$idea['claimed_by']): ?>
            <form method="post">
                <input type="hidden" name="claim" value="1">
                <input type="submit" value="Claim this Idea">
            </form>
        <?php elseif (strpos($_SESSION['role'], 'programmer') !== false && $_SESSION['user_id'] == $idea['claimed_by'] && $idea['status'] === 'in_progress'): ?>
            <form method="post">
                <input type="hidden" name="complete" value="1">
                <input type="submit" value="Mark as Completed">
            </form>
        <?php endif; ?>
    <?php endif; ?>

    <p><a href="index.php">Back to ideas</a></p>
</body>
</html>