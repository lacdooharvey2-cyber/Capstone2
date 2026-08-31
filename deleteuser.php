<?php
session_start();
include("db.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

$user_id = intval($_GET['id'] ?? $_POST['user_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user_id > 0 && $user_id !== intval($_SESSION['user_id'])) {
    $stmt = $conn->prepare("DELETE FROM users WHERE user_id=?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    header("Location: adminmanageusers.php?deleted=1");
    exit();
}

$stmt = $conn->prepare("SELECT user_id, name, email FROM users WHERE user_id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Delete User - Red Star</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="bg-light">
  <?php include("navbaradmin.php"); ?>
  <div class="container mt-4"><div class="card shadow-sm"><div class="card-body">
    <h3 class="mb-3">Delete User</h3>
    <?php if ($user): ?>
      <p>Delete <strong><?= htmlspecialchars($user['name']) ?></strong>?</p>
      <?php if ($user_id === intval($_SESSION['user_id'])): ?>
        <div class="alert alert-warning">You cannot delete your own logged-in account.</div>
      <?php else: ?>
        <form method="post"><input type="hidden" name="user_id" value="<?= htmlspecialchars($user['user_id']) ?>"><button class="btn btn-danger">Delete</button> <a href="adminmanageusers.php" class="btn btn-secondary">Cancel</a></form>
      <?php endif; ?>
    <?php else: ?>
      <p class="text-muted">User not found.</p><a href="adminmanageusers.php" class="btn btn-secondary">Back</a>
    <?php endif; ?>
  </div></div></div>
</body>
</html>
