<?php
session_start();
include("db.php");
include_once("schema_helpers.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

ensureRepairAutomationSchema($conn);

function analyticsValue(mysqli $conn, string $sql, string $key = 'total')
{
    $result = $conn->query($sql);
    if (!$result) {
        return 0;
    }
    $row = $result->fetch_assoc();
    return $row[$key] ?? 0;
}

function analyticsRows(mysqli $conn, string $sql): array
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

function analyticsPct($part, $total): float
{
    return (float)$total > 0 ? round(((float)$part / (float)$total) * 100, 1) : 0.0;
}

$totalRepairs = (int)analyticsValue($conn, "SELECT COUNT(*) AS total FROM repairs");
$completedRepairs = (int)analyticsValue($conn, "SELECT COUNT(*) AS total FROM repairs WHERE repair_status='Completed'");
$totalRevenue = (float)analyticsValue($conn, "SELECT COALESCE(SUM(amount),0) AS total FROM repairs WHERE repair_status='Completed'");
$monthlyRevenue = (float)analyticsValue($conn, "
  SELECT COALESCE(SUM(amount),0) AS total
  FROM repairs
  WHERE repair_status='Completed' AND updated_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
");
$averageRevenue = (float)analyticsValue($conn, "SELECT COALESCE(AVG(NULLIF(amount,0)),0) AS total FROM repairs WHERE repair_status='Completed'");
$completionRate = analyticsPct($completedRepairs, $totalRepairs);
$pendingPayments = (int)analyticsValue($conn, "SELECT COUNT(*) AS total FROM repair_bookings WHERE payment_status='Pending'");
$kudaUsers = (int)analyticsValue($conn, "SELECT COUNT(DISTINCT customer_id) AS total FROM warranty_records WHERE UPPER(ebike_model) LIKE '%KUDA%' OR UPPER(ebike_model) LIKE '%KDA%'");
$nwowUsers = (int)analyticsValue($conn, "SELECT COUNT(DISTINCT customer_id) AS total FROM warranty_records WHERE UPPER(ebike_model) LIKE '%NWOW%'");
$warrantyClaims = (int)analyticsValue($conn, "SELECT COUNT(*) AS total FROM warranty_records WHERE warranty_status='Claimed'");
$totalWarranties = (int)analyticsValue($conn, "SELECT COUNT(*) AS total FROM warranty_records");
$warrantyClaimRate = analyticsPct($warrantyClaims, $totalWarranties);

$brandUserRows = analyticsRows($conn, "
  SELECT brand, COUNT(DISTINCT customer_id) AS users
  FROM (
    SELECT customer_id,
           CASE
             WHEN UPPER(ebike_model) LIKE '%NWOW%' THEN 'NWOW'
             WHEN UPPER(ebike_model) LIKE '%KUDA%' OR UPPER(ebike_model) LIKE '%KDA%' THEN 'KUDA/KDA'
             ELSE 'Other'
           END AS brand
    FROM warranty_records
  ) brand_records
  GROUP BY brand
");

$commonIssues = analyticsRows($conn, "
  SELECT issue_category, COUNT(*) AS total
  FROM (
    SELECT CASE
      WHEN LOWER(issue_description) LIKE '%battery%' THEN 'Battery'
      WHEN LOWER(issue_description) LIKE '%motor%' THEN 'Motor'
      WHEN LOWER(issue_description) LIKE '%brake%' THEN 'Brake'
      WHEN LOWER(issue_description) LIKE '%tire%' OR LOWER(issue_description) LIKE '%wheel%' THEN 'Tire/Wheel'
      WHEN LOWER(issue_description) LIKE '%wiring%' OR LOWER(issue_description) LIKE '%electrical%' THEN 'Wiring/Electrical'
      WHEN LOWER(issue_description) LIKE '%charger%' OR LOWER(issue_description) LIKE '%charge%' THEN 'Charger'
      WHEN LOWER(issue_description) LIKE '%controller%' THEN 'Controller'
      WHEN LOWER(issue_description) LIKE '%display%' THEN 'Display'
      WHEN LOWER(issue_description) LIKE '%throttle%' THEN 'Throttle'
      ELSE 'Other'
    END AS issue_category
    FROM repairs
  ) issue_data
  GROUP BY issue_category
  ORDER BY total DESC
  LIMIT 8
");

$monthlyRevenueRows = analyticsRows($conn, "
  SELECT DATE_FORMAT(created_at, '%Y-%m') AS month,
         COALESCE(SUM(CASE WHEN repair_status='Completed' THEN amount ELSE 0 END),0) AS revenue,
         SUM(CASE WHEN repair_status='Completed' THEN 1 ELSE 0 END) AS completed_repairs
  FROM repairs
  WHERE created_at >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 11 MONTH), '%Y-%m-01')
  GROUP BY DATE_FORMAT(created_at, '%Y-%m')
  ORDER BY month
");

$revenueRangeRows = analyticsRows($conn, "
  SELECT revenue_group, COUNT(*) AS repairs
  FROM (
    SELECT CASE
      WHEN COALESCE(amount,0) = 0 THEN 'No Charge/Pending'
      WHEN amount < 500 THEN 'Below PHP 500'
      WHEN amount BETWEEN 500 AND 999.99 THEN 'PHP 500-999'
      WHEN amount BETWEEN 1000 AND 1999.99 THEN 'PHP 1,000-1,999'
      ELSE 'PHP 2,000+'
    END AS revenue_group
    FROM repairs
    WHERE repair_status='Completed'
  ) revenue_data
  GROUP BY revenue_group
");

$weeklyCompletionRows = analyticsRows($conn, "
  SELECT DATE_FORMAT(created_at, '%x-W%v') AS week_label,
         COUNT(*) AS total_repairs,
         SUM(CASE WHEN repair_status='Completed' THEN 1 ELSE 0 END) AS completed_repairs
  FROM repairs
  WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 8 WEEK)
  GROUP BY DATE_FORMAT(created_at, '%x-W%v')
  ORDER BY week_label
");

$turnaroundRows = analyticsRows($conn, "
  SELECT DATE_FORMAT(updated_at, '%Y-%m') AS month,
         COALESCE(AVG(TIMESTAMPDIFF(DAY, created_at, updated_at)),0) AS avg_days
  FROM repairs
  WHERE repair_status='Completed'
    AND updated_at IS NOT NULL
    AND updated_at >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 11 MONTH), '%Y-%m-01')
  GROUP BY DATE_FORMAT(updated_at, '%Y-%m')
  ORDER BY month
");

$technicianPerformance = analyticsRows($conn, "
  SELECT COALESCE(u.name, 'Unassigned') AS technician, COUNT(*) AS completed_repairs
  FROM repairs r
  LEFT JOIN users u ON u.user_id = r.technician_id
  WHERE r.repair_status='Completed'
  GROUP BY technician
  ORDER BY completed_repairs DESC
  LIMIT 8
");

$warrantyVsPaid = analyticsRows($conn, "
  SELECT charge_type AS label, COUNT(*) AS total
  FROM (
    SELECT CASE WHEN warranty_status='Valid' THEN 'Warranty' ELSE 'Paid' END AS charge_type
    FROM repairs
    WHERE repair_status='Completed'
  ) charge_data
  GROUP BY charge_type
");

$brandTotals = ['KUDA/KDA' => 0, 'NWOW' => 0, 'Other' => 0];
foreach ($brandUserRows as $row) {
    $brandTotals[$row['brand']] = (int)$row['users'];
}
$brandUsers = [];
foreach ($brandTotals as $brand => $users) {
    $brandUsers[] = ['brand' => $brand, 'users' => $users];
}

$monthlyTotals = [];
foreach ($monthlyRevenueRows as $row) {
    $monthlyTotals[$row['month']] = [
        'revenue' => (float)$row['revenue'],
        'completed_repairs' => (int)$row['completed_repairs'],
    ];
}
$turnaroundTotals = [];
foreach ($turnaroundRows as $row) {
    $turnaroundTotals[$row['month']] = (float)$row['avg_days'];
}
$monthlyRevenueTrend = [];
$turnaroundTrend = [];
$monthCursor = new DateTime('first day of -11 months');
for ($i = 0; $i < 12; $i++) {
    $monthKey = $monthCursor->format('Y-m');
    $monthlyRevenueTrend[] = [
        'month' => $monthKey,
        'revenue' => $monthlyTotals[$monthKey]['revenue'] ?? 0,
        'completed_repairs' => $monthlyTotals[$monthKey]['completed_repairs'] ?? 0,
    ];
    $turnaroundTrend[] = [
        'month' => $monthKey,
        'avg_days' => $turnaroundTotals[$monthKey] ?? 0,
    ];
    $monthCursor->modify('+1 month');
}

$revenueGroups = [
    'No Charge/Pending' => 0,
    'Below PHP 500' => 0,
    'PHP 500-999' => 0,
    'PHP 1,000-1,999' => 0,
    'PHP 2,000+' => 0,
];
foreach ($revenueRangeRows as $row) {
    $revenueGroups[$row['revenue_group']] = (int)$row['repairs'];
}
$revenueByRange = [];
foreach ($revenueGroups as $range => $repairs) {
    $revenueByRange[] = ['range' => $range, 'repairs' => $repairs];
}

$weeklyTotals = [];
foreach ($weeklyCompletionRows as $row) {
    $total = (int)$row['total_repairs'];
    $completed = (int)$row['completed_repairs'];
    $weeklyTotals[$row['week_label']] = [
        'total_repairs' => $total,
        'completed_repairs' => $completed,
        'completion_rate' => analyticsPct($completed, $total),
    ];
}
$weeklyCompletion = [];
$weekCursor = new DateTime('monday this week');
$weekCursor->modify('-7 weeks');
for ($i = 0; $i < 8; $i++) {
    $weekKey = $weekCursor->format('o-\WW');
    $weeklyCompletion[] = [
        'week_label' => $weekKey,
        'total_repairs' => $weeklyTotals[$weekKey]['total_repairs'] ?? 0,
        'completed_repairs' => $weeklyTotals[$weekKey]['completed_repairs'] ?? 0,
        'completion_rate' => $weeklyTotals[$weekKey]['completion_rate'] ?? 0,
    ];
    $weekCursor->modify('+1 week');
}

$chargeTotals = ['Warranty' => 0, 'Paid' => 0];
foreach ($warrantyVsPaid as $row) {
    $chargeTotals[$row['label']] = (int)$row['total'];
}
$warrantyPaidBreakdown = [];
foreach ($chargeTotals as $label => $total) {
    $warrantyPaidBreakdown[] = ['label' => $label, 'total' => $total];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Analytics - FixTrack</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    .kpi-card { min-height: 128px; }
    .kpi-row { display: grid; gap: 16px; grid-template-columns: repeat(1, minmax(0, 1fr)); }
    .kpi-col { min-width: 0; }
    .kpi-card { min-width: 0; }
    .kpi-body { align-items: flex-start; height: 100%; padding: 18px; position: relative; }
    .kpi-label { color: var(--app-muted); font-size: .82rem; font-weight: 700; letter-spacing: 0; margin-bottom: .5rem; max-width: calc(100% - 48px); min-height: 2.2em; }
    .kpi-value { font-size: clamp(1.05rem, 1.8vw, 1.8rem); font-weight: 800; line-height: 1; margin-bottom: .55rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .kpi-note { color: var(--app-muted); font-size: .78rem; line-height: 1.3; margin-bottom: 0; }
    .kpi-icon { align-items: center; background: #fff1f2; border-radius: 8px; display: inline-flex; height: 42px; justify-content: center; position: absolute; right: 18px; top: 18px; width: 42px; }
    .chart-card { min-height: 350px; padding: 20px; }
    .chart-card h6 { font-size: .95rem; margin-bottom: 4px; }
    .chart-subtitle { color: #6c757d; font-size: .78rem; margin-bottom: 18px; }
    .chart-card canvas { height: 255px !important; max-height: 255px; }
    .chart-card.small-chart { min-height: 300px; }
    .chart-card.small-chart canvas { height: 205px !important; max-height: 205px; }
    @media (min-width: 576px) {
      .kpi-row { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (min-width: 992px) {
      .kpi-row { grid-template-columns: repeat(4, minmax(0, 1fr)); }
    }
    @media (min-width: 1200px) {
      .kpi-row { grid-template-columns: repeat(7, minmax(0, 1fr)); }
    }
  </style>
</head>
<body>
  <?php include("navbaradmin.php"); ?>
  <div class="container mt-4">
    <div class="page-hero">
      <h3 class="mb-1">Analytics</h3>
      <p>Revenue quality, repair completion, technician performance, and warranty behavior.</p>
    </div>

    <div class="kpi-row mb-4">
      <?php
      $cards = [
        ['Total Revenue', 'PHP ' . number_format($totalRevenue, 0), 'Completed repairs', 'text-primary', 'bi-cash-stack'],
        ['Monthly Revenue', 'PHP ' . number_format($monthlyRevenue, 0), 'Current month', 'text-info', 'bi-calendar3'],
        ['Avg Revenue / Repair', 'PHP ' . number_format($averageRevenue, 0), 'Priced completed jobs', 'text-dark', 'bi-receipt'],
        ['Completion Rate', $completionRate . '%', $completedRepairs . ' completed', 'text-success', 'bi-check2-circle'],
        ['Pending Payments', $pendingPayments, 'Bookings awaiting payment', 'text-warning', 'bi-clock-history'],
        ['KUDA vs NWOW Users', $kudaUsers . ' / ' . $nwowUsers, 'KUDA/KDA vs NWOW', 'text-danger', 'bi-bicycle'],
        ['Warranty Claim Rate', $warrantyClaimRate . '%', $warrantyClaims . ' of ' . $totalWarranties . ' warranties', 'text-secondary', 'bi-shield-check'],
      ];
      foreach ($cards as $card):
      ?>
        <div class="kpi-col">
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

    <div class="row g-3 mb-4">
      <div class="col-lg-6"><div class="card chart-card">
        <h6 class="fw-bold">Registered Owners by Brand</h6>
        <p class="chart-subtitle">KUDA/KDA, NWOW, and other registered owners</p>
        <canvas id="brandOwnersChart"></canvas>
      </div></div>
      <div class="col-lg-6"><div class="card chart-card">
        <h6 class="fw-bold">Common Issue Categories</h6>
        <p class="chart-subtitle">Grouped from repair issue descriptions</p>
        <canvas id="issueCategoryChart"></canvas>
      </div></div>
    </div>

    <div class="row g-3 mb-4">
      <div class="col-lg-8"><div class="card chart-card">
        <h6 class="fw-bold">Monthly Revenue + Completed Repairs</h6>
        <p class="chart-subtitle">Revenue bars with completed repair line</p>
        <canvas id="monthlyRevenueChart"></canvas>
      </div></div>
      <div class="col-lg-4"><div class="card chart-card">
        <h6 class="fw-bold">Revenue by Value Range</h6>
        <p class="chart-subtitle">Completed repairs grouped by price band</p>
        <canvas id="revenueRangeChart"></canvas>
      </div></div>
    </div>

    <div class="row g-3 mb-4">
      <div class="col-lg-8"><div class="card chart-card">
        <h6 class="fw-bold">Weekly Completion Volume</h6>
        <p class="chart-subtitle">Stacked open remainder and completed jobs</p>
        <canvas id="weeklyCompletionBarChart"></canvas>
      </div></div>
      <div class="col-lg-4"><div class="card chart-card small-chart">
        <h6 class="fw-bold">Weekly Completion Rate</h6>
        <p class="chart-subtitle">Completion percentage by week</p>
        <canvas id="weeklyCompletionLineChart"></canvas>
      </div></div>
    </div>

    <div class="row g-3 mb-4">
      <div class="col-lg-6"><div class="card chart-card">
        <h6 class="fw-bold">Avg Turnaround Time Trend</h6>
        <p class="chart-subtitle">Average days from created to completed</p>
        <canvas id="turnaroundTrendChart"></canvas>
      </div></div>
      <div class="col-lg-6"><div class="card chart-card">
        <h6 class="fw-bold">Technician Performance</h6>
        <p class="chart-subtitle">Completed repairs per technician</p>
        <canvas id="technicianPerformanceChart"></canvas>
      </div></div>
    </div>

    <div class="row g-3 mb-4">
      <div class="col-lg-4"><div class="card chart-card">
        <h6 class="fw-bold">Warranty Claim Rate</h6>
        <p class="chart-subtitle">Completed repairs covered by warranty vs paid</p>
        <canvas id="warrantyPaidChart"></canvas>
      </div></div>
    </div>
  </div>

  <script>
    const brandUsers = <?= json_encode($brandUsers, JSON_NUMERIC_CHECK) ?>;
    const commonIssues = <?= json_encode($commonIssues, JSON_NUMERIC_CHECK) ?>;
    const monthlyRevenueTrend = <?= json_encode($monthlyRevenueTrend, JSON_NUMERIC_CHECK) ?>;
    const revenueByRange = <?= json_encode($revenueByRange, JSON_NUMERIC_CHECK) ?>;
    const weeklyCompletion = <?= json_encode($weeklyCompletion, JSON_NUMERIC_CHECK) ?>;
    const turnaroundTrend = <?= json_encode($turnaroundTrend, JSON_NUMERIC_CHECK) ?>;
    const technicianPerformance = <?= json_encode($technicianPerformance, JSON_NUMERIC_CHECK) ?>;
    const warrantyPaidBreakdown = <?= json_encode($warrantyPaidBreakdown, JSON_NUMERIC_CHECK) ?>;
    const colors = ['#d62828', '#0d6efd', '#198754', '#f77f00', '#17a2b8', '#6c757d', '#6f42c1', '#20c997'];
    const gridStyle = { color: 'rgba(108,117,125,.28)', borderDash: [3, 3], drawTicks: false };
    const axisStyle = { grid: gridStyle, border: { color: 'rgba(108,117,125,.45)' }, ticks: { color: '#6c757d', padding: 8 } };
    const legendStyle = { labels: { boxWidth: 10, boxHeight: 10, color: '#495057', usePointStyle: true }, position: 'bottom' };

    new Chart(document.getElementById('brandOwnersChart'), {
      type: 'bar',
      data: { labels: brandUsers.map(row => row.brand), datasets: [{ label: 'Owners', data: brandUsers.map(row => row.users), backgroundColor: ['#d62828', '#0d6efd', '#6c757d'], borderRadius: 8, maxBarThickness: 40 }] },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: axisStyle, y: { ...axisStyle, beginAtZero: true, ticks: { ...axisStyle.ticks, precision: 0 } } } }
    });
    new Chart(document.getElementById('issueCategoryChart'), {
      type: 'bar',
      data: { labels: commonIssues.map(row => row.issue_category), datasets: [{ label: 'Repairs', data: commonIssues.map(row => row.total), backgroundColor: colors, borderRadius: 8, maxBarThickness: 24 }] },
      options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { ...axisStyle, beginAtZero: true, ticks: { ...axisStyle.ticks, precision: 0 } }, y: axisStyle } }
    });
    new Chart(document.getElementById('monthlyRevenueChart'), {
      data: {
        labels: monthlyRevenueTrend.map(row => row.month),
        datasets: [
          { type: 'bar', label: 'Revenue', data: monthlyRevenueTrend.map(row => row.revenue), backgroundColor: 'rgba(13,110,253,.72)', borderRadius: 8, maxBarThickness: 34, yAxisID: 'y' },
          { type: 'line', label: 'Completed Repairs', data: monthlyRevenueTrend.map(row => row.completed_repairs), borderColor: '#198754', backgroundColor: 'rgba(25,135,84,.12)', tension: .4, fill: true, pointRadius: 4, yAxisID: 'y1' }
        ]
      },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: legendStyle }, scales: { x: axisStyle, y: { ...axisStyle, beginAtZero: true, position: 'left' }, y1: { ...axisStyle, beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, ticks: { ...axisStyle.ticks, precision: 0 } } } }
    });
    new Chart(document.getElementById('revenueRangeChart'), {
      type: 'bar',
      data: { labels: revenueByRange.map(row => row.range), datasets: [{ label: 'Repairs', data: revenueByRange.map(row => row.repairs), backgroundColor: colors, borderRadius: 8, maxBarThickness: 32 }] },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: axisStyle, y: { ...axisStyle, beginAtZero: true, ticks: { ...axisStyle.ticks, precision: 0 } } } }
    });
    new Chart(document.getElementById('weeklyCompletionBarChart'), {
      type: 'bar',
      data: {
        labels: weeklyCompletion.map(row => row.week_label),
        datasets: [
          { label: 'Completed', data: weeklyCompletion.map(row => row.completed_repairs), backgroundColor: 'rgba(25,135,84,.72)', borderRadius: 8 },
          { label: 'Remaining', data: weeklyCompletion.map(row => Math.max(row.total_repairs - row.completed_repairs, 0)), backgroundColor: 'rgba(214,40,40,.55)', borderRadius: 8 }
        ]
      },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: legendStyle }, scales: { x: { ...axisStyle, stacked: true }, y: { ...axisStyle, stacked: true, beginAtZero: true, ticks: { ...axisStyle.ticks, precision: 0 } } } }
    });
    new Chart(document.getElementById('weeklyCompletionLineChart'), {
      type: 'line',
      data: { labels: weeklyCompletion.map(row => row.week_label), datasets: [{ label: 'Completion Rate %', data: weeklyCompletion.map(row => row.completion_rate), borderColor: '#0d6efd', backgroundColor: 'rgba(13,110,253,.12)', tension: .4, fill: true, pointRadius: 4 }] },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: legendStyle }, scales: { x: axisStyle, y: { ...axisStyle, beginAtZero: true, max: 100 } } }
    });
    new Chart(document.getElementById('turnaroundTrendChart'), {
      type: 'line',
      data: { labels: turnaroundTrend.map(row => row.month), datasets: [{ label: 'Avg Days', data: turnaroundTrend.map(row => row.avg_days), borderColor: '#f77f00', backgroundColor: 'rgba(247,127,0,.12)', tension: .4, fill: true, pointRadius: 4 }] },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: legendStyle }, scales: { x: axisStyle, y: { ...axisStyle, beginAtZero: true } } }
    });
    new Chart(document.getElementById('technicianPerformanceChart'), {
      type: 'bar',
      data: { labels: technicianPerformance.map(row => row.technician), datasets: [{ label: 'Completed Repairs', data: technicianPerformance.map(row => row.completed_repairs), backgroundColor: 'rgba(214,40,40,.7)', borderRadius: 8, maxBarThickness: 26 }] },
      options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { ...axisStyle, beginAtZero: true, ticks: { ...axisStyle.ticks, precision: 0 } }, y: axisStyle } }
    });
    new Chart(document.getElementById('warrantyPaidChart'), {
      type: 'doughnut',
      data: { labels: warrantyPaidBreakdown.map(row => row.label), datasets: [{ data: warrantyPaidBreakdown.map(row => row.total), backgroundColor: ['#198754', '#0d6efd'] }] },
      options: { responsive: true, maintainAspectRatio: false, cutout: '58%', plugins: { legend: legendStyle } }
    });
  </script>
</body>
</html>
