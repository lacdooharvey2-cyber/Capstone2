<?php
session_start();
include("db.php");
include_once("schema_helpers.php");

// Guard: only allow Admins
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['Admin', 'SuperAdmin'], true)) {
    header("Location: login.php");
    exit();
}

ensureRepairAutomationSchema($conn);

function repairPageValue(mysqli $conn, string $sql): int
{
    $result = $conn->query($sql);
    if (!$result) {
        return 0;
    }
    $row = $result->fetch_assoc();
    return (int)($row['total'] ?? 0);
}

$totalRepairs = repairPageValue($conn, "SELECT COUNT(*) AS total FROM repairs");
$pendingRepairs = repairPageValue($conn, "SELECT COUNT(*) AS total FROM repairs WHERE repair_status='Pending'");
$inProgressRepairs = repairPageValue($conn, "SELECT COUNT(*) AS total FROM repairs WHERE repair_status='In Progress'");
$completedRepairs = repairPageValue($conn, "SELECT COUNT(*) AS total FROM repairs WHERE repair_status='Completed'");

// Fetch all repair bookings
$sql = "SELECT r.repair_id, r.customer_id, u.name AS customer_name, r.ebike_model,
               r.issue_description, r.warranty_status, r.amount, r.repair_status, r.created_at
        FROM repairs r
        LEFT JOIN users u ON u.user_id = r.customer_id
        ORDER BY r.created_at DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Manage Repairs - FixTrack Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .repair-live-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; margin-bottom: 18px; }
    .repair-live-card { border-left-width: 4px !important; min-height: 104px; }
    .repair-live-label { color: #6c757d; font-size: .8rem; font-weight: 700; margin-bottom: 6px; }
    .repair-live-value { font-size: 1.55rem; font-weight: 800; margin-bottom: 0; }
    @media (max-width: 900px) { .repair-live-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 575px) { .repair-live-grid { grid-template-columns: 1fr; } }
  </style>
</head>
<body>
  <?php include("navbaradmin.php"); ?> <!-- make sure file exists -->

  <div class="container mt-4">
    <div class="page-hero">
      <h3 class="mb-1">Manage Repair Bookings</h3>
      <p>Review repair issues, automatic warranty result, and technician-set costing.</p>
    </div>

    <div class="repair-live-grid" aria-live="polite">
      <div class="card repair-live-card text-danger shadow-sm"><div class="card-body">
        <p class="repair-live-label">Total Repairs</p>
        <p class="repair-live-value" data-stat="total_repairs"><?= htmlspecialchars((string)$totalRepairs) ?></p>
      </div></div>
      <div class="card repair-live-card text-warning shadow-sm"><div class="card-body">
        <p class="repair-live-label">Pending</p>
        <p class="repair-live-value" data-stat="pending_repairs"><?= htmlspecialchars((string)$pendingRepairs) ?></p>
      </div></div>
      <div class="card repair-live-card text-primary shadow-sm"><div class="card-body">
        <p class="repair-live-label">In Progress</p>
        <p class="repair-live-value" data-stat="in_progress_repairs"><?= htmlspecialchars((string)$inProgressRepairs) ?></p>
      </div></div>
      <div class="card repair-live-card text-success shadow-sm"><div class="card-body">
        <p class="repair-live-label">Completed</p>
        <p class="repair-live-value" data-stat="completed_repairs"><?= htmlspecialchars((string)$completedRepairs) ?></p>
      </div></div>
    </div>

    <div class="card shadow-sm">
      <div class="card-body">
        <div class="table-responsive">
    <table class="table table-hover table-bordered mb-0">
      <thead class="table-danger">
        <tr>
          <th>Repair ID</th>
          <th>Customer</th>
          <th>E-Bike Model</th>
          <th>Issue</th>
          <th>Warranty</th>
          <th>Cost</th>
          <th>Status</th>
          <th>Date Created</th>
          <th style="width:150px;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($result && $result->num_rows > 0): ?>
          <?php while($row = $result->fetch_assoc()): ?>
            <tr>
              <td><?= htmlspecialchars($row['repair_id']) ?></td>
              <td><?= htmlspecialchars($row['customer_name'] ?? 'Customer #'.$row['customer_id']) ?></td>
              <td><?= htmlspecialchars($row['ebike_model']) ?></td>
              <td><?= htmlspecialchars($row['issue_description']) ?></td>
              <td>
                <span class="badge bg-<?= $row['warranty_status'] === 'Valid' ? 'success' : 'secondary' ?>">
                  <?= htmlspecialchars($row['warranty_status']) ?>
                </span>
              </td>
              <td><?= (float)$row['amount'] > 0 ? 'PHP ' . number_format((float)$row['amount'], 2) : 'Pending' ?></td>
              <td>
                <?php if ($row['repair_status'] === 'Pending'): ?>
                  <span class="badge bg-warning">Pending</span>
                <?php elseif ($row['repair_status'] === 'In Progress'): ?>
                  <span class="badge bg-primary">In Progress</span>
                <?php elseif ($row['repair_status'] === 'Completed'): ?>
                  <span class="badge bg-success">Completed</span>
                <?php else: ?>
                  <span class="badge bg-danger"><?= htmlspecialchars($row['repair_status']) ?></span>
                <?php endif; ?>
              </td>
              <td><?= htmlspecialchars($row['created_at']) ?></td>
              <td>
                <a href="repairdetails.php?id=<?= urlencode($row['repair_id']) ?>" class="btn btn-sm btn-outline-secondary">View Details</a>
                <a href="editrepair.php?id=<?= urlencode($row['repair_id']) ?>" class="btn btn-sm btn-primary">Edit</a>
                <a href="deleterepair.php?id=<?= urlencode($row['repair_id']) ?>" 
                   class="btn btn-sm btn-danger"
                   onclick="return confirm('Are you sure you want to delete this booking?');">Delete</a>
              </td>
            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr>
            <td colspan="9" class="text-center text-muted">No repair bookings found.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
        </div>
      </div>
    </div>
  </div>
  <script>
    async function refreshRepairStats() {
      if (document.visibilityState !== 'visible') return;
      try {
        const response = await fetch('admin_stats_api.php', { cache: 'no-store' });
        if (!response.ok) return;
        const stats = await response.json();
        document.querySelectorAll('[data-stat]').forEach((element) => {
          const key = element.dataset.stat;
          if (key in stats) element.textContent = stats[key];
        });
      } catch (error) {
        console.warn('Unable to refresh repair stats', error);
      }
    }

    window.setInterval(refreshRepairStats, 15000);
    refreshRepairStats();
  </script>
</body>
</html>
