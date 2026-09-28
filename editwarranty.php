<?php
session_start();
include("db.php");
include_once("schema_helpers.php");

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['Admin', 'SuperAdmin'], true)) {
    header("Location: login.php");
    exit();
}

$warranty_id = intval($_GET['id'] ?? $_POST['warranty_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ebike_model = trim($_POST['ebike_model'] ?? '');
    $purchase_date = $_POST['purchase_date'] ?? '';
    $warranty_period = intval($_POST['warranty_period'] ?? 0);
    $warranty_status = $_POST['warranty_status'] ?? 'Active';
    $allowed = ['Active', 'Expired', 'Claimed', 'Rejected'];

    if ($warranty_id > 0 && $ebike_model !== '' && $purchase_date !== '' && $warranty_period > 0 && in_array($warranty_status, $allowed, true)) {
        if (!in_array($warranty_status, ['Claimed', 'Rejected'], true)) {
            $warranty_status = calculatedWarrantyRecordStatus($purchase_date, $warranty_period);
        }
        $claimDateSql = $warranty_status === 'Claimed' ? ', claim_date = COALESCE(claim_date, NOW())' : '';
        $stmt = $conn->prepare("UPDATE warranty_records SET ebike_model=?, purchase_date=?, warranty_period=?, warranty_status=? $claimDateSql WHERE warranty_id=?");
        $stmt->bind_param("ssisi", $ebike_model, $purchase_date, $warranty_period, $warranty_status, $warranty_id);
        $stmt->execute();
        header("Location: adminwarranties.php?updated=1");
        exit();
    }
}

$stmt = $conn->prepare("SELECT * FROM warranty_records WHERE warranty_id=?");
$stmt->bind_param("i", $warranty_id);
$stmt->execute();
$warranty = $stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Edit Warranty - FixTrack</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="bg-light">
  <?php include("navbaradmin.php"); ?>
  <div class="container mt-4"><div class="card shadow-sm"><div class="card-body">
    <h3 class="mb-3">Edit Warranty</h3>
    <?php if ($warranty): ?>
      <form method="post">
        <input type="hidden" name="warranty_id" value="<?= htmlspecialchars($warranty['warranty_id']) ?>">
        <div class="mb-3"><label class="form-label">E-Bike Model</label><input class="form-control" name="ebike_model" value="<?= htmlspecialchars($warranty['ebike_model']) ?>" required></div>
        <div class="mb-3"><label class="form-label">Purchase Date</label><input class="form-control" type="date" name="purchase_date" value="<?= htmlspecialchars($warranty['purchase_date']) ?>" required></div>
        <div class="mb-3"><label class="form-label">Warranty Period</label><input class="form-control" type="number" min="1" name="warranty_period" value="<?= htmlspecialchars($warranty['warranty_period']) ?>" required></div>
        <div class="mb-3"><label class="form-label">Status</label><select class="form-select" name="warranty_status"><?php foreach(['Active','Expired','Claimed','Rejected'] as $status): ?><option <?= $warranty['warranty_status']===$status?'selected':'' ?>><?= $status ?></option><?php endforeach; ?></select></div>
        <button class="btn btn-success">Save</button>
        <a href="adminwarranties.php" class="btn btn-secondary">Cancel</a>
      </form>
    <?php else: ?>
      <p class="text-muted">Warranty not found.</p><a href="adminwarranties.php" class="btn btn-secondary">Back</a>
    <?php endif; ?>
  </div></div></div>
</body>
</html>
