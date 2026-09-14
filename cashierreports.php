<?php
session_start();
include("db.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Cashier') {
    header("Location: login.php");
    exit();
}

function cashierRows(mysqli $conn, string $sql): array
{
    $result = $conn->query($sql);
    if (!$result) {
        return [];
    }
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    return $rows;
}

function cashierValue(mysqli $conn, string $sql, string $key = 'total')
{
    $result = $conn->query($sql);
    if (!$result) {
        return 0;
    }
    $row = $result->fetch_assoc();
    return $row[$key] ?? 0;
}

$averageTransaction = (float)cashierValue($conn, "SELECT COALESCE(AVG(NULLIF(amount,0)),0) AS total FROM repairs WHERE repair_status='Completed'");
$paymentStatusRows = cashierRows($conn, "SELECT payment_status AS label, COUNT(*) AS total FROM repair_bookings GROUP BY payment_status ORDER BY total DESC");
$monthlyRows = cashierRows($conn, "
  SELECT DATE_FORMAT(updated_at, '%Y-%m') AS month, COALESCE(SUM(amount),0) AS total
  FROM repairs
  WHERE repair_status='Completed'
    AND updated_at >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 MONTH), '%Y-%m-01')
  GROUP BY month
  ORDER BY month
");
$dailyRows = cashierRows($conn, "
  SELECT DATE(updated_at) AS day, COALESCE(SUM(amount),0) AS total
  FROM repairs
  WHERE repair_status='Completed'
    AND updated_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
  GROUP BY DATE(updated_at)
  ORDER BY day
");

$paymentTotals = ['Pending' => 0, 'Paid' => 0];
foreach ($paymentStatusRows as $row) {
    $paymentTotals[$row['label']] = (int)$row['total'];
}
$paymentSummary = [];
foreach ($paymentTotals as $label => $total) {
    $paymentSummary[] = ['label' => $label, 'total' => $total];
}

$monthlyMap = [];
foreach ($monthlyRows as $row) {
    $monthlyMap[$row['month']] = (float)$row['total'];
}
$monthlyRevenue = [];
$monthCursor = new DateTime('first day of -5 months');
for ($i = 0; $i < 6; $i++) {
    $month = $monthCursor->format('Y-m');
    $monthlyRevenue[] = ['month' => $month, 'total' => $monthlyMap[$month] ?? 0];
    $monthCursor->modify('+1 month');
}

$dailyMap = [];
foreach ($dailyRows as $row) {
    $dailyMap[$row['day']] = (float)$row['total'];
}
$dailyRevenue = [];
$dayCursor = new DateTime('-29 days');
for ($i = 0; $i < 30; $i++) {
    $day = $dayCursor->format('Y-m-d');
    $dailyRevenue[] = ['day' => $day, 'total' => $dailyMap[$day] ?? 0];
    $dayCursor->modify('+1 day');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Cashier Reports - FixTrack</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    .kpi-card { min-height: 128px; }
    .kpi-body { align-items: flex-start; height: 100%; padding: 18px; position: relative; }
    .kpi-label { color: var(--app-muted); font-size: .82rem; font-weight: 700; letter-spacing: 0; margin-bottom: .5rem; max-width: calc(100% - 48px); min-height: 2.2em; }
    .kpi-value { font-size: clamp(1.05rem, 1.8vw, 1.8rem); font-weight: 800; line-height: 1; margin-bottom: .55rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .kpi-note { color: var(--app-muted); font-size: .78rem; line-height: 1.3; margin-bottom: 0; }
    .kpi-icon { align-items: center; background: #fff1f2; border-radius: 8px; display: inline-flex; height: 42px; justify-content: center; position: absolute; right: 18px; top: 18px; width: 42px; }
    .chart-card { min-height:340px; padding:20px; }
    .chart-card canvas { height:245px !important; max-height:245px; }
    .chart-subtitle { color:#6c757d; font-size:.82rem; margin-bottom:18px; }
  </style>
</head>
<body class="bg-light">
  <?php include("navbarcashier.php"); ?>
  <div class="container mt-4">
    <div class="page-hero">
      <h3 class="mb-1">Cashier Reports</h3>
      <p>Payment status, monthly revenue, daily revenue, and transaction value.</p>
    </div>

    <div class="row g-3 mb-4">
      <div class="col-md-4">
        <div class="card kpi-card text-danger shadow-sm h-100"><div class="card-body kpi-body">
          <div>
            <p class="kpi-label">Average Transaction Value</p>
            <p class="kpi-value text-danger">PHP <?= number_format($averageTransaction, 2) ?></p>
            <p class="kpi-note">Completed paid repairs</p>
          </div>
          <div class="kpi-icon text-danger"><i class="bi bi-receipt"></i></div>
        </div></div>
      </div>
    </div>

    <div class="row g-3 mb-4">
      <div class="col-lg-4"><div class="card chart-card">
        <h5 class="mb-1"><i class="bi bi-credit-card me-2 text-danger"></i>Payment Status Breakdown</h5>
        <p class="chart-subtitle">Paid and pending booking payments</p>
        <canvas id="paymentStatusChart"></canvas>
      </div></div>
      <div class="col-lg-8"><div class="card chart-card">
        <h5 class="mb-1"><i class="bi bi-graph-up-arrow me-2 text-danger"></i>Monthly Revenue</h5>
        <p class="chart-subtitle">Completed repair revenue over the latest 6 months</p>
        <canvas id="monthlyRevenueChart"></canvas>
      </div></div>
    </div>

    <div class="row g-3 mb-4">
      <div class="col-12"><div class="card chart-card">
        <h5 class="mb-1"><i class="bi bi-calendar3 me-2 text-danger"></i>Daily Revenue</h5>
        <p class="chart-subtitle">Completed repair revenue over the last 30 days</p>
        <canvas id="dailyRevenueChart"></canvas>
      </div></div>
    </div>
  </div>

  <script>
    const paymentSummary = <?= json_encode($paymentSummary, JSON_NUMERIC_CHECK) ?>;
    const monthlyRevenue = <?= json_encode($monthlyRevenue, JSON_NUMERIC_CHECK) ?>;
    const dailyRevenue = <?= json_encode($dailyRevenue, JSON_NUMERIC_CHECK) ?>;
    const axisStyle = { grid: { color: 'rgba(108,117,125,.25)', borderDash: [3, 3] }, ticks: { color: '#6c757d' } };
    const legendStyle = { labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true }, position: 'bottom' };

    new Chart(document.getElementById('paymentStatusChart'), {
      type: 'doughnut',
      data: { labels: paymentSummary.map(row => row.label), datasets: [{ data: paymentSummary.map(row => row.total), backgroundColor: ['#ffc107', '#198754', '#6c757d'] }] },
      options: { responsive: true, maintainAspectRatio: false, cutout: '58%', plugins: { legend: legendStyle } }
    });
    new Chart(document.getElementById('monthlyRevenueChart'), {
      type: 'line',
      data: { labels: monthlyRevenue.map(row => row.month), datasets: [{ label: 'Revenue', data: monthlyRevenue.map(row => row.total), borderColor: '#dc3545', backgroundColor: 'rgba(220,53,69,.14)', tension: .4, fill: true, pointRadius: 4 }] },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: axisStyle, y: { ...axisStyle, beginAtZero: true, ticks: { callback: value => 'PHP ' + Number(value).toLocaleString('en-PH') } } } }
    });
    new Chart(document.getElementById('dailyRevenueChart'), {
      type: 'line',
      data: { labels: dailyRevenue.map(row => row.day), datasets: [{ label: 'Revenue', data: dailyRevenue.map(row => row.total), borderColor: '#0d6efd', backgroundColor: 'rgba(13,110,253,.12)', tension: .35, fill: true, pointRadius: 2 }] },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: axisStyle, y: { ...axisStyle, beginAtZero: true, ticks: { callback: value => 'PHP ' + Number(value).toLocaleString('en-PH') } } } }
    });

    window.setInterval(() => {
      if (document.visibilityState === 'visible') window.location.reload();
    }, 15000);
  </script>
</body>
</html>
