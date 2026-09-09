<?php
session_start();
include("db.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $receiver_id = intval($_POST['receiver_id'] ?? 0);
    $content = trim($_POST['content'] ?? '');
    if ($receiver_id > 0 && $content !== '') {
        $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, content, status) VALUES (?, ?, ?, 'Unread')");
        $stmt->bind_param("iis", $_SESSION['user_id'], $receiver_id, $content);
        $stmt->execute();
        header("Location: adminmessages.php?contact=".$receiver_id);
        exit();
    }
}

$users = $conn->query("SELECT user_id, name, role FROM users WHERE user_id <> ".intval($_SESSION['user_id'])." ORDER BY role, name");
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>New Message - FixTrack</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="bg-light">
  <?php include("navbaradmin.php"); ?>
  <div class="container mt-4"><div class="card shadow-sm"><div class="card-body">
    <h3 class="mb-3">New Message</h3>
    <form method="post">
      <div class="mb-3"><label class="form-label">Recipient</label><select class="form-select" name="receiver_id" required><?php while($u=$users->fetch_assoc()): ?><option value="<?= htmlspecialchars($u['user_id']) ?>"><?= htmlspecialchars($u['name'].' ('.$u['role'].')') ?></option><?php endwhile; ?></select></div>
      <div class="mb-3"><label class="form-label">Message</label><textarea class="form-control" name="content" rows="4" required></textarea></div>
      <button class="btn btn-danger">Send</button>
      <a href="adminmessages.php" class="btn btn-secondary">Cancel</a>
    </form>
  </div></div></div>
</body>
</html>
