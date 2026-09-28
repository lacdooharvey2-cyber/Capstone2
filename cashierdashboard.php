<?php
session_start();
include("db.php");
include_once("dashboard_alerts_logs.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Cashier') {
    header("Location: login.php");
    exit();
}

$todayRevenue = $conn->query("SELECT COALESCE(SUM(amount),0) AS total FROM repairs WHERE repair_status='Completed' AND DATE(updated_at)=CURDATE()")->fetch_assoc()['total'];
$totalRevenue = $conn->query("SELECT COALESCE(SUM(amount),0) AS total FROM repairs WHERE repair_status='Completed'")->fetch_assoc()['total'];
$pendingPayments = $conn->query("SELECT COUNT(*) AS total FROM repair_bookings WHERE payment_status='Pending'")->fetch_assoc()['total'];
$paidBookings = $conn->query("SELECT COUNT(*) AS total FROM repair_bookings WHERE payment_status='Paid'")->fetch_assoc()['total'];
$overdueDays = 7;
$overdueUnpaid = $conn->query("SELECT COUNT(*) AS total FROM repair_bookings WHERE payment_status='Pending' AND created_at < DATE_SUB(NOW(), INTERVAL $overdueDays DAY)")->fetch_assoc()['total'];
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
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Cashier Dashboard - FixTrack</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    .kpi-card { min-height: 128px; }
    .kpi-body { align-items: flex-start; height: 100%; padding: 18px; position: relative; }
    .kpi-label { color: var(--app-muted); font-size: .82rem; font-weight: 700; letter-spacing: 0; margin-bottom: .5rem; max-width: calc(100% - 48px); min-height: 2.2em; }
    .kpi-value { font-size: clamp(1.05rem, 1.8vw, 1.8rem); font-weight: 800; line-height: 1; margin-bottom: .55rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .kpi-note { color: var(--app-muted); font-size: .78rem; line-height: 1.3; margin-bottom: 0; }
    .kpi-icon { align-items: center; background: #e5f8e9; border-radius: 8px; display: inline-flex; height: 42px; justify-content: center; position: absolute; right: 18px; top: 18px; width: 42px; }
  </style>
</head>
<body class="bg-light">
  <?php include("navbarcashier.php"); ?>
  <div class="container mt-4">
    <div class="page-hero">
      <h3 class="mb-1">Cashier Dashboard</h3>
      <p>Track paid repairs, pending balances, and daily service revenue.</p>
    </div>
    <?php renderDashboardAlerts($conn, $_SESSION['role'], (int)$_SESSION['user_id']); ?>
    <div class="row g-3 mb-4">
      <?php
      $cards = [
        ["Today's Revenue", 'PHP ' . number_format((float)$todayRevenue, 2), 'Collected today', 'text-success', 'bi-cash-stack'],
        ['Total Revenue', 'PHP ' . number_format((float)$totalRevenue, 2), 'Completed repairs', 'text-danger', 'bi-graph-up'],
        ['Pending Payments', $pendingPayments, 'Awaiting collection', 'text-warning', 'bi-clock-history'],
        ['Paid Bookings', $paidBookings, 'Marked as paid', 'text-primary', 'bi-check-circle'],
        ['Overdue/Unpaid', $overdueUnpaid, 'Pending > ' . $overdueDays . ' days', 'text-secondary', 'bi-exclamation-circle'],
      ];
      foreach ($cards as $card):
      ?>
        <div class="col-md">
          <div class="card kpi-card <?= $card[3] ?> shadow-sm h-100"><div class="card-body kpi-body">
            <div>
              <p class="kpi-label"><?= htmlspecialchars($card[0]) ?></p>
              <p class="kpi-value <?= $card[3] ?>"><?= htmlspecialchars((string)$card[1]) ?></p>
              <p class="kpi-note"><?= htmlspecialchars($card[2]) ?></p>
            </div>
            <div class="kpi-icon <?= $card[3] ?>"><i class="bi <?= $card[4] ?>"></i></div>
          </div></div>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="card shadow-sm">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h5 class="mb-0">Recent Repair Payments</h5>
          <a href="cashiertransactions.php" class="btn btn-success btn-sm">View Payments</a>
        </div>
        <div class="table-responsive">
        <table class="table table-hover table-bordered">
          <thead class="table-danger"><tr><th>Repair ID</th><th>Customer</th><th>E-Bike</th><th>Amount</th><th>Status</th><th>Updated</th><th>Details</th></tr></thead>
          <tbody>
            <?php if ($recentRepairs && $recentRepairs->num_rows > 0): while($row = $recentRepairs->fetch_assoc()): ?>
              <tr>
                <td><?= htmlspecialchars($row['repair_id']) ?></td>
                <td><?= htmlspecialchars($row['customer_name'] ?? 'Customer') ?></td>
                <td><?= htmlspecialchars($row['ebike_model']) ?></td>
                <td>PHP <?= number_format((float)$row['amount'], 2) ?></td>
                <td><span class="badge bg-<?= $row['repair_status'] === 'Completed' ? 'success' : 'warning' ?>"><?= htmlspecialchars($row['repair_status']) ?></span></td>
                <td><?= htmlspecialchars($row['updated_at']) ?></td>
                <td><a href="repairdetails.php?id=<?= urlencode((string)$row['repair_id']) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye me-1"></i>View Details</a></td>
              </tr>
            <?php endwhile; else: ?>
              <tr><td colspan="7" class="text-center text-muted">No repair transactions found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
