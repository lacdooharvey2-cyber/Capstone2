<?php
session_start();
include("db.php");
include_once("schema_helpers.php");
include_once("dashboard_alerts_logs.php");

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['Admin', 'SuperAdmin'], true)) {
    header("Location: login.php");
    exit();
}

ensureRepairAutomationSchema($conn);

function dashboardValue(mysqli $conn, string $sql, string $key = 'total')
{
    $result = $conn->query($sql);
    if (!$result) {
        return 0;
    }
    $row = $result->fetch_assoc();
    return $row[$key] ?? 0;
}

function dashboardRows(mysqli $conn, string $sql): array
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

$totalRepairs = (int)dashboardValue($conn, "SELECT COUNT(*) AS total FROM repairs");
$pendingRepairs = (int)dashboardValue($conn, "SELECT COUNT(*) AS total FROM repairs WHERE repair_status='Pending'");
$inProgressRepairs = (int)dashboardValue($conn, "SELECT COUNT(*) AS total FROM repairs WHERE repair_status='In Progress'");
$openQueue = $pendingRepairs + $inProgressRepairs;
$activeCustomers = (int)dashboardValue($conn, "
  SELECT COUNT(DISTINCT u.user_id) AS total
  FROM users u
  LEFT JOIN repairs r ON r.customer_id = u.user_id
  LEFT JOIN warranty_records w ON w.customer_id = u.user_id
  WHERE u.role='Customer' AND (r.repair_id IS NOT NULL OR w.warranty_id IS NOT NULL)
");
$monthlyRevenue = (float)dashboardValue($conn, "
  SELECT COALESCE(SUM(amount),0) AS total
  FROM repairs
  WHERE repair_status='Completed' AND updated_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
");
$avgTurnaround = (float)dashboardValue($conn, "
  SELECT COALESCE(AVG(TIMESTAMPDIFF(DAY, created_at, updated_at)),0) AS total
  FROM repairs
  WHERE repair_status='Completed' AND updated_at IS NOT NULL
");
$cancellationsThisMonth = (int)dashboardValue($conn, "
  SELECT COUNT(*) AS total
  FROM repairs
  WHERE repair_status='Cancelled' AND updated_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
");

$repairStatus = dashboardRows($conn, "
  SELECT repair_status AS label, COUNT(*) AS total
  FROM repairs
  GROUP BY repair_status
  ORDER BY FIELD(repair_status, 'Pending', 'In Progress', 'Completed', 'Cancelled'), repair_status
");
$monthlyRepairRows = dashboardRows($conn, "
  SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, COUNT(*) AS total
  FROM repairs
  WHERE created_at >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 11 MONTH), '%Y-%m-01')
  GROUP BY DATE_FORMAT(created_at, '%Y-%m')
  ORDER BY month
");
$brandRepairRows = dashboardRows($conn, "
  SELECT brand, COUNT(*) AS total
  FROM (
    SELECT CASE
      WHEN UPPER(ebike_model) LIKE '%NWOW%' THEN 'NWOW'
      WHEN UPPER(ebike_model) LIKE '%KUDA%' OR UPPER(ebike_model) LIKE '%KDA%' THEN 'KUDA/KDA'
      ELSE 'Other'
    END AS brand
    FROM repairs
  ) brand_data
  GROUP BY brand
  ORDER BY FIELD(brand, 'KUDA/KDA', 'NWOW', 'Other')
");

$monthlyTotals = [];
foreach ($monthlyRepairRows as $row) {
    $monthlyTotals[$row['month']] = (int)$row['total'];
}
$monthlyRepairs = [];
$monthCursor = new DateTime('first day of -11 months');
for ($i = 0; $i < 12; $i++) {
    $monthKey = $monthCursor->format('Y-m');
    $monthlyRepairs[] = ['month' => $monthKey, 'total' => $monthlyTotals[$monthKey] ?? 0];
    $monthCursor->modify('+1 month');
}

$brandTotals = ['KUDA/KDA' => 0, 'NWOW' => 0, 'Other' => 0];
foreach ($brandRepairRows as $row) {
    $brandTotals[$row['brand']] = (int)$row['total'];
}
$brandRepairs = [];
foreach ($brandTotals as $brand => $total) {
    $brandRepairs[] = ['brand' => $brand, 'total' => $total];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Dashboard - FixTrack</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    .admin-kpi-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; }
    .kpi-card { min-height: 142px; }
    .kpi-body { align-items: flex-start; height: 100%; padding: 18px; position: relative; }
    .kpi-label { color: var(--app-muted); font-size: .82rem; font-weight: 700; letter-spacing: 0; margin-bottom: .5rem; max-width: calc(100% - 48px); min-height: 2.2em; }
    .kpi-value { font-size: clamp(1.1rem, 2vw, 1.8rem); font-weight: 800; line-height: 1.08; margin-bottom: .55rem; overflow-wrap: anywhere; }
    .kpi-note { color: var(--app-muted); font-size: .78rem; line-height: 1.3; margin-bottom: 0; }
    .kpi-icon { align-items: center; background: #fff1f2; border-radius: 8px; display: inline-flex; height: 42px; justify-content: center; position: absolute; right: 18px; top: 18px; width: 42px; }
    @media (max-width: 900px) { .admin-kpi-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 575px) { .admin-kpi-grid { grid-template-columns: 1fr; } }
    .chart-card { min-height: 340px; padding: 20px; }
    .chart-card h6 { font-size: .95rem; margin-bottom: 4px; }
    .chart-subtitle { color: #6c757d; font-size: .78rem; margin-bottom: 18px; }
    .chart-card canvas { height: 245px !important; max-height: 245px; }
  </style>
</head>
<body>
  <?php include("navbaradmin.php"); ?>
  <div class="container mt-4">
    <div class="page-hero">
      <h3 class="mb-1">Admin Dashboard</h3>
      <p>Repair queue, revenue, turnaround, cancellation, and brand activity overview.</p>
    </div>
    <?php renderDashboardAlerts($conn, $_SESSION['role'], (int)$_SESSION['user_id']); ?>

    <div class="admin-kpi-grid mb-4">
      <?php
      $cards = [
        ['total_repairs', 'Total Repairs', $totalRepairs, 'All repair tickets', 'text-danger', 'bi-tools'],
        ['open_queue', 'Open Queue', $openQueue, $pendingRepairs . ' pending, ' . $inProgressRepairs . ' in progress', 'text-warning', 'bi-hourglass-split'],
        ['active_customers', 'Active Customers', $activeCustomers, 'With repair or e-bike record', 'text-success', 'bi-people'],
        ['monthly_revenue', 'Monthly Revenue', 'PHP ' . number_format($monthlyRevenue, 0), 'Completed this month', 'text-primary', 'bi-cash-stack'],
        ['avg_turnaround', 'Avg Turnaround', number_format($avgTurnaround, 1) . ' days', 'Completed repair cycle', 'text-info', 'bi-speedometer2'],
        ['cancellations_this_month', 'Cancellations This Month', $cancellationsThisMonth, 'Cancelled repair tickets', 'text-secondary', 'bi-x-circle'],
      ];
      foreach ($cards as $card):
      ?>
        <div>
          <div class="card kpi-card <?= $card[4] ?> shadow-sm h-100"><div class="card-body kpi-body">
            <div>
              <p class="kpi-label"><?= htmlspecialchars($card[1]) ?></p>
              <p class="kpi-value <?= $card[4] ?>" data-stat="<?= htmlspecialchars($card[0]) ?>"><?= htmlspecialchars((string)$card[2]) ?></p>
              <p class="kpi-note" <?= $card[0] === 'open_queue' ? 'data-stat-note="open_queue"' : '' ?>><?= htmlspecialchars($card[3]) ?></p>
            </div>
            <div class="kpi-icon <?= $card[4] ?>"><i class="bi <?= $card[5] ?>"></i></div>
          </div></div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="row g-3 mb-4">
      <div class="col-lg-4"><div class="card chart-card">
        <h6 class="fw-bold">Repair Status Breakdown</h6>
        <p class="chart-subtitle">Current repair pipeline distribution</p>
        <canvas id="repairStatusChart"></canvas>
      </div></div>
      <div class="col-lg-8"><div class="card chart-card">
        <h6 class="fw-bold">Monthly Repair Volume</h6>
        <p class="chart-subtitle">Last 12 months of repair tickets</p>
        <canvas id="monthlyRepairsChart"></canvas>
      </div></div>
    </div>
    <div class="row g-3 mb-4">
      <div class="col-lg-6"><div class="card chart-card">
        <h6 class="fw-bold">Repairs by Brand</h6>
        <p class="chart-subtitle">KUDA/KDA, NWOW, and other repair tickets</p>
        <canvas id="brandRepairsChart"></canvas>
      </div></div>
    </div>
  </div>

  <script>
    const repairStatus = <?= json_encode($repairStatus, JSON_NUMERIC_CHECK) ?>;
    const monthlyRepairs = <?= json_encode($monthlyRepairs, JSON_NUMERIC_CHECK) ?>;
    const brandRepairs = <?= json_encode($brandRepairs, JSON_NUMERIC_CHECK) ?>;
    const colors = ['#d62828', '#0d6efd', '#198754', '#f77f00', '#6c757d', '#17a2b8'];
    const gridStyle = { color: 'rgba(108,117,125,.28)', borderDash: [3, 3], drawTicks: false };
    const axisStyle = { grid: gridStyle, border: { color: 'rgba(108,117,125,.45)' }, ticks: { color: '#6c757d', padding: 8 } };
    const legendStyle = { labels: { boxWidth: 10, boxHeight: 10, color: '#495057', usePointStyle: true }, position: 'bottom' };

    new Chart(document.getElementById('repairStatusChart'), {
      type: 'doughnut',
      data: {
        labels: repairStatus.map(row => row.label),
        datasets: [{ data: repairStatus.map(row => row.total), backgroundColor: colors }]
      },
      options: { responsive: true, maintainAspectRatio: false, cutout: '58%', plugins: { legend: legendStyle } }
    });
    new Chart(document.getElementById('monthlyRepairsChart'), {
      type: 'line',
      data: {
        labels: monthlyRepairs.map(row => row.month),
        datasets: [{ label: 'Total Repairs', data: monthlyRepairs.map(row => row.total), borderColor: '#d62828', backgroundColor: 'rgba(214,40,40,.12)', tension: .4, fill: true, pointRadius: 4 }]
      },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: legendStyle }, scales: { x: axisStyle, y: { ...axisStyle, beginAtZero: true, ticks: { ...axisStyle.ticks, precision: 0 } } } }
    });
    new Chart(document.getElementById('brandRepairsChart'), {
      type: 'bar',
      data: {
        labels: brandRepairs.map(row => row.brand),
        datasets: [{ label: 'Repairs', data: brandRepairs.map(row => row.total), backgroundColor: ['#d62828', '#0d6efd', '#6c757d'], borderRadius: 8, maxBarThickness: 42 }]
      },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: axisStyle, y: { ...axisStyle, beginAtZero: true, ticks: { ...axisStyle.ticks, precision: 0 } } } }
    });

    function formatPeso(value) {
      return 'PHP ' + Number(value || 0).toLocaleString('en-PH', { maximumFractionDigits: 0 });
    }

    function updateAdminStats(stats) {
      const formatters = {
        monthly_revenue: formatPeso,
        avg_turnaround: (value) => Number(value || 0).toFixed(1) + ' days'
      };

      document.querySelectorAll('[data-stat]').forEach((element) => {
        const key = element.dataset.stat;
        if (!(key in stats)) return;
        element.textContent = formatters[key] ? formatters[key](stats[key]) : stats[key];
      });

      const openQueueNote = document.querySelector('[data-stat-note="open_queue"]');
      if (openQueueNote) {
        openQueueNote.textContent = `${stats.pending_repairs || 0} pending, ${stats.in_progress_repairs || 0} in progress`;
      }
    }

    async function refreshAdminStats() {
      if (document.visibilityState !== 'visible') return;
      try {
        const response = await fetch('admin_stats_api.php', { cache: 'no-store' });
        if (!response.ok) return;
        updateAdminStats(await response.json());
      } catch (error) {
        console.warn('Unable to refresh admin stats', error);
      }
    }

    window.setInterval(refreshAdminStats, 15000);
    refreshAdminStats();
  </script>
</body>
</html>
