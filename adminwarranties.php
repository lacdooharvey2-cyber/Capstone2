<?php
session_start();
include("db.php");
include_once("schema_helpers.php");

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['AssistantAdmin', 'Admin', 'AssistantSuperAdmin', 'SuperAdmin'], true)) {
    header("Location: login.php");
    exit();
}

syncWarrantyStatuses($conn);
syncRepairWarrantyCoverage($conn);

$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? 'All';
$allowedStatuses = ['All', 'Active', 'Expired', 'Claimed', 'Rejected'];
if (!in_array($status, $allowedStatuses, true)) {
    $status = 'All';
}

$kpi = [
    'Active' => 0,
    'Expired' => 0,
    'Claimed' => 0,
];
$kpiResult = $conn->query("
    SELECT warranty_status, COUNT(*) AS total
    FROM warranty_records
    WHERE warranty_status IN ('Active','Expired','Claimed')
    GROUP BY warranty_status
");
if ($kpiResult) {
    while ($row = $kpiResult->fetch_assoc()) {
        $kpi[$row['warranty_status']] = (int)$row['total'];
    }
}

$sql = "
  SELECT w.warranty_id, w.customer_id, u.name AS customer_name, w.ebike_model,
         w.purchase_date, w.warranty_period, w.warranty_status, w.claim_date, w.created_at
  FROM warranty_records w
  LEFT JOIN users u ON u.user_id = w.customer_id
";
$where = [];
$params = [];
$types = '';

if ($search !== '') {
    $where[] = "(u.name LIKE ? OR w.warranty_id LIKE ? OR w.customer_id LIKE ? OR w.ebike_model LIKE ?)";
    $term = "%{$search}%";
    array_push($params, $term, $term, $term, $term);
    $types .= 'ssss';
}

if ($status !== 'All') {
    $where[] = "w.warranty_status = ?";
    $params[] = $status;
    $types .= 's';
}

if ($where) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY w.created_at DESC";

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Warranty Records - FixTrack Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/css/app-theme.css?v=20261014" rel="stylesheet">
</head>
<body>
  <?php include("navbaradmin.php"); ?>

  <main class="warranty-wrap">
    <h1 class="page-title">Warranty Records</h1>
    <p class="page-subtitle">Track and manage all repair warranties and coverage information</p>

    <section class="row g-3 warranty-kpi">
      <div class="col-md-4">
        <div class="kpi-card">
          <div>
            <div class="kpi-label">Active Warranties</div>
            <div class="kpi-value"><?= htmlspecialchars($kpi['Active']) ?></div>
          </div>
          <div class="kpi-icon icon-active"><i class="bi bi-check-lg"></i></div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="kpi-card">
          <div>
            <div class="kpi-label">Expired Warranties</div>
            <div class="kpi-value"><?= htmlspecialchars($kpi['Expired']) ?></div>
          </div>
          <div class="kpi-icon icon-expired"><i class="bi bi-exclamation-lg"></i></div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="kpi-card">
          <div>
            <div class="kpi-label">Claims Filed</div>
            <div class="kpi-value"><?= htmlspecialchars($kpi['Claimed']) ?></div>
          </div>
          <div class="kpi-icon icon-claimed"><i class="bi bi-clock"></i></div>
        </div>
      </div>
    </section>

    <form class="warranty-toolbar" method="get">
      <div class="searchbox">
        <i class="bi bi-search"></i>
        <input class="form-control search-input" type="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by customer, warranty ID, or bike model...">
      </div>
      <select class="form-select status-select" name="status" onchange="this.form.submit()">
        <?php foreach ($allowedStatuses as $option): ?>
          <option value="<?= htmlspecialchars($option) ?>" <?= $status === $option ? 'selected' : '' ?>><?= $option === 'All' ? 'All Status' : htmlspecialchars($option) ?></option>
        <?php endforeach; ?>
      </select>
      <a class="new-btn" href="newwarranty.php"><i class="bi bi-plus-lg"></i> New Warranty</a>
    </form>

    <section class="records-panel">
      <div class="table-responsive warranty-table-wrap">
        <table class="table table-hover">
          <thead>
            <tr>
              <th>Warranty ID</th>
              <th>Customer</th>
              <th>E-Bike Model</th>
              <th>Purchase Date</th>
              <th>Period</th>
              <th>Status</th>
              <th>Claim Date</th>
              <th>Created</th>
              <th style="width:150px;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($result && $result->num_rows > 0): ?>
              <?php while($row = $result->fetch_assoc()): ?>
                <tr>
                  <td>#<?= htmlspecialchars($row['warranty_id']) ?></td>
                  <td><?= htmlspecialchars($row['customer_name'] ?? 'Customer #'.$row['customer_id']) ?></td>
                  <td><?= htmlspecialchars($row['ebike_model']) ?></td>
                  <td><?= htmlspecialchars($row['purchase_date']) ?></td>
                  <td><?= htmlspecialchars($row['warranty_period']) ?> months</td>
                  <td>
                    <?php if ($row['warranty_status'] === 'Active'): ?>
                      <span class="badge text-bg-success">Active</span>
                    <?php elseif ($row['warranty_status'] === 'Expired'): ?>
                      <span class="badge text-bg-secondary">Expired</span>
                    <?php elseif ($row['warranty_status'] === 'Claimed'): ?>
                      <span class="badge text-bg-primary">Claimed</span>
                    <?php else: ?>
                      <span class="badge text-bg-danger">Rejected</span>
                    <?php endif; ?>
                  </td>
                  <td class="muted-cell"><?= htmlspecialchars($row['claim_date'] ?? '-') ?></td>
                  <td class="muted-cell"><?= htmlspecialchars($row['created_at']) ?></td>
                  <td>
                    <a class="action-link" href="editwarranty.php?id=<?= urlencode($row['warranty_id']) ?>">Edit</a>
                    <a class="action-link delete" href="deletewarranty.php?id=<?= urlencode($row['warranty_id']) ?>">Delete</a>
                  </td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr>
                <td colspan="9" class="text-center muted-cell py-4">No warranty records found.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>
  </main>
</body>
</html>
