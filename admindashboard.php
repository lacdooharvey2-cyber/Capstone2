<?php
session_start();
include("db.php");
include_once("schema_helpers.php");
include_once("dashboard_alerts_logs.php");

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['AssistantAdmin', 'Admin', 'AssistantSuperAdmin', 'SuperAdmin'], true)) {
    header("Location: login.php");
    exit();
}

ensureRepairAutomationSchema($conn);
$isAssistantAdmin = ($_SESSION['role'] ?? '') === 'AssistantAdmin';

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

$dashboardBreakdowns = [
    'total_repairs' => [
        'title' => 'Total Repairs Breakdown',
        'headers' => ['ID', 'Customer', 'E-Bike', 'Status', 'Created'],
        'rows' => dashboardRows($conn, "
            SELECT r.repair_id, COALESCE(u.name, CONCAT('Customer #', r.customer_id)) AS customer, r.ebike_model, r.repair_status, r.created_at
            FROM repairs r LEFT JOIN users u ON u.user_id = r.customer_id
            ORDER BY r.created_at DESC LIMIT 50
        "),
        'fields' => ['repair_id', 'customer', 'ebike_model', 'repair_status', 'created_at'],
    ],
    'open_queue' => [
        'title' => 'Open Queue Breakdown',
        'headers' => ['ID', 'Customer', 'E-Bike', 'Status', 'Technician'],
        'rows' => dashboardRows($conn, "
            SELECT r.repair_id, COALESCE(c.name, CONCAT('Customer #', r.customer_id)) AS customer, r.ebike_model, r.repair_status, COALESCE(t.name, 'Unassigned') AS technician
            FROM repairs r
            LEFT JOIN users c ON c.user_id = r.customer_id
            LEFT JOIN users t ON t.user_id = r.technician_id
            WHERE r.repair_status IN ('Pending','In Progress')
            ORDER BY r.created_at ASC LIMIT 50
        "),
        'fields' => ['repair_id', 'customer', 'ebike_model', 'repair_status', 'technician'],
    ],
    'active_customers' => [
        'title' => 'Active Customers Breakdown',
        'headers' => ['Customer', 'Email', 'Repairs', 'Warranty Records'],
        'rows' => dashboardRows($conn, "
            SELECT u.name AS customer, u.email, COUNT(DISTINCT r.repair_id) AS repairs, COUNT(DISTINCT w.warranty_id) AS warranties
            FROM users u
            LEFT JOIN repairs r ON r.customer_id = u.user_id
            LEFT JOIN warranty_records w ON w.customer_id = u.user_id
            WHERE u.role='Customer'
            GROUP BY u.user_id, u.name, u.email
            HAVING repairs > 0 OR warranties > 0
            ORDER BY u.name LIMIT 50
        "),
        'fields' => ['customer', 'email', 'repairs', 'warranties'],
    ],
    'monthly_revenue' => [
        'title' => 'Monthly Revenue Breakdown',
        'headers' => ['ID', 'Customer', 'E-Bike', 'Amount', 'Completed'],
        'rows' => dashboardRows($conn, "
            SELECT r.repair_id, COALESCE(u.name, CONCAT('Customer #', r.customer_id)) AS customer, r.ebike_model, CONCAT('PHP ', FORMAT(r.amount, 2)) AS amount, r.updated_at
            FROM repairs r LEFT JOIN users u ON u.user_id = r.customer_id
            WHERE r.repair_status='Completed' AND r.updated_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
            ORDER BY r.updated_at DESC LIMIT 50
        "),
        'fields' => ['repair_id', 'customer', 'ebike_model', 'amount', 'updated_at'],
    ],
    'avg_turnaround' => [
        'title' => 'Turnaround Breakdown',
        'headers' => ['ID', 'Customer', 'Created', 'Completed', 'Days'],
        'rows' => dashboardRows($conn, "
            SELECT r.repair_id, COALESCE(u.name, CONCAT('Customer #', r.customer_id)) AS customer, r.created_at, r.updated_at, TIMESTAMPDIFF(DAY, r.created_at, r.updated_at) AS days
            FROM repairs r LEFT JOIN users u ON u.user_id = r.customer_id
            WHERE r.repair_status='Completed' AND r.updated_at IS NOT NULL
            ORDER BY r.updated_at DESC LIMIT 50
        "),
        'fields' => ['repair_id', 'customer', 'created_at', 'updated_at', 'days'],
    ],
    'cancellations_this_month' => [
        'title' => 'Cancellation Breakdown',
        'headers' => ['ID', 'Customer', 'E-Bike', 'Cancelled', 'Issue'],
        'rows' => dashboardRows($conn, "
            SELECT r.repair_id, COALESCE(u.name, CONCAT('Customer #', r.customer_id)) AS customer, r.ebike_model, r.updated_at, r.issue_description
            FROM repairs r LEFT JOIN users u ON u.user_id = r.customer_id
            WHERE r.repair_status='Cancelled' AND r.updated_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
            ORDER BY r.updated_at DESC LIMIT 50
        "),
        'fields' => ['repair_id', 'customer', 'ebike_model', 'updated_at', 'issue_description'],
    ],
];

if ($isAssistantAdmin) {
    unset($dashboardBreakdowns['monthly_revenue']);
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
    .kpi-icon { align-items: center; background: #e5f8e9; border-radius: 8px; display: inline-flex; height: 42px; justify-content: center; position: absolute; right: 18px; top: 18px; width: 42px; }
    .kpi-button { background: transparent; border: 0; padding: 0; text-align: left; width: 100%; }
    .kpi-button .card { cursor: pointer; transition: transform .18s ease, box-shadow .18s ease; }
    .kpi-button:hover .card { transform: translateY(-2px); box-shadow: 0 .7rem 1.4rem rgba(31,41,55,.12) !important; }
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
      <p><?= $isAssistantAdmin ? 'Repair queue, turnaround, cancellation, and brand activity overview.' : 'Repair queue, revenue, turnaround, cancellation, and brand activity overview.' ?></p>
    </div>
    <?php renderDashboardAlerts($conn, $_SESSION['role'], (int)$_SESSION['user_id']); ?>

    <div class="admin-kpi-grid mb-4">
      <?php
      $cards = [
        ['total_repairs', 'Total Repairs', $totalRepairs, 'All repair tickets', 'text-primary', 'bi-tools'],
        ['open_queue', 'Open Queue', $openQueue, $pendingRepairs . ' pending, ' . $inProgressRepairs . ' in progress', 'text-warning', 'bi-hourglass-split'],
        ['active_customers', 'Active Customers', $activeCustomers, 'With repair or e-bike record', 'text-primary', 'bi-people'],
        ['monthly_revenue', 'Monthly Revenue', 'PHP ' . number_format($monthlyRevenue, 0), 'Completed this month', 'text-success', 'bi-cash-stack'],
        ['avg_turnaround', 'Avg Turnaround', number_format($avgTurnaround, 1) . ' days', 'Completed repair cycle', 'text-danger', 'bi-speedometer2'],
        ['cancellations_this_month', 'Cancellations This Month', $cancellationsThisMonth, 'Cancelled repair tickets', 'text-secondary', 'bi-x-circle'],
      ];
      if ($isAssistantAdmin) {
        $cards = array_values(array_filter($cards, static fn (array $card): bool => $card[0] !== 'monthly_revenue'));
      }
      foreach ($cards as $card):
      ?>
        <div>
          <button type="button" class="kpi-button" <?= $isAssistantAdmin ? 'disabled aria-disabled="true"' : 'data-bs-toggle="modal" data-bs-target="#kpiModal-' . htmlspecialchars($card[0]) . '"' ?>>
          <div class="card kpi-card <?= $card[4] ?> shadow-sm h-100"><div class="card-body kpi-body">
            <div>
              <p class="kpi-label"><?= htmlspecialchars($card[1]) ?></p>
              <p class="kpi-value <?= $card[4] ?>" data-stat="<?= htmlspecialchars($card[0]) ?>"><?= htmlspecialchars((string)$card[2]) ?></p>
              <p class="kpi-note" <?= $card[0] === 'open_queue' ? 'data-stat-note="open_queue"' : '' ?>><?= htmlspecialchars($card[3]) ?></p>
            </div>
            <div class="kpi-icon <?= $card[4] ?>"><i class="bi <?= $card[5] ?>"></i></div>
          </div></div>
          </button>
        </div>
      <?php endforeach; ?>
    </div>

    <?php if (!$isAssistantAdmin): foreach ($dashboardBreakdowns as $key => $breakdown): ?>
      <div class="modal fade" id="kpiModal-<?= htmlspecialchars($key) ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title"><?= htmlspecialchars($breakdown['title']) ?></h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <div class="table-responsive">
                <table class="table table-sm table-hover align-middle">
                  <thead><tr><?php foreach ($breakdown['headers'] as $header): ?><th><?= htmlspecialchars($header) ?></th><?php endforeach; ?></tr></thead>
                  <tbody>
                    <?php if (!empty($breakdown['rows'])): foreach ($breakdown['rows'] as $row): ?>
                      <tr><?php foreach ($breakdown['fields'] as $field): ?><td><?= htmlspecialchars((string)($row[$field] ?? '')) ?></td><?php endforeach; ?></tr>
                    <?php endforeach; else: ?>
                      <tr><td colspan="<?= count($breakdown['headers']) ?>" class="text-center text-muted">No records found for this KPI.</td></tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; endif; ?>

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

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    const repairStatus = <?= json_encode($repairStatus, JSON_NUMERIC_CHECK) ?>;
    const monthlyRepairs = <?= json_encode($monthlyRepairs, JSON_NUMERIC_CHECK) ?>;
    const brandRepairs = <?= json_encode($brandRepairs, JSON_NUMERIC_CHECK) ?>;
    const colors = ['#003b16', '#146c43', '#198754', '#67b87a', '#b8e4c2', '#6c757d'];
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
        datasets: [{ label: 'Total Repairs', data: monthlyRepairs.map(row => row.total), borderColor: '#198754', backgroundColor: 'rgba(25,135,84,.12)', tension: .4, fill: true, pointRadius: 4 }]
      },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: legendStyle }, scales: { x: axisStyle, y: { ...axisStyle, beginAtZero: true, ticks: { ...axisStyle.ticks, precision: 0 } } } }
    });
    new Chart(document.getElementById('brandRepairsChart'), {
      type: 'bar',
      data: {
        labels: brandRepairs.map(row => row.brand),
        datasets: [{ label: 'Repairs', data: brandRepairs.map(row => row.total), backgroundColor: ['#003b16', '#198754', '#6c757d'], borderRadius: 8, maxBarThickness: 42 }]
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
