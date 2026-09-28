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
  SELECT DATE_FORMAT(updated_at, '%Y-%m') AS month,
         COALESCE(SUM(CASE WHEN repair_status='Completed' THEN amount ELSE 0 END),0) AS revenue,
         SUM(CASE WHEN repair_status='Completed' THEN 1 ELSE 0 END) AS completed_repairs
  FROM repairs
  WHERE updated_at >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 11 MONTH), '%Y-%m-01')
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


$warrantyClaimStatuses = analyticsRows($conn, "
  SELECT CASE WHEN warranty_status='Claimed' THEN 'Claimed' ELSE 'Not Claimed' END AS label, COUNT(*) AS total
  FROM warranty_records
  GROUP BY CASE WHEN warranty_status='Claimed' THEN 'Claimed' ELSE 'Not Claimed' END
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
$monthlyRevenueTrend = [];
$monthCursor = new DateTime('first day of -11 months');
for ($i = 0; $i < 12; $i++) {
    $monthKey = $monthCursor->format('Y-m');
    $monthlyRevenueTrend[] = [
        'month' => $monthKey,
        'revenue' => $monthlyTotals[$monthKey]['revenue'] ?? 0,
        'completed_repairs' => $monthlyTotals[$monthKey]['completed_repairs'] ?? 0,
    ];
    $monthCursor->modify('+1 month');
}

$chargeTotals = ['Claimed' => 0, 'Not Claimed' => 0];
foreach ($warrantyClaimStatuses as $row) {
    $chargeTotals[$row['label']] = (int)$row['total'];
}
$warrantyPaidBreakdown = [];
foreach ($chargeTotals as $label => $total) {
    $warrantyPaidBreakdown[] = ['label' => $label, 'total' => $total];
}

$analyticsBreakdowns = [
    'total_revenue' => [
        'title' => 'Total Revenue Breakdown',
        'headers' => ['ID', 'Customer', 'E-Bike', 'Amount', 'Completed'],
        'rows' => analyticsRows($conn, "
            SELECT r.repair_id, COALESCE(u.name, CONCAT('Customer #', r.customer_id)) AS customer, r.ebike_model, CONCAT('PHP ', FORMAT(r.amount, 2)) AS amount, r.updated_at
            FROM repairs r LEFT JOIN users u ON u.user_id = r.customer_id
            WHERE r.repair_status='Completed'
            ORDER BY r.updated_at DESC LIMIT 50
        "),
        'fields' => ['repair_id', 'customer', 'ebike_model', 'amount', 'updated_at'],
    ],
    'monthly_revenue' => [
        'title' => 'Monthly Revenue Breakdown',
        'headers' => ['ID', 'Customer', 'Amount', 'Completed'],
        'rows' => analyticsRows($conn, "
            SELECT r.repair_id, COALESCE(u.name, CONCAT('Customer #', r.customer_id)) AS customer, CONCAT('PHP ', FORMAT(r.amount, 2)) AS amount, r.updated_at
            FROM repairs r LEFT JOIN users u ON u.user_id = r.customer_id
            WHERE r.repair_status='Completed' AND r.updated_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
            ORDER BY r.updated_at DESC LIMIT 50
        "),
        'fields' => ['repair_id', 'customer', 'amount', 'updated_at'],
    ],
    'average_revenue' => [
        'title' => 'Average Revenue / Repair Breakdown',
        'headers' => ['ID', 'Customer', 'Amount', 'Status'],
        'rows' => analyticsRows($conn, "
            SELECT r.repair_id, COALESCE(u.name, CONCAT('Customer #', r.customer_id)) AS customer, CONCAT('PHP ', FORMAT(r.amount, 2)) AS amount, r.repair_status
            FROM repairs r LEFT JOIN users u ON u.user_id = r.customer_id
            WHERE r.repair_status='Completed' AND r.amount > 0
            ORDER BY r.amount DESC LIMIT 50
        "),
        'fields' => ['repair_id', 'customer', 'amount', 'repair_status'],
    ],
    'completion_rate' => [
        'title' => 'Completion Rate Breakdown',
        'headers' => ['Status', 'Total'],
        'rows' => analyticsRows($conn, "SELECT repair_status, COUNT(*) AS total FROM repairs GROUP BY repair_status ORDER BY total DESC"),
        'fields' => ['repair_status', 'total'],
    ],
    'pending_payments' => [
        'title' => 'Pending Payments Breakdown',
        'headers' => ['Booking', 'Customer', 'Amount', 'Schedule'],
        'rows' => analyticsRows($conn, "
            SELECT rb.booking_id, COALESCE(u.name, CONCAT('Customer #', rb.customer_id)) AS customer, CONCAT('PHP ', FORMAT(rb.estimated_amount, 2)) AS amount, CONCAT(rb.preferred_date, ' ', rb.preferred_time) AS schedule
            FROM repair_bookings rb LEFT JOIN users u ON u.user_id = rb.customer_id
            WHERE rb.payment_status='Pending'
            ORDER BY rb.preferred_date DESC LIMIT 50
        "),
        'fields' => ['booking_id', 'customer', 'amount', 'schedule'],
    ],
    'brand_users' => [
        'title' => 'KUDA vs NWOW Users Breakdown',
        'headers' => ['Brand', 'Registered Owners'],
        'rows' => $brandUsers,
        'fields' => ['brand', 'users'],
    ],
    'warranty_claim_rate' => [
        'title' => 'Warranty Claim Rate Breakdown',
        'headers' => ['Warranty ID', 'Customer', 'E-Bike', 'Status', 'Claim Date'],
        'rows' => analyticsRows($conn, "
            SELECT w.warranty_id, COALESCE(u.name, CONCAT('Customer #', w.customer_id)) AS customer, w.ebike_model, w.warranty_status, w.claim_date
            FROM warranty_records w LEFT JOIN users u ON u.user_id = w.customer_id
            WHERE w.warranty_status='Claimed'
            ORDER BY w.claim_date DESC, w.created_at DESC LIMIT 50
        "),
        'fields' => ['warranty_id', 'customer', 'ebike_model', 'warranty_status', 'claim_date'],
    ],
];

$analyticsBreakdowns['warranty_claims'] = $analyticsBreakdowns['warranty_claim_rate'];
$analyticsBreakdowns['warranty_claims']['title'] = 'Warranty Claims Breakdown';

if ($isAssistantAdmin) {
    foreach (['total_revenue', 'monthly_revenue', 'average_revenue', 'pending_payments'] as $restrictedKey) {
        unset($analyticsBreakdowns[$restrictedKey]);
    }
    $monthlyRevenueTrend = [];
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
    .kpi-card { min-height: 150px; }
    .kpi-row { display: grid; gap: 22px; grid-template-columns: repeat(1, minmax(0, 1fr)); }
    .kpi-col { min-width: 0; }
    .kpi-card { min-width: 0; }
    .kpi-body { align-items: flex-start; height: 100%; padding: 24px 28px; position: relative; }
    .kpi-label { color: var(--app-muted); font-size: .82rem; font-weight: 700; letter-spacing: 0; margin-bottom: 1rem; max-width: calc(100% - 58px); min-height: 1.2em; }
    .kpi-value { font-size: clamp(1.45rem, 2.4vw, 2.1rem); font-weight: 800; line-height: 1.08; margin-bottom: .8rem; max-width: calc(100% - 54px); overflow-wrap: anywhere; }
    .kpi-note { color: var(--app-muted); font-size: .78rem; line-height: 1.3; margin-bottom: 0; }
    .kpi-icon { align-items: center; background: #e5f8e9; border-radius: 8px; display: inline-flex; height: 52px; justify-content: center; position: absolute; right: 22px; top: 22px; width: 52px; }
    .kpi-button { background: transparent; border: 0; padding: 0; text-align: left; width: 100%; }
    .kpi-button .card { cursor: pointer; transition: transform .18s ease, box-shadow .18s ease; }
    .kpi-button:hover .card { transform: translateY(-2px); box-shadow: 0 .7rem 1.4rem rgba(31,41,55,.12) !important; }
    .chart-card { min-height: 350px; padding: 20px; }
    .chart-card h6 { font-size: .95rem; margin-bottom: 4px; }
    .chart-subtitle { color: #6c757d; font-size: .78rem; margin-bottom: 18px; }
    .chart-card canvas { height: 255px !important; max-height: 255px; }
    .chart-card.small-chart { min-height: 300px; }
    .chart-card.small-chart canvas { height: 205px !important; max-height: 205px; }
    @media (min-width: 576px) {
      .kpi-row { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (min-width: 992px) { .kpi-row { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
    @media (min-width: 1200px) {
      .kpi-row { grid-template-columns: repeat(12, minmax(0, 1fr)); }
      .kpi-col:nth-child(-n+4) { grid-column: span 3; }
      .kpi-col:nth-child(n+5) { grid-column: span 4; }
    }
  </style>
</head>
<body>
  <?php include("navbaradmin.php"); ?>
  <div class="container mt-4">
    <div class="page-hero">
      <h3 class="mb-1">Analytics</h3>
      <p><?= $isAssistantAdmin ? 'Repair completion, warranty activity, ownership, common issues, and technician performance.' : 'Revenue quality, repair completion, technician performance, and warranty behavior.' ?></p>
    </div>
    <?php renderDashboardAlerts($conn, $_SESSION['role'], (int)$_SESSION['user_id']); ?>

    <div class="kpi-row mb-4">
      <?php
      $cards = [
        ['total_revenue', 'Total Revenue', 'PHP ' . number_format($totalRevenue, 0), 'Completed repairs', 'text-primary', 'bi-cash-stack'],
        ['monthly_revenue', 'Monthly Revenue', 'PHP ' . number_format($monthlyRevenue, 0), 'Current month', 'text-info', 'bi-calendar3'],
        ['average_revenue', 'Avg Revenue / Repair', 'PHP ' . number_format($averageRevenue, 0), 'Priced completed jobs', 'text-dark', 'bi-receipt'],
        ['completion_rate', 'Completion Rate', $completionRate . '%', $completedRepairs . ' completed', 'text-success', 'bi-check2-circle'],
        ['pending_payments', 'Pending Payments', $pendingPayments, 'Bookings awaiting payment', 'text-warning', 'bi-clock-history'],
        ['brand_users', 'KUDA vs NWOW Users', $kudaUsers . ' / ' . $nwowUsers, 'KUDA/KDA vs NWOW', 'text-danger', 'bi-bicycle'],
        ['warranty_claims', 'Warranty Claims', $warrantyClaims, 'Claimed warranty records', 'text-warning', 'bi-shield-exclamation'],
        ['warranty_claim_rate', 'Warranty Claim Rate', $warrantyClaimRate . '%', $warrantyClaims . ' of ' . $totalWarranties . ' warranties', 'text-secondary', 'bi-shield-check'],
      ];
      if ($isAssistantAdmin) {
        $restrictedKpis = ['total_revenue', 'monthly_revenue', 'average_revenue', 'pending_payments'];
        $cards = array_values(array_filter($cards, static fn (array $card): bool => !in_array($card[0], $restrictedKpis, true)));
      }
      foreach ($cards as $card):
      ?>
        <div class="kpi-col">
          <button type="button" class="kpi-button" <?= $isAssistantAdmin ? 'disabled aria-disabled="true"' : 'data-bs-toggle="modal" data-bs-target="#analyticsModal-' . htmlspecialchars($card[0]) . '"' ?>>
          <div class="card kpi-card <?= $card[4] ?> shadow-sm h-100"><div class="card-body kpi-body">
            <div>
              <p class="kpi-label"><?= htmlspecialchars($card[1]) ?></p>
              <p class="kpi-value <?= $card[4] ?>"><?= htmlspecialchars((string)$card[2]) ?></p>
              <p class="kpi-note"><?= htmlspecialchars($card[3]) ?></p>
            </div>
            <div class="kpi-icon <?= $card[4] ?>"><i class="bi <?= $card[5] ?>"></i></div>
          </div></div>
          </button>
        </div>
      <?php endforeach; ?>
    </div>

    <?php if (!$isAssistantAdmin): foreach ($analyticsBreakdowns as $key => $breakdown): ?>
      <div class="modal fade" id="analyticsModal-<?= htmlspecialchars($key) ?>" tabindex="-1" aria-hidden="true">
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
      <?php if (!$isAssistantAdmin): ?>
      <div class="col-lg-7"><div class="card chart-card">
        <h6 class="fw-bold">Monthly Revenue + Completed Repairs</h6>
        <p class="chart-subtitle">Revenue and completed repair trends by month</p>
        <canvas id="monthlyRevenueChart"></canvas>
      </div></div>
      <?php endif; ?>
      <div class="<?= $isAssistantAdmin ? 'col-12' : 'col-lg-5' ?>"><div class="card chart-card">
        <h6 class="fw-bold">Technician Performance</h6>
        <p class="chart-subtitle">Completed repairs per technician</p>
        <canvas id="technicianPerformanceChart"></canvas>
      </div></div>
    </div>

    <div class="row g-3 mb-4">
      <div class="col-lg-4"><div class="card chart-card">
        <h6 class="fw-bold">Warranty Claim Rate</h6>
        <p class="chart-subtitle">Claimed and non-claimed warranty records</p>
        <canvas id="warrantyPaidChart"></canvas>
      </div></div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    const brandUsers = <?= json_encode($brandUsers, JSON_NUMERIC_CHECK) ?>;
    const commonIssues = <?= json_encode($commonIssues, JSON_NUMERIC_CHECK) ?>;
    const monthlyRevenueTrend = <?= json_encode($monthlyRevenueTrend, JSON_NUMERIC_CHECK) ?>;
    const technicianPerformance = <?= json_encode($technicianPerformance, JSON_NUMERIC_CHECK) ?>;
    const warrantyPaidBreakdown = <?= json_encode($warrantyPaidBreakdown, JSON_NUMERIC_CHECK) ?>;
    const colors = ['#003b16', '#146c43', '#198754', '#67b87a', '#b8e4c2', '#6c757d', '#374151', '#e5f8e9'];
    const gridStyle = { color: 'rgba(108,117,125,.28)', borderDash: [3, 3], drawTicks: false };
    const axisStyle = { grid: gridStyle, border: { color: 'rgba(108,117,125,.45)' }, ticks: { color: '#6c757d', padding: 8 } };
    const legendStyle = { labels: { boxWidth: 10, boxHeight: 10, color: '#495057', usePointStyle: true }, position: 'bottom' };

    new Chart(document.getElementById('brandOwnersChart'), {
      type: 'bar',
      data: { labels: brandUsers.map(row => row.brand), datasets: [{ label: 'Owners', data: brandUsers.map(row => row.users), backgroundColor: ['#003b16', '#198754', '#6c757d'], borderRadius: 8, maxBarThickness: 40 }] },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: axisStyle, y: { ...axisStyle, beginAtZero: true, ticks: { ...axisStyle.ticks, precision: 0 } } } }
    });
    new Chart(document.getElementById('issueCategoryChart'), {
      type: 'bar',
      data: { labels: commonIssues.map(row => row.issue_category), datasets: [{ label: 'Repairs', data: commonIssues.map(row => row.total), backgroundColor: colors, borderRadius: 8, maxBarThickness: 24 }] },
      options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { ...axisStyle, beginAtZero: true, ticks: { ...axisStyle.ticks, precision: 0 } }, y: axisStyle } }
    });
    <?php if (!$isAssistantAdmin): ?>
    new Chart(document.getElementById('monthlyRevenueChart'), {
      type: 'bar',
      data: {
        labels: monthlyRevenueTrend.map(row => row.month),
        datasets: [
          { label: 'Revenue', type: 'bar', data: monthlyRevenueTrend.map(row => row.revenue), backgroundColor: 'rgba(20,108,67,.18)', borderColor: 'rgba(20,108,67,.65)', borderWidth: 1, borderRadius: 8, maxBarThickness: 36, order: 2, yAxisID: 'y' },
          { label: 'Completed Repairs', type: 'line', data: monthlyRevenueTrend.map(row => row.completed_repairs), borderColor: '#198754', backgroundColor: 'rgba(25,135,84,.12)', borderWidth: 3, pointRadius: 4, pointHoverRadius: 7, pointBackgroundColor: '#fff', pointBorderColor: '#198754', pointBorderWidth: 3, tension: .4, fill: true, order: 1, yAxisID: 'y1' }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { intersect: false, mode: 'index' },
        plugins: {
          legend: legendStyle,
          tooltip: { callbacks: { label: context => context.dataset.label === 'Revenue' ? ` Revenue: PHP ${Number(context.parsed.y).toLocaleString('en-PH', { minimumFractionDigits: 2 })}` : ` Completed Repairs: ${context.parsed.y}` } }
        },
        scales: {
          x: axisStyle,
          y: { ...axisStyle, beginAtZero: true, position: 'left', ticks: { ...axisStyle.ticks, callback: value => 'PHP ' + Number(value).toLocaleString('en-PH') } },
          y1: { ...axisStyle, beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, ticks: { ...axisStyle.ticks, precision: 0, stepSize: 1 } }
        }
      }
    });
    <?php endif; ?>
    new Chart(document.getElementById('technicianPerformanceChart'), {
      type: 'bar',
      data: {
        labels: technicianPerformance.map(row => row.technician),
        datasets: [{
          label: 'Completed Repairs',
          data: technicianPerformance.map(row => row.completed_repairs),
          backgroundColor: technicianPerformance.map((row, index) => colors[index % colors.length] + 'cc'),
          borderColor: technicianPerformance.map((row, index) => colors[index % colors.length]),
          borderWidth: 1,
          borderRadius: technicianPerformance.map((row, index) => ({ topLeft: 6 + (index % 3) * 3, topRight: 6 + (index % 3) * 3, bottomLeft: 3, bottomRight: 3 })),
          maxBarThickness: 42,
          categoryPercentage: .72,
          barPercentage: .82
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          x: { ...axisStyle, ticks: { ...axisStyle.ticks, maxRotation: 35, minRotation: 0 } },
          y: { ...axisStyle, beginAtZero: true, ticks: { ...axisStyle.ticks, precision: 0, stepSize: 1 } }
        }
      }
    });
    new Chart(document.getElementById('warrantyPaidChart'), {
      type: 'doughnut',
      data: { labels: warrantyPaidBreakdown.map(row => row.label), datasets: [{ data: warrantyPaidBreakdown.map(row => row.total), backgroundColor: ['#dc3545', '#198754'] }] },
      options: { responsive: true, maintainAspectRatio: false, cutout: '58%', plugins: { legend: legendStyle } }
    });

    window.setInterval(() => {
      if (document.visibilityState === 'visible') window.location.reload();
    }, 15000);
  </script>
</body>
</html>
