<?php
session_start();
include("db.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Cashier') {
    header("Location: login.php");
    exit();
}

$statusRows = $conn->query("SELECT payment_status, COUNT(*) AS total FROM repair_bookings GROUP BY payment_status");
$monthlyRevenue = $conn->query("
  SELECT DATE_FORMAT(updated_at, '%Y-%m') AS month, COALESCE(SUM(amount),0) AS total
  FROM repairs
  WHERE repair_status='Completed'
  GROUP BY month
  ORDER BY month DESC
  LIMIT 6
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Cashier Reports - Red Star</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
  <?php include("navbarcashier.php"); ?>
  <div class="container mt-4">
    <h3 class="mb-4">Cashier Reports</h3>
    <div class="row g-3">
      <div class="col-md-6">
        <div class="card shadow-sm">
          <div class="card-body">
            <h5>Payment Status</h5>
            <table class="table table-bordered">
              <thead class="table-danger"><tr><th>Status</th><th>Total</th></tr></thead>
              <tbody>
                <?php if ($statusRows && $statusRows->num_rows > 0): while($row = $statusRows->fetch_assoc()): ?>
                  <tr><td><?= htmlspecialchars($row['payment_status']) ?></td><td><?= htmlspecialchars($row['total']) ?></td></tr>
                <?php endwhile; else: ?>
                  <tr><td colspan="2" class="text-center text-muted">No booking payments yet.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card shadow-sm">
          <div class="card-body">
            <h5>Monthly Revenue</h5>
            <table class="table table-bordered">
              <thead class="table-danger"><tr><th>Month</th><th>Revenue</th></tr></thead>
              <tbody>
                <?php if ($monthlyRevenue && $monthlyRevenue->num_rows > 0): while($row = $monthlyRevenue->fetch_assoc()): ?>
                  <tr><td><?= htmlspecialchars($row['month']) ?></td><td>PHP <?= number_format((float)$row['total'], 2) ?></td></tr>
                <?php endwhile; else: ?>
                  <tr><td colspan="2" class="text-center text-muted">No completed repair revenue yet.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
