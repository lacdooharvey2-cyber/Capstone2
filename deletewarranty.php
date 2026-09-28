<?php
session_start();
include("db.php");

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['Admin', 'SuperAdmin'], true)) {
    header("Location: login.php");
    exit();
}

$warranty_id = intval($_GET['id'] ?? $_POST['warranty_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $warranty_id > 0) {
    $stmt = $conn->prepare("DELETE FROM warranty_records WHERE warranty_id=?");
    $stmt->bind_param("i", $warranty_id);
    $stmt->execute();
    header("Location: adminwarranties.php?deleted=1");
    exit();
}

$stmt = $conn->prepare("SELECT warranty_id, ebike_model FROM warranty_records WHERE warranty_id=?");
$stmt->bind_param("i", $warranty_id);
$stmt->execute();
$warranty = $stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Delete Warranty - FixTrack</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="bg-light">
  <?php include("navbaradmin.php"); ?>
  <div class="container mt-4"><div class="card shadow-sm"><div class="card-body">
    <h3 class="mb-3">Delete Warranty</h3>
    <?php if ($warranty): ?>
      <p>Delete warranty #<?= htmlspecialchars($warranty['warranty_id']) ?> for <strong><?= htmlspecialchars($warranty['ebike_model']) ?></strong>?</p>
      <form method="post"><input type="hidden" name="warranty_id" value="<?= htmlspecialchars($warranty['warranty_id']) ?>"><button class="btn btn-success">Delete</button> <a href="adminwarranties.php" class="btn btn-secondary">Cancel</a></form>
    <?php else: ?>
      <p class="text-muted">Warranty not found.</p><a href="adminwarranties.php" class="btn btn-secondary">Back</a>
    <?php endif; ?>
  </div></div></div>
</body>
</html>
