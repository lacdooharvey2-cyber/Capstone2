<?php
session_start();
include("db.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Technician') {
    header("Location: login.php");
    exit();
}

$user_id = intval($_SESSION['user_id']);
$overdueDays = 7;

function techValue(mysqli $conn, string $sql, string $key = 'total')
{
    $result = $conn->query($sql);
    if (!$result) {
        return 0;
    }
    $row = $result->fetch_assoc();
    return $row[$key] ?? 0;
}

function techRows(mysqli $conn, string $sql): array
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

$completedRepairs = (int)techValue($conn, "SELECT COUNT(*) AS total FROM repairs WHERE technician_id=$user_id AND repair_status='Completed'");
$inProgressRepairs = (int)techValue($conn, "SELECT COUNT(*) AS total FROM repairs WHERE technician_id=$user_id AND repair_status='In Progress'");
$pendingRepairs = (int)techValue($conn, "SELECT COUNT(*) AS total FROM repairs WHERE technician_id=$user_id AND repair_status='Pending'");
$avgTurnaround = (float)techValue($conn, "
  SELECT COALESCE(AVG(TIMESTAMPDIFF(DAY, created_at, updated_at)),0) AS total
  FROM repairs
  WHERE technician_id=$user_id AND repair_status='Completed' AND updated_at IS NOT NULL
");
$overdueJobs = (int)techValue($conn, "
  SELECT COUNT(*) AS total
  FROM repairs
  WHERE technician_id=$user_id
    AND repair_status IN ('Pending','In Progress')
    AND created_at < DATE_SUB(NOW(), INTERVAL $overdueDays DAY)
");

$weeklyRows = techRows($conn, "
  SELECT WEEKDAY(created_at) AS weekday_index, COUNT(*) AS total
  FROM repairs
  WHERE technician_id=$user_id
    AND created_at >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)
    AND created_at < DATE_ADD(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 7 DAY)
  GROUP BY WEEKDAY(created_at)
");
$weekCounts = array_fill(0, 7, 0);
foreach ($weeklyRows as $row) {
    $weekCounts[(int)$row['weekday_index']] = (int)$row['total'];
}

$queue = $conn->query("
  SELECT r.repair_id, r.ebike_model, r.issue_description, r.repair_status, r.created_at, u.name AS customer_name
  FROM repairs r
  LEFT JOIN users u ON u.user_id = r.customer_id
  WHERE r.technician_id=$user_id AND r.repair_status IN ('Pending','In Progress')
  ORDER BY r.created_at ASC
  LIMIT 8
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Technician Dashboard - FixTrack</title>
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
    .chart-card { min-height: 330px; padding: 20px; }
    .chart-card canvas { height: 240px !important; max-height: 240px; }
  </style>
</head>
<body>
  <?php include("navbartechnician.php"); ?>
  <div class="container mt-4">
    <div class="page-hero">
      <h3 class="mb-1">Technician Dashboard</h3>
      <p>Assigned repair workload, overdue jobs, and this week's activity.</p>
    </div>

    <div class="row g-3 mb-4">
      <?php
      $cards = [
        ['My Completed Repairs', $completedRepairs, 'Filtered by technician', 'text-success', 'bi-check2-circle'],
        ['My In-Progress Repairs', $inProgressRepairs, 'Currently being repaired', 'text-info', 'bi-arrow-repeat'],
        ['My Pending Repairs', $pendingRepairs, 'Waiting to start', 'text-warning', 'bi-hourglass-split'],
        ['My Avg Turnaround', number_format($avgTurnaround, 1) . ' days', 'Completed repair cycle', 'text-primary', 'bi-speedometer2'],
        ['My Overdue Jobs', $overdueJobs, 'Open longer than ' . $overdueDays . ' days', 'text-danger', 'bi-exclamation-triangle'],
      ];
      foreach ($cards as $card):
      ?>
        <div class="col-md-4 col-xl">
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
      <div class="col-lg-6">
        <div class="card chart-card">
          <h6 class="fw-bold">My Repairs This Week</h6>
          <p class="text-muted small mb-3">Monday to Sunday assigned repair tickets</p>
          <canvas id="weeklyTechChart"></canvas>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="card shadow-sm h-100">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h5 class="mb-0">Active Work Queue</h5>
              <a href="technicianrepair.php" class="btn btn-danger btn-sm">View Full Queue</a>
            </div>
            <div class="table-responsive">
              <table class="table table-hover mb-0">
                <thead><tr><th>ID</th><th>Customer</th><th>E-Bike</th><th>Status</th><th>Created</th></tr></thead>
                <tbody>
                  <?php if ($queue && $queue->num_rows > 0): while($job = $queue->fetch_assoc()): ?>
                    <tr>
                      <td><?= htmlspecialchars($job['repair_id']) ?></td>
                      <td><?= htmlspecialchars($job['customer_name'] ?? 'Customer') ?></td>
                      <td><?= htmlspecialchars($job['ebike_model']) ?></td>
                      <td><span class="badge bg-<?= $job['repair_status'] === 'In Progress' ? 'primary' : 'warning' ?>"><?= htmlspecialchars($job['repair_status']) ?></span></td>
                      <td><?= htmlspecialchars($job['created_at']) ?></td>
                    </tr>
                  <?php endwhile; else: ?>
                    <tr><td colspan="5" class="text-center text-muted">No active assigned jobs.</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script>
    const weekLabels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    const weekCounts = <?= json_encode($weekCounts, JSON_NUMERIC_CHECK) ?>;
    const axisStyle = { grid: { color: 'rgba(108,117,125,.25)', borderDash: [3, 3] }, ticks: { color: '#6c757d' } };
    const weeklyTechChart = new Chart(document.getElementById('weeklyTechChart'), {
      type: 'bar',
      data: { labels: weekLabels, datasets: [{ label: 'Repairs', data: weekCounts, backgroundColor: 'rgba(214,40,40,.7)', borderRadius: 8, maxBarThickness: 36 }] },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: axisStyle, y: { ...axisStyle, beginAtZero: true, ticks: { ...axisStyle.ticks, precision: 0 } } } }
    });

    async function refreshWeeklyTechChart() {
      try {
        const response = await fetch('technician_chart_data.php?ts=' + Date.now(), {
          cache: 'no-store',
          credentials: 'same-origin',
        });
        if (!response.ok) {
          return;
        }
        const payload = await response.json();
        if (!Array.isArray(payload.weekCounts)) {
          return;
        }
        weeklyTechChart.data.datasets[0].data = payload.weekCounts;
        weeklyTechChart.update('none');
      } catch (error) {
        // Keep the last known chart values when the live refresh is unavailable.
      }
    }

    window.setInterval(refreshWeeklyTechChart, 15000);
  </script>
</body>
</html>
