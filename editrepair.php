<?php
session_start();
include("db.php");
include_once("schema_helpers.php");
include_once("activity_log_helper.php");

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['AssistantAdmin', 'Admin', 'AssistantSuperAdmin', 'SuperAdmin'], true)) {
    header("Location: login.php");
    exit();
}

if (($_SESSION['role'] ?? '') === 'AssistantAdmin') {
    header("Location: adminrepairs.php");
    exit();
}

ensureRepairAutomationSchema($conn);

$repair_id = intval($_GET['id'] ?? $_POST['repair_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ebike_model = trim($_POST['ebike_model'] ?? '');
    $issue_description = trim($_POST['issue_description'] ?? '');
    $repair_status = $_POST['repair_status'] ?? 'Pending';
    $warranty_status = $_POST['warranty_status'] === 'Valid' ? 'Valid' : 'Invalid';
    $amount = (float)($_POST['amount'] ?? 0);
    $technician_id = $_POST['technician_id'] !== '' ? intval($_POST['technician_id']) : null;
    $allowed = ['Pending', 'In Progress', 'Completed', 'Cancelled'];

    if ($repair_id > 0 && $ebike_model !== '' && $issue_description !== '' && in_array($repair_status, $allowed, true)) {
        $stmt = $conn->prepare("UPDATE repairs SET ebike_model=?, issue_description=?, repair_status=?, warranty_status=?, amount=?, technician_id=? WHERE repair_id=?");
        $stmt->bind_param("ssssdii", $ebike_model, $issue_description, $repair_status, $warranty_status, $amount, $technician_id, $repair_id);
        $stmt->execute();

        $bookingStmt = $conn->prepare("UPDATE repair_bookings SET warranty_status=?, estimated_amount=?, payment_status=IF(? > 0, payment_status, 'Paid') WHERE booking_id=(SELECT booking_id FROM repairs WHERE repair_id=? LIMIT 1)");
        $bookingStmt->bind_param("sddi", $warranty_status, $amount, $amount, $repair_id);
        $bookingStmt->execute();
        logActivity($conn, (int)$_SESSION['user_id'], (string)$_SESSION['role'], 'Repair edited', 'Updated repair #' . $repair_id . ' from the admin panel.', 'repair', $repair_id);

        header("Location: adminrepairs.php?updated=1");
        exit();
    }
}

$stmt = $conn->prepare("SELECT * FROM repairs WHERE repair_id=?");
$stmt->bind_param("i", $repair_id);
$stmt->execute();
$repair = $stmt->get_result()->fetch_assoc();
$technicians = $conn->query("SELECT user_id, name FROM users WHERE role IN ('Technician','HeadTechnician') ORDER BY role='HeadTechnician' DESC, name");
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Edit Repair - FixTrack</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="bg-light">
  <?php include("navbaradmin.php"); ?>
  <div class="container mt-4"><div class="card shadow-sm"><div class="card-body">
    <h3 class="mb-3">Edit Repair</h3>
    <?php if ($repair): ?>
      <form method="post">
        <input type="hidden" name="repair_id" value="<?= htmlspecialchars($repair['repair_id']) ?>">
        <div class="mb-3"><label class="form-label">E-Bike Model</label><input class="form-control" name="ebike_model" value="<?= htmlspecialchars($repair['ebike_model']) ?>" required></div>
        <div class="mb-3"><label class="form-label">Issue</label><textarea class="form-control" name="issue_description" rows="4" required><?= htmlspecialchars($repair['issue_description']) ?></textarea></div>
        <div class="mb-3"><label class="form-label">Status</label><select class="form-select" name="repair_status"><?php foreach(['Pending','In Progress','Completed','Cancelled'] as $status): ?><option <?= $repair['repair_status']===$status?'selected':'' ?>><?= $status ?></option><?php endforeach; ?></select></div>
        <div class="mb-3"><label class="form-label">Warranty Verification</label><select class="form-select" name="warranty_status"><option <?= $repair['warranty_status']==='Valid'?'selected':'' ?>>Valid</option><option <?= $repair['warranty_status']==='Invalid'?'selected':'' ?>>Invalid</option></select></div>
        <div class="mb-3"><label class="form-label">Amount</label><input class="form-control" type="number" min="0" step="0.01" name="amount" value="<?= htmlspecialchars($repair['amount']) ?>"></div>
        <div class="mb-3"><label class="form-label">Technician</label><select class="form-select" name="technician_id"><option value="">Unassigned</option><?php while($t=$technicians->fetch_assoc()): ?><option value="<?= htmlspecialchars($t['user_id']) ?>" <?= intval($repair['technician_id'])===intval($t['user_id'])?'selected':'' ?>><?= htmlspecialchars($t['name']) ?></option><?php endwhile; ?></select></div>
        <button class="btn btn-success">Save</button>
        <a href="adminrepairs.php" class="btn btn-secondary">Cancel</a>
      </form>
    <?php else: ?>
      <p class="text-muted">Repair not found.</p><a href="adminrepairs.php" class="btn btn-secondary">Back</a>
    <?php endif; ?>
  </div></div></div>
</body>
</html>
