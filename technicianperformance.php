<?php
session_start();
include("db.php");

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'HeadTechnician') {
    header("Location: login.php");
    exit();
}

$techniciansResult = $conn->query("
  SELECT u.user_id, u.name,
         COUNT(r.repair_id) AS total_repairs,
         SUM(CASE WHEN r.repair_status = 'Completed' THEN 1 ELSE 0 END) AS completed_repairs,
         SUM(CASE WHEN r.repair_status = 'In Progress' THEN 1 ELSE 0 END) AS in_progress_repairs,
         SUM(CASE WHEN r.repair_status = 'Pending' THEN 1 ELSE 0 END) AS pending_repairs,
         SUM(CASE WHEN r.repair_status = 'Cancelled' THEN 1 ELSE 0 END) AS cancelled_repairs,
         COALESCE(AVG(CASE WHEN r.repair_status = 'Completed' THEN TIMESTAMPDIFF(DAY, r.created_at, r.updated_at) END), 0) AS avg_turnaround,
         MAX(r.updated_at) AS last_activity
  FROM users u
  LEFT JOIN repairs r ON r.technician_id = u.user_id
  WHERE u.role = 'Technician' AND u.account_status = 'Active'
  GROUP BY u.user_id, u.name
  ORDER BY completed_repairs DESC, avg_turnaround ASC, u.name ASC
  LIMIT 5
");

$technicians = [];
$teamTotals = ['total' => 0, 'completed' => 0, 'progress' => 0, 'pending' => 0];
$turnaroundSum = 0.0;
$turnaroundCount = 0;

if ($techniciansResult) {
    while ($tech = $techniciansResult->fetch_assoc()) {
        $tech['total_repairs'] = (int)$tech['total_repairs'];
        $tech['completed_repairs'] = (int)$tech['completed_repairs'];
        $tech['in_progress_repairs'] = (int)$tech['in_progress_repairs'];
        $tech['pending_repairs'] = (int)$tech['pending_repairs'];
        $tech['avg_turnaround'] = (float)$tech['avg_turnaround'];
        $tech['completion_rate'] = $tech['total_repairs'] > 0 ? round(($tech['completed_repairs'] / $tech['total_repairs']) * 100) : 0;

        $teamTotals['total'] += $tech['total_repairs'];
        $teamTotals['completed'] += $tech['completed_repairs'];
        $teamTotals['progress'] += $tech['in_progress_repairs'];
        $teamTotals['pending'] += $tech['pending_repairs'];
        if ($tech['completed_repairs'] > 0) {
            $turnaroundSum += $tech['avg_turnaround'];
            $turnaroundCount++;
        }
        $technicians[] = $tech;
    }
}

$teamCompletionRate = $teamTotals['total'] > 0 ? round(($teamTotals['completed'] / $teamTotals['total']) * 100) : 0;
$teamAvgTurnaround = $turnaroundCount > 0 ? $turnaroundSum / $turnaroundCount : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Technician Performance - FixTrack</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    .performance-wrap { margin: 30px auto 48px; max-width: min(1280px, calc(100% - 24px)); }
    .owner-summary { display: grid; gap: 14px; grid-template-columns: repeat(4, minmax(0, 1fr)); margin-bottom: 18px; }
    .owner-metric { border-left: 4px solid #198754 !important; min-height: 116px; }
    .owner-metric.primary { border-left-color: #0d6efd !important; }
    .owner-metric.warning { border-left-color: #ffc107 !important; }
    .owner-metric.danger { border-left-color: #dc3545 !important; }
    .metric-label { color: #6c757d; font-size: .78rem; font-weight: 800; margin-bottom: 8px; text-transform: uppercase; }
    .metric-value { font-size: 1.7rem; font-weight: 900; margin-bottom: 3px; }
    .metric-note { color: #6c757d; font-size: .82rem; margin-bottom: 0; }
    .tech-grid { display: grid; gap: 14px; grid-template-columns: repeat(5, minmax(0, 1fr)); margin-bottom: 18px; }
    .tech-card { height: 100%; }
    .tech-rank { align-items: center; background: #e5f8e9; border-radius: 8px; color: #146c43; display: inline-flex; font-weight: 900; height: 34px; justify-content: center; width: 34px; }
    .tech-name { font-size: .96rem; font-weight: 900; line-height: 1.2; min-height: 2.4em; }
    .progress { height: 9px; }
    .performance-table { table-layout: fixed; }
    .performance-table th, .performance-table td { vertical-align: middle; }
    @media (max-width: 1100px) {
      .tech-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
      .owner-summary { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 640px) {
      .tech-grid, .owner-summary { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>
  <?php include("navbartechnician.php"); ?>
  <main class="performance-wrap">
    <div class="page-hero">
      <h3 class="mb-1">Technician Performance Overview</h3>
      <p>Owner-ready summary of the 5 active technicians, ranked by completed repair output and turnaround.</p>
    </div>

    <section class="owner-summary" aria-label="Team performance summary">
      <div class="card owner-metric shadow-sm"><div class="card-body">
        <p class="metric-label">Team Completed</p>
        <p class="metric-value text-success"><?= htmlspecialchars((string)$teamTotals['completed']) ?></p>
        <p class="metric-note">Closed repairs from the 5 technicians</p>
      </div></div>
      <div class="card owner-metric primary shadow-sm"><div class="card-body">
        <p class="metric-label">Active Work</p>
        <p class="metric-value text-primary"><?= htmlspecialchars((string)($teamTotals['progress'] + $teamTotals['pending'])) ?></p>
        <p class="metric-note"><?= htmlspecialchars((string)$teamTotals['progress']) ?> in progress, <?= htmlspecialchars((string)$teamTotals['pending']) ?> pending</p>
      </div></div>
      <div class="card owner-metric warning shadow-sm"><div class="card-body">
        <p class="metric-label">Completion Rate</p>
        <p class="metric-value text-warning"><?= htmlspecialchars((string)$teamCompletionRate) ?>%</p>
        <p class="metric-note">Completed against all assigned work</p>
      </div></div>
      <div class="card owner-metric danger shadow-sm"><div class="card-body">
        <p class="metric-label">Avg Turnaround</p>
        <p class="metric-value text-danger"><?= number_format($teamAvgTurnaround, 1) ?> days</p>
        <p class="metric-note">Average among technicians with completed work</p>
      </div></div>
    </section>

    <section class="tech-grid" aria-label="Technician ranking cards">
      <?php if ($technicians): foreach ($technicians as $index => $tech): ?>
        <article class="card tech-card shadow-sm">
          <div class="card-body">
            <div class="d-flex align-items-start justify-content-between mb-3">
              <div>
                <div class="tech-name"><?= htmlspecialchars($tech['name']) ?></div>
                <div class="text-muted small">Technician <?= $index + 1 ?></div>
              </div>
              <span class="tech-rank">#<?= $index + 1 ?></span>
            </div>
            <div class="d-flex justify-content-between small fw-semibold mb-1">
              <span>Completion</span>
              <span><?= htmlspecialchars((string)$tech['completion_rate']) ?>%</span>
            </div>
            <div class="progress mb-3">
              <div class="progress-bar bg-success" style="width: <?= htmlspecialchars((string)$tech['completion_rate']) ?>%"></div>
            </div>
            <div class="row g-2 text-center">
              <div class="col-4"><div class="fw-bold text-success"><?= htmlspecialchars((string)$tech['completed_repairs']) ?></div><div class="small text-muted">Done</div></div>
              <div class="col-4"><div class="fw-bold text-primary"><?= htmlspecialchars((string)$tech['in_progress_repairs']) ?></div><div class="small text-muted">Active</div></div>
              <div class="col-4"><div class="fw-bold text-danger"><?= number_format((float)$tech['avg_turnaround'], 1) ?></div><div class="small text-muted">Days</div></div>
            </div>
          </div>
        </article>
      <?php endforeach; else: ?>
        <div class="card shadow-sm"><div class="card-body text-muted">No active technicians found.</div></div>
      <?php endif; ?>
    </section>

    <div class="card shadow-sm">
      <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
          <div>
            <h5 class="mb-1">Detailed Technician Scorecard</h5>
            <p class="text-muted mb-0">Only Technician-role staff are included. Head Technician is intentionally excluded.</p>
          </div>
          <input type="search" class="form-control" id="performanceSearch" style="max-width: 320px;" placeholder="Search technician">
        </div>
        <div class="table-responsive">
          <table class="table table-hover table-bordered mb-0 performance-table" id="performanceTable">
            <thead class="table-danger">
              <tr>
                <th>Rank</th>
                <th>Technician</th>
                <th>Total Assigned</th>
                <th>Completed</th>
                <th>In Progress</th>
                <th>Pending</th>
                <th>Completion</th>
                <th>Avg Turnaround</th>
                <th>Last Activity</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($technicians): foreach ($technicians as $index => $tech): ?>
                <tr>
                  <td class="fw-bold">#<?= $index + 1 ?></td>
                  <td class="fw-semibold"><?= htmlspecialchars($tech['name']) ?></td>
                  <td><?= htmlspecialchars((string)$tech['total_repairs']) ?></td>
                  <td><span class="badge bg-success"><?= htmlspecialchars((string)$tech['completed_repairs']) ?></span></td>
                  <td><span class="badge bg-primary"><?= htmlspecialchars((string)$tech['in_progress_repairs']) ?></span></td>
                  <td><span class="badge bg-warning"><?= htmlspecialchars((string)$tech['pending_repairs']) ?></span></td>
                  <td><?= htmlspecialchars((string)$tech['completion_rate']) ?>%</td>
                  <td class="text-danger fw-bold"><?= number_format((float)$tech['avg_turnaround'], 1) ?> days</td>
                  <td><?= htmlspecialchars($tech['last_activity'] ?? 'No activity yet') ?></td>
                </tr>
              <?php endforeach; else: ?>
                <tr><td colspan="9" class="text-center text-muted">No active technicians found.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </main>
  <script>
    const performanceSearch = document.getElementById('performanceSearch');
    const performanceTable = document.getElementById('performanceTable');
    performanceSearch?.addEventListener('input', () => {
      const query = performanceSearch.value.trim().toLowerCase();
      performanceTable?.querySelectorAll('tbody tr').forEach((row) => {
        if (row.cells.length <= 1) return;
        row.style.display = row.textContent.toLowerCase().includes(query) ? '' : 'none';
      });
    });
  </script>
</body>
</html>
