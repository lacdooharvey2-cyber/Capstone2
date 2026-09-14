<?php
session_start();
include("db.php");

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['Admin', 'SuperAdmin'], true)) {
    header("Location: login.php");
    exit();
}

$repair_id = intval($_GET['id'] ?? $_POST['repair_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $repair_id > 0) {
    $stmt = $conn->prepare("DELETE FROM repairs WHERE repair_id=?");
    $stmt->bind_param("i", $repair_id);
    $stmt->execute();
    header("Location: adminrepairs.php?deleted=1");
    exit();
}

$stmt = $conn->prepare("SELECT repair_id, ebike_model FROM repairs WHERE repair_id=?");
$stmt->bind_param("i", $repair_id);
$stmt->execute();
$repair = $stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Delete Repair - FixTrack</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="bg-light">
  <?php include("navbaradmin.php"); ?>
  <div class="container mt-4"><div class="card shadow-sm"><div class="card-body">
    <h3 class="mb-3">Delete Repair</h3>
    <?php if ($repair): ?>
      <p>Delete repair #<?= htmlspecialchars($repair['repair_id']) ?> for <strong><?= htmlspecialchars($repair['ebike_model']) ?></strong>?</p>
      <form method="post"><input type="hidden" name="repair_id" value="<?= htmlspecialchars($repair['repair_id']) ?>"><button class="btn btn-danger">Delete</button> <a href="adminrepairs.php" class="btn btn-secondary">Cancel</a></form>
    <?php else: ?>
      <p class="text-muted">Repair not found.</p><a href="adminrepairs.php" class="btn btn-secondary">Back</a>
    <?php endif; ?>
  </div></div></div>
</body>
</html>
