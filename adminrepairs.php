<?php
session_start();
include("db.php");
include_once("schema_helpers.php");

// Guard: only allow Admins
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['AssistantAdmin', 'Admin', 'AssistantSuperAdmin', 'SuperAdmin'], true)) {
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

function repairPageRows(mysqli $conn, string $sql): array
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

$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';
$warrantyFilter = $_GET['warranty'] ?? '';
$sort = $_GET['sort'] ?? 'newest';
$allowedStatuses = ['Pending', 'In Progress', 'Completed', 'Cancelled'];
$allowedWarranty = ['Valid', 'Invalid'];
$where = [];
$types = '';
$params = [];

if ($search !== '') {
    $where[] = "(CAST(r.repair_id AS CHAR) LIKE ? OR u.name LIKE ? OR r.ebike_model LIKE ? OR r.issue_description LIKE ?)";
    $needle = '%' . $search . '%';
    array_push($params, $needle, $needle, $needle, $needle);
    $types .= 'ssss';
}
if (in_array($statusFilter, $allowedStatuses, true)) {
    $where[] = "r.repair_status = ?";
    $params[] = $statusFilter;
    $types .= 's';
}
if (in_array($warrantyFilter, $allowedWarranty, true)) {
    $where[] = "r.warranty_status = ?";
    $params[] = $warrantyFilter;
    $types .= 's';
}

$orderBy = match ($sort) {
    'oldest' => 'r.created_at ASC',
    'status' => "FIELD(r.repair_status, 'Pending', 'In Progress', 'Completed', 'Cancelled'), r.created_at DESC",
    'customer' => 'u.name ASC, r.created_at DESC',
    'cost_desc' => 'r.amount DESC, r.created_at DESC',
    default => 'r.created_at DESC',
};
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
// Fetch all repair bookings
$sql = "SELECT r.repair_id, r.customer_id, u.name AS customer_name, r.ebike_model,
               r.issue_description, r.proof_file, r.warranty_status, r.amount, r.repair_status, r.created_at
        FROM repairs r
        LEFT JOIN users u ON u.user_id = r.customer_id
        $whereSql
        ORDER BY $orderBy";
$stmt = $conn->prepare($sql);
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$repairBreakdowns = [
    'total_repairs' => ['title' => 'All Repairs', 'rows' => repairPageRows($conn, "SELECT repair_id, ebike_model, repair_status, created_at FROM repairs ORDER BY created_at DESC LIMIT 50")],
    'pending_repairs' => ['title' => 'Pending Repairs', 'rows' => repairPageRows($conn, "SELECT repair_id, ebike_model, repair_status, created_at FROM repairs WHERE repair_status='Pending' ORDER BY created_at DESC LIMIT 50")],
    'in_progress_repairs' => ['title' => 'In Progress Repairs', 'rows' => repairPageRows($conn, "SELECT repair_id, ebike_model, repair_status, created_at FROM repairs WHERE repair_status='In Progress' ORDER BY created_at DESC LIMIT 50")],
    'completed_repairs' => ['title' => 'Completed Repairs', 'rows' => repairPageRows($conn, "SELECT repair_id, ebike_model, repair_status, created_at FROM repairs WHERE repair_status='Completed' ORDER BY updated_at DESC LIMIT 50")],
];
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
    .repair-kpi-button { background: transparent; border: 0; padding: 0; text-align: left; width: 100%; }
    .repair-kpi-button .card { cursor: pointer; transition: transform .18s ease, box-shadow .18s ease; }
    .repair-kpi-button:hover .card { transform: translateY(-2px); box-shadow: 0 .7rem 1.4rem rgba(31,41,55,.12) !important; }
    .repair-live-label { color: #6c757d; font-size: .8rem; font-weight: 700; margin-bottom: 6px; }
    .repair-live-value { font-size: 1.55rem; font-weight: 800; margin-bottom: 0; }
    .record-actions { display:flex; flex-wrap:wrap; gap:6px; min-width:210px; }
    .record-actions .btn { min-width:76px; }
    .record-actions .btn:first-child { min-width:104px; }
    @media (max-width: 900px) { .repair-live-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 575px) { .repair-live-grid { grid-template-columns: 1fr; } }
  </style>
</head>
<body>
  <?php include("navbaradmin.php"); ?> <!-- make sure file exists -->
  <?php $isAssistantAdmin = ($_SESSION['role'] ?? '') === 'AssistantAdmin'; ?>

  <div class="container mt-4">
    <div class="page-hero">
      <h3 class="mb-1">Manage Repair Bookings</h3>
      <p>Review repair issues, automatic warranty result, and technician-set costing.</p>
    </div>

    <div class="repair-live-grid" aria-live="polite">
      <div class="<?= $isAssistantAdmin ? '' : 'repair-kpi-button' ?>" <?= $isAssistantAdmin ? '' : 'data-bs-toggle="modal" data-bs-target="#repairKpi-total_repairs"' ?>><div class="card repair-live-card text-primary shadow-sm"><div class="card-body">
        <p class="repair-live-label">Total Repairs</p>
        <p class="repair-live-value" data-stat="total_repairs"><?= htmlspecialchars((string)$totalRepairs) ?></p>
      </div></div></div>
      <div class="<?= $isAssistantAdmin ? '' : 'repair-kpi-button' ?>" <?= $isAssistantAdmin ? '' : 'data-bs-toggle="modal" data-bs-target="#repairKpi-pending_repairs"' ?>><div class="card repair-live-card text-warning shadow-sm"><div class="card-body">
        <p class="repair-live-label">Pending</p>
        <p class="repair-live-value" data-stat="pending_repairs"><?= htmlspecialchars((string)$pendingRepairs) ?></p>
      </div></div></div>
      <div class="<?= $isAssistantAdmin ? '' : 'repair-kpi-button' ?>" <?= $isAssistantAdmin ? '' : 'data-bs-toggle="modal" data-bs-target="#repairKpi-in_progress_repairs"' ?>><div class="card repair-live-card text-primary shadow-sm"><div class="card-body">
        <p class="repair-live-label">In Progress</p>
        <p class="repair-live-value" data-stat="in_progress_repairs"><?= htmlspecialchars((string)$inProgressRepairs) ?></p>
      </div></div></div>
      <div class="<?= $isAssistantAdmin ? '' : 'repair-kpi-button' ?>" <?= $isAssistantAdmin ? '' : 'data-bs-toggle="modal" data-bs-target="#repairKpi-completed_repairs"' ?>><div class="card repair-live-card text-success shadow-sm"><div class="card-body">
        <p class="repair-live-label">Completed</p>
        <p class="repair-live-value" data-stat="completed_repairs"><?= htmlspecialchars((string)$completedRepairs) ?></p>
      </div></div></div>
    </div>

    <div class="card shadow-sm">
      <div class="card-body">
        <form class="row g-2 align-items-end mb-3" method="get">
          <div class="col-md-4">
            <label class="form-label">Search</label>
            <input class="form-control" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="ID, customer, model, issue">
          </div>
          <div class="col-md-2">
            <label class="form-label">Status</label>
            <select class="form-select" name="status">
              <option value="">All</option>
              <?php foreach ($allowedStatuses as $status): ?><option value="<?= htmlspecialchars($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= htmlspecialchars($status) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label">Warranty</label>
            <select class="form-select" name="warranty">
              <option value="">All</option>
              <?php foreach ($allowedWarranty as $warranty): ?><option value="<?= htmlspecialchars($warranty) ?>" <?= $warrantyFilter === $warranty ? 'selected' : '' ?>><?= htmlspecialchars($warranty) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label">Sort</label>
            <select class="form-select" name="sort">
              <?php foreach (['newest' => 'Newest', 'oldest' => 'Oldest', 'status' => 'Status', 'customer' => 'Customer', 'cost_desc' => 'Highest Cost'] as $value => $label): ?>
                <option value="<?= htmlspecialchars($value) ?>" <?= $sort === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2 d-flex gap-2">
            <button class="btn btn-success flex-fill">Apply</button>
            <a href="adminrepairs.php" class="btn btn-outline-secondary">Reset</a>
          </div>
        </form>
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
          <th>Proof</th>
          <th>Date Created</th>
          <th style="width:230px;">Actions</th>
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
              <td>
                <?php if (!empty($row['proof_file'])): ?>
                  <a href="<?= htmlspecialchars($row['proof_file']) ?>" class="btn btn-sm btn-outline-secondary" target="_blank">Open</a>
                <?php else: ?>
                  <span class="text-muted">None</span>
                <?php endif; ?>
              </td>
              <td><?= htmlspecialchars($row['created_at']) ?></td>
              <td><div class="record-actions">
                <a href="repairdetails.php?id=<?= urlencode($row['repair_id']) ?>" class="btn btn-sm btn-outline-secondary">View Details</a>
                <?php if (!$isAssistantAdmin): ?>
                  <a href="editrepair.php?id=<?= urlencode($row['repair_id']) ?>" class="btn btn-sm btn-primary">Edit</a>
                  <a href="deleterepair.php?id=<?= urlencode($row['repair_id']) ?>"
                     class="btn btn-sm btn-danger"
                     onclick="return confirm('Are you sure you want to delete this booking?');">Delete</a>
                <?php endif; ?>
              </div></td>
            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr>
            <td colspan="10" class="text-center text-muted">No repair bookings found.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
        </div>
      </div>
    </div>
  </div>
  <?php if (!$isAssistantAdmin): foreach ($repairBreakdowns as $key => $breakdown): ?>
    <div class="modal fade" id="repairKpi-<?= htmlspecialchars($key) ?>" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title"><?= htmlspecialchars($breakdown['title']) ?></h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="table-responsive">
              <table class="table table-sm table-hover">
                <thead><tr><th>ID</th><th>E-Bike</th><th>Status</th><th>Created</th></tr></thead>
                <tbody>
                  <?php if ($breakdown['rows']): foreach ($breakdown['rows'] as $row): ?>
                    <tr><td><?= htmlspecialchars($row['repair_id']) ?></td><td><?= htmlspecialchars($row['ebike_model']) ?></td><td><?= htmlspecialchars($row['repair_status']) ?></td><td><?= htmlspecialchars($row['created_at']) ?></td></tr>
                  <?php endforeach; else: ?>
                    <tr><td colspan="4" class="text-center text-muted">No records found.</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  <?php endforeach; endif; ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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
