<?php
session_start();
include("db.php");
include_once("schema_helpers.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Technician') {
    header("Location: login.php");
    exit();
}

ensureRepairAutomationSchema($conn);

$user_id = intval($_SESSION['user_id']);
$repairs = $conn->query("
  SELECT r.repair_id, r.created_at, r.customer_id, u.name AS customer_name, u.contact_number,
         r.ebike_model, r.issue_description, r.warranty_status, r.amount, r.repair_status
  FROM repairs r
  LEFT JOIN users u ON u.user_id = r.customer_id
  WHERE r.technician_id = $user_id OR r.technician_id IS NULL
  ORDER BY r.created_at DESC
");

$warranties = $conn->query("
  SELECT w.warranty_id, w.customer_id, u.name AS customer_name, w.ebike_model,
         w.warranty_period, w.warranty_status, w.claim_date, w.created_at
  FROM warranty_records w
  LEFT JOIN users u ON u.user_id = w.customer_id
  ORDER BY w.created_at DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Manage Repair & Warranty - FixTrack</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background: #f8f9fa; font-family: 'Segoe UI', sans-serif; }
    .container { margin-top: 30px; animation: fadeInUp 0.6s ease; }
    @keyframes fadeInUp { from { opacity:0; transform:translateY(20px);} to { opacity:1; transform:translateY(0);} }
    .card { border-radius: 8px; }
    .badge { font-size: 0.9em; }
  </style>
</head>
<body>
  <?php include("navbartechnician.php"); ?>

  <div class="container">
    <div class="page-hero">
      <h3 class="mb-1">Repair & Warranty Management</h3>
      <p>Manage assigned repair tickets, set repair costs, and update warranty records.</p>
    </div>

    <?php if (isset($_GET['updated'])): ?>
      <div class="alert alert-success">Repair status updated.</div>
    <?php elseif (isset($_GET['error'])): ?>
      <div class="alert alert-danger">Unable to update repair status.</div>
    <?php endif; ?>

    <ul class="nav nav-tabs mb-3" id="myTab" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link active" id="repairs-tab" data-bs-toggle="tab" data-bs-target="#repairs" type="button" role="tab">Repair Tickets</button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="warranty-tab" data-bs-toggle="tab" data-bs-target="#warranty" type="button" role="tab">Warranty Records</button>
      </li>
    </ul>

    <div class="tab-content">
      <div class="tab-pane fade show active" id="repairs" role="tabpanel">
        <div class="card shadow-sm"><div class="card-body"><div class="table-responsive">
        <table class="table table-hover table-bordered mb-0">
          <thead class="table-danger">
            <tr>
              <th>ID</th><th>Date</th><th>Customer</th><th>Contact</th><th>E-Bike</th><th>Issue</th><th>Warranty</th><th>Cost</th><th>Status</th><th style="width:360px;">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($repairs && $repairs->num_rows > 0): ?>
              <?php while($r = $repairs->fetch_assoc()): ?>
                <tr>
                  <td><?= htmlspecialchars($r['repair_id']) ?></td>
                  <td><?= htmlspecialchars($r['created_at']) ?></td>
                  <td><?= htmlspecialchars($r['customer_name'] ?? 'Customer #'.$r['customer_id']) ?></td>
                  <td><?= htmlspecialchars($r['contact_number'] ?? '') ?></td>
                  <td><?= htmlspecialchars($r['ebike_model']) ?></td>
                  <td><?= htmlspecialchars($r['issue_description']) ?></td>
                  <td><span class="badge bg-<?= $r['warranty_status'] === 'Valid' ? 'success' : 'secondary' ?>"><?= htmlspecialchars($r['warranty_status']) ?></span></td>
                  <td><?= (float)$r['amount'] > 0 ? 'PHP ' . number_format((float)$r['amount'], 2) : 'Pending' ?></td>
                  <td><span class="badge bg-<?php echo $r['repair_status']=='Completed'?'success':($r['repair_status']=='In Progress'?'primary':'warning'); ?>"><?= htmlspecialchars($r['repair_status']) ?></span></td>
                  <td>
                    <a href="repairdetails.php?id=<?= urlencode($r['repair_id']) ?>" class="btn btn-sm btn-outline-secondary mb-1">View Details</a>
                    <form method="post" action="technicianupdaterepair.php" class="d-flex gap-2">
                      <input type="hidden" name="repair_id" value="<?= htmlspecialchars($r['repair_id']) ?>">
                      <input type="number" class="form-control form-control-sm" name="amount" min="0" step="0.01" value="<?= htmlspecialchars($r['amount']) ?>" aria-label="Repair cost">
                      <select name="status" class="form-select form-select-sm">
                        <option <?= $r['repair_status'] === 'Pending' ? 'selected' : '' ?>>Pending</option>
                        <option <?= $r['repair_status'] === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                        <option <?= $r['repair_status'] === 'Completed' ? 'selected' : '' ?>>Completed</option>
                        <option <?= $r['repair_status'] === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                      </select>
                      <button class="btn btn-sm btn-danger">Save</button>
                    </form>
                  </td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr><td colspan="10" class="text-center text-muted">No repair tickets found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
        </div></div></div>
      </div>

      <div class="tab-pane fade" id="warranty" role="tabpanel">
        <div class="card shadow-sm"><div class="card-body"><div class="table-responsive">
        <table class="table table-hover table-bordered mb-0">
          <thead class="table-danger">
            <tr>
              <th>ID</th><th>Customer</th><th>E-Bike</th><th>Coverage</th><th>Status</th><th>Claim Date</th><th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($warranties && $warranties->num_rows > 0): ?>
              <?php while($w = $warranties->fetch_assoc()): ?>
                <tr>
                  <td><?= htmlspecialchars($w['warranty_id']) ?></td>
                  <td><?= htmlspecialchars($w['customer_name'] ?? 'Customer #'.$w['customer_id']) ?></td>
                  <td><?= htmlspecialchars($w['ebike_model']) ?></td>
                  <td><?= htmlspecialchars($w['warranty_period']) ?> months</td>
                  <td><span class="badge bg-<?php echo $w['warranty_status']=='Active'?'success':'secondary'; ?>"><?= htmlspecialchars($w['warranty_status']) ?></span></td>
                  <td><?= htmlspecialchars($w['claim_date'] ?? '') ?></td>
                  <td><a class="btn btn-sm btn-outline-danger" href="technicianwarrantyverify.php?id=<?= urlencode($w['warranty_id']) ?>">Verify</a></td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr><td colspan="7" class="text-center text-muted">No warranty records found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
        </div></div></div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
