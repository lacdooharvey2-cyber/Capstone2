<?php
session_start();
include("db.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Cashier') {
    header("Location: login.php");
    exit();
}

$todayRevenue = $conn->query("SELECT COALESCE(SUM(amount),0) AS total FROM repairs WHERE repair_status='Completed' AND DATE(updated_at)=CURDATE()")->fetch_assoc()['total'];
$totalRevenue = $conn->query("SELECT COALESCE(SUM(amount),0) AS total FROM repairs WHERE repair_status='Completed'")->fetch_assoc()['total'];
$pendingPayments = $conn->query("SELECT COUNT(*) AS total FROM repair_bookings WHERE payment_status='Pending'")->fetch_assoc()['total'];
$paidBookings = $conn->query("SELECT COUNT(*) AS total FROM repair_bookings WHERE payment_status='Paid'")->fetch_assoc()['total'];
$recentRepairs = $conn->query("
  SELECT r.repair_id, u.name AS customer_name, r.ebike_model, r.amount, r.repair_status, r.updated_at
  FROM repairs r
  LEFT JOIN users u ON u.user_id = r.customer_id
  ORDER BY r.updated_at DESC
  LIMIT 8
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Cashier Dashboard - Red Star</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
  <?php include("navbarcashier.php"); ?>
  <div class="container mt-4">
    <h3 class="mb-4">Cashier Dashboard</h3>
    <div class="row g-3 mb-4">
      <div class="col-md-3"><div class="card shadow-sm"><div class="card-body text-center"><h6>Today Revenue</h6><p class="display-6 text-success">PHP <?= number_format((float)$todayRevenue, 2) ?></p></div></div></div>
      <div class="col-md-3"><div class="card shadow-sm"><div class="card-body text-center"><h6>Total Revenue</h6><p class="display-6 text-danger">PHP <?= number_format((float)$totalRevenue, 2) ?></p></div></div></div>
      <div class="col-md-3"><div class="card shadow-sm"><div class="card-body text-center"><h6>Pending Payments</h6><p class="display-6 text-warning"><?= $pendingPayments ?></p></div></div></div>
      <div class="col-md-3"><div class="card shadow-sm"><div class="card-body text-center"><h6>Paid Bookings</h6><p class="display-6 text-primary"><?= $paidBookings ?></p></div></div></div>
    </div>
    <div class="card shadow-sm">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h5 class="mb-0">Recent Repair Payments</h5>
          <a href="cashiertransactions.php" class="btn btn-danger btn-sm">View Payments</a>
        </div>
        <table class="table table-hover table-bordered">
          <thead class="table-danger"><tr><th>Repair ID</th><th>Customer</th><th>E-Bike</th><th>Amount</th><th>Status</th><th>Updated</th></tr></thead>
          <tbody>
            <?php if ($recentRepairs && $recentRepairs->num_rows > 0): while($row = $recentRepairs->fetch_assoc()): ?>
              <tr>
                <td><?= htmlspecialchars($row['repair_id']) ?></td>
                <td><?= htmlspecialchars($row['customer_name'] ?? 'Customer') ?></td>
                <td><?= htmlspecialchars($row['ebike_model']) ?></td>
                <td>PHP <?= number_format((float)$row['amount'], 2) ?></td>
                <td><span class="badge bg-<?= $row['repair_status'] === 'Completed' ? 'success' : 'warning' ?>"><?= htmlspecialchars($row['repair_status']) ?></span></td>
                <td><?= htmlspecialchars($row['updated_at']) ?></td>
              </tr>
            <?php endwhile; else: ?>
              <tr><td colspan="6" class="text-center text-muted">No repair transactions found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</body>
</html>
