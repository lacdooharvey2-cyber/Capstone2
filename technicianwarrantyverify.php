<?php
session_start();
include("db.php");

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['Technician', 'HeadTechnician'], true)) {
    header("Location: login.php");
    exit();
}

$warranty_id = intval($_GET['id'] ?? $_POST['warranty_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = $_POST['warranty_status'] ?? 'Active';
    $allowed = ['Active', 'Expired', 'Claimed', 'Rejected'];
    if (!in_array($status, $allowed, true) || $warranty_id <= 0) {
        header("Location: technicianrepair.php?error=invalid_warranty");
        exit();
    }
    $claimDateSql = $status === 'Claimed' ? ', claim_date = NOW()' : '';
    $stmt = $conn->prepare("UPDATE warranty_records SET warranty_status = ? $claimDateSql WHERE warranty_id = ?");
    $stmt->bind_param("si", $status, $warranty_id);
    $stmt->execute();
    header("Location: technicianrepair.php?updated=1");
    exit();
}

$stmt = $conn->prepare("
  SELECT w.*, u.name AS customer_name
  FROM warranty_records w
  LEFT JOIN users u ON u.user_id = w.customer_id
  WHERE w.warranty_id = ?
");
$stmt->bind_param("i", $warranty_id);
$stmt->execute();
$warranty = $stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Verify Warranty - FixTrack</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
  <?php include("navbartechnician.php"); ?>
  <div class="container mt-4">
    <div class="card shadow-sm">
      <div class="card-body">
        <h3 class="mb-3">Verify Warranty</h3>
        <?php if ($warranty): ?>
          <p><strong>Customer:</strong> <?= htmlspecialchars($warranty['customer_name'] ?? 'Customer #'.$warranty['customer_id']) ?></p>
          <p><strong>E-Bike:</strong> <?= htmlspecialchars($warranty['ebike_model']) ?></p>
          <p><strong>Purchase Date:</strong> <?= htmlspecialchars($warranty['purchase_date']) ?></p>
          <form method="post">
            <input type="hidden" name="warranty_id" value="<?= htmlspecialchars($warranty['warranty_id']) ?>">
            <div class="mb-3">
              <label class="form-label">Warranty Status</label>
              <select name="warranty_status" class="form-select">
                <?php foreach (['Active', 'Expired', 'Claimed', 'Rejected'] as $status): ?>
                  <option <?= $warranty['warranty_status'] === $status ? 'selected' : '' ?>><?= $status ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <button class="btn btn-success">Save</button>
            <a href="technicianrepair.php" class="btn btn-secondary">Cancel</a>
          </form>
        <?php else: ?>
          <p class="text-muted">Warranty record not found.</p>
          <a href="technicianrepair.php" class="btn btn-secondary">Back</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</body>
</html>
