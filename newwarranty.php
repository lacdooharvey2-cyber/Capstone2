<?php
session_start();
include("db.php");
include_once("schema_helpers.php");

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['Admin', 'SuperAdmin'], true)) {
    header("Location: login.php");
    exit();
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_id = intval($_POST['customer_id'] ?? 0);
    $ebike_model = trim($_POST['ebike_model'] ?? '');
    $purchase_date = $_POST['purchase_date'] ?? '';
    $warranty_period = intval($_POST['warranty_period'] ?? 12);
    $warranty_status = $_POST['warranty_status'] ?? 'Active';
    $allowed = ['Active', 'Expired', 'Claimed', 'Rejected'];

    if ($customer_id <= 0 || $ebike_model === '' || $purchase_date === '' || $warranty_period <= 0 || !in_array($warranty_status, $allowed, true)) {
        $error = "Please complete all warranty details.";
    } else {
        if (!in_array($warranty_status, ['Claimed', 'Rejected'], true)) {
            $warranty_status = calculatedWarrantyRecordStatus($purchase_date, $warranty_period);
        }
        $claimDateSql = $warranty_status === 'Claimed' ? 'NOW()' : 'NULL';
        $stmt = $conn->prepare("
            INSERT INTO warranty_records (customer_id, ebike_model, purchase_date, warranty_period, warranty_status, claim_date)
            VALUES (?, ?, ?, ?, ?, $claimDateSql)
        ");
        $stmt->bind_param("issis", $customer_id, $ebike_model, $purchase_date, $warranty_period, $warranty_status);
        $stmt->execute();

        header("Location: adminwarranties.php?created=1");
        exit();
    }
}

$customers = $conn->query("SELECT user_id, name, custom_id FROM users WHERE role='Customer' ORDER BY name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>New Warranty - FixTrack Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
  <?php include("navbaradmin.php"); ?>
  <div class="container mt-4">
    <div class="card shadow-sm">
      <div class="card-body">
        <h3 class="mb-3">New Warranty</h3>
        <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="post">
          <div class="mb-3">
            <label class="form-label">Customer</label>
            <select class="form-select" name="customer_id" required>
              <option value="">Select customer</option>
              <?php if ($customers): while($customer = $customers->fetch_assoc()): ?>
                <option value="<?= htmlspecialchars($customer['user_id']) ?>">
                  <?= htmlspecialchars($customer['name']) ?><?= $customer['custom_id'] ? ' - '.htmlspecialchars($customer['custom_id']) : '' ?>
                </option>
              <?php endwhile; endif; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">E-Bike Model</label>
            <input class="form-control" name="ebike_model" required>
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Purchase Date</label>
              <input class="form-control" type="date" name="purchase_date" required>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Warranty Period</label>
              <input class="form-control" type="number" min="1" name="warranty_period" value="12" required>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Status</label>
            <select class="form-select" name="warranty_status">
              <option>Active</option>
              <option>Expired</option>
              <option>Claimed</option>
              <option>Rejected</option>
            </select>
          </div>
          <button class="btn btn-success">Create Warranty</button>
          <a href="adminwarranties.php" class="btn btn-secondary">Cancel</a>
        </form>
      </div>
    </div>
  </div>
</body>
</html>
