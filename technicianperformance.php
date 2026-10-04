<?php
session_start();
include("db.php");

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'HeadTechnician') {
    header("Location: login.php");
    exit();
}

$technicians = $conn->query("
  SELECT u.user_id, u.name,
         COUNT(r.repair_id) AS total_repairs,
         SUM(CASE WHEN r.repair_status = 'Completed' THEN 1 ELSE 0 END) AS completed_repairs,
         SUM(CASE WHEN r.repair_status = 'In Progress' THEN 1 ELSE 0 END) AS in_progress_repairs,
         SUM(CASE WHEN r.repair_status = 'Pending' THEN 1 ELSE 0 END) AS pending_repairs,
         COALESCE(AVG(CASE WHEN r.repair_status = 'Completed' THEN TIMESTAMPDIFF(DAY, r.created_at, r.updated_at) END), 0) AS avg_turnaround,
         MAX(r.updated_at) AS last_activity
  FROM users u
  LEFT JOIN repairs r ON r.technician_id = u.user_id
  WHERE u.role IN ('Technician', 'HeadTechnician') AND u.account_status = 'Active'
  GROUP BY u.user_id, u.name
  ORDER BY completed_repairs DESC, avg_turnaround ASC, u.name ASC
");
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
    .performance-wrap { margin: 30px auto 48px; max-width: min(1180px, calc(100% - 24px)); }
    .performance-table { table-layout: fixed; }
    .performance-table th, .performance-table td { vertical-align: middle; }
    .metric-value { font-weight: 800; }
  </style>
</head>
<body>
  <?php include("navbartechnician.php"); ?>
  <main class="performance-wrap">
    <div class="page-hero">
      <h3 class="mb-1">Technician Performance</h3>
      <p>Overview of repair volume, status mix, average turnaround, and latest activity by technician.</p>
    </div>

    <div class="card shadow-sm">
      <div class="card-body">
        <div class="d-flex justify-content-end mb-3">
          <input type="search" class="form-control" id="performanceSearch" style="max-width: 360px;" placeholder="Search technician performance">
        </div>
        <div class="table-responsive">
          <table class="table table-hover table-bordered mb-0 performance-table" id="performanceTable">
            <thead class="table-danger">
              <tr>
                <th>Technician</th>
                <th>Total</th>
                <th>Completed</th>
                <th>In Progress</th>
                <th>Pending</th>
                <th>Avg Turnaround</th>
                <th>Last Activity</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($technicians && $technicians->num_rows > 0): while ($tech = $technicians->fetch_assoc()): ?>
                <tr>
                  <td class="fw-semibold"><?= htmlspecialchars($tech['name']) ?></td>
                  <td class="metric-value"><?= htmlspecialchars((string)$tech['total_repairs']) ?></td>
                  <td><span class="badge bg-success"><?= htmlspecialchars((string)$tech['completed_repairs']) ?></span></td>
                  <td><span class="badge bg-primary"><?= htmlspecialchars((string)$tech['in_progress_repairs']) ?></span></td>
                  <td><span class="badge bg-warning"><?= htmlspecialchars((string)$tech['pending_repairs']) ?></span></td>
                  <td class="text-danger fw-bold"><?= number_format((float)$tech['avg_turnaround'], 1) ?> days</td>
                  <td><?= htmlspecialchars($tech['last_activity'] ?? 'No activity yet') ?></td>
                </tr>
              <?php endwhile; else: ?>
                <tr><td colspan="7" class="text-center text-muted">No active technicians found.</td></tr>
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
