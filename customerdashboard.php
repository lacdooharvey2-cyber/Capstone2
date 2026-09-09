<?php
session_start();
include("db.php");

// Guard: only allow Customers
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Customer') {
    header("Location: login.php");
    exit();
}

$customer_id = $_SESSION['user_id'];

$customerIdSql = intval($customer_id);
$ebikesOwned = $conn->query("SELECT COUNT(*) AS total FROM warranty_records WHERE customer_id=$customerIdSql")->fetch_assoc()['total'];
$activeRepairs = $conn->query("SELECT COUNT(*) AS total FROM repairs WHERE customer_id=$customerIdSql AND repair_status IN ('Pending','In Progress')")->fetch_assoc()['total'];
$completedRepairs = $conn->query("SELECT COUNT(*) AS total FROM repairs WHERE customer_id=$customerIdSql AND repair_status='Completed'")->fetch_assoc()['total'];
$activeWarranties = $conn->query("SELECT COUNT(*) AS total FROM warranty_records WHERE customer_id=$customerIdSql AND warranty_status='Active'")->fetch_assoc()['total'];
$expiringSoon = $conn->query("
  SELECT COUNT(*) AS total
  FROM warranty_records
  WHERE customer_id=$customerIdSql
    AND warranty_status='Active'
    AND DATE_ADD(purchase_date, INTERVAL warranty_period MONTH) BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
")->fetch_assoc()['total'];

$myEbikes = $conn->query("
  SELECT ebike_model, purchase_date, warranty_period, warranty_status
  FROM warranty_records
  WHERE customer_id=$customerIdSql
  ORDER BY created_at DESC
  LIMIT 5
");

$recentRepairs = $conn->query("
  SELECT r.ebike_model, r.issue_description, r.repair_status, r.created_at,
         rb.booking_id, rb.payment_status,
         COALESCE(NULLIF(rb.estimated_amount, 0), r.amount, 0) AS payable_amount
  FROM repairs r
  LEFT JOIN repair_bookings rb ON rb.booking_id = r.booking_id
  WHERE r.customer_id=$customerIdSql
  ORDER BY r.created_at DESC
  LIMIT 5
");

$activeRepair = $conn->query("
  SELECT ebike_model, issue_description, repair_status, created_at
  FROM repairs
  WHERE customer_id=$customerIdSql AND repair_status IN ('Pending','In Progress','Completed')
  ORDER BY FIELD(repair_status, 'Pending', 'In Progress', 'Completed'), created_at DESC
  LIMIT 1
")->fetch_assoc();

$statusSteps = ['Booked' => 0, 'In Progress' => 1, 'Completed' => 2];
$currentStep = 0;
if ($activeRepair) {
    $currentStep = $activeRepair['repair_status'] === 'Completed' ? 2 : ($activeRepair['repair_status'] === 'In Progress' ? 1 : 0);
}
$progressWidth = $activeRepair ? ($currentStep === 0 ? 33 : ($currentStep === 1 ? 66 : 100)) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Customer Dashboard - FixTrack</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    .dashboard-container { margin: 30px auto; max-width: 1180px; }
    .page-hero { animation: portalRise .6s ease both; }
    .customer-kpis .kpi-card { animation: portalRise .55s ease both; }
    .customer-kpis > .col-md:nth-child(2) .kpi-card { animation-delay: .06s; }
    .customer-kpis > .col-md:nth-child(3) .kpi-card { animation-delay: .12s; }
    .customer-kpis > .col-md:nth-child(4) .kpi-card { animation-delay: .18s; }
    .customer-kpis > .col-md:nth-child(5) .kpi-card { animation-delay: .24s; }
    .kpi-card { min-height: 128px; }
    .kpi-body { align-items: flex-start; height: 100%; padding: 18px; position: relative; }
    .kpi-label { color: var(--app-muted); font-size: .82rem; font-weight: 700; letter-spacing: 0; margin-bottom: .5rem; max-width: calc(100% - 48px); min-height: 2.2em; }
    .kpi-value { font-size: clamp(1.05rem, 1.8vw, 1.8rem); font-weight: 800; line-height: 1; margin-bottom: .55rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .kpi-note { color: var(--app-muted); font-size: .78rem; line-height: 1.3; margin-bottom: 0; }
    .kpi-icon { align-items: center; background: #fff1f2; border-radius: 8px; display: inline-flex; height: 42px; justify-content: center; position: absolute; right: 18px; top: 18px; width: 42px; }
    .section-title { margin-top: 30px; margin-bottom: 15px; font-weight: 800; color: var(--app-ink); }
    .portal-panel { background: var(--app-card); border: 1px solid var(--app-line); border-radius: 12px; box-shadow: 0 14px 36px rgba(31, 41, 55, .08); }
    .portal-panel .table thead th { background: #fff1f2; color: #842029; border-color: #f5c2c7; }
    .portal-panel .btn-danger { box-shadow: 0 8px 18px rgba(220, 53, 69, .14); }
    .portal-panel .btn-danger:hover { transform: translateY(-1px); }
    .map-container { height: 300px; border-radius: 12px; overflow: hidden; }
    .repair-step { flex: 1; text-align: center; font-size: 0.9rem; color: #6c757d; }
    .repair-step .step-dot { width: 34px; height: 34px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; border: 2px solid #dee2e6; background: #fff; font-weight: 700; margin-bottom: 6px; }
    .repair-step.active { color: #dc3545; font-weight: 700; }
    .repair-step.active .step-dot { border-color: #dc3545; background: #dc3545; color: #fff; }
    @keyframes portalRise {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: translateY(0); }
    }
    @media (prefers-reduced-motion: reduce) {
      *, *::before, *::after {
        animation-duration: .01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: .01ms !important;
      }
    }
  </style>
</head>
<body>
  <?php include("navbarcustomer.php"); ?>

  <div class="dashboard-container">
    <div class="page-hero">
      <h2 class="mb-1">Welcome, FixTrack Customer</h2>
      <p>Book repairs, check warranty coverage, and track your e-bike service in one place.</p>
    </div>

    <?php if (($_GET['payment'] ?? '') === 'success'): ?>
      <div class="alert alert-success d-flex align-items-center gap-2" role="alert"><i class="bi bi-check-circle-fill"></i> Payment received. Your repair booking is now marked as paid.</div>
    <?php elseif (($_GET['payment'] ?? '') === 'cancelled'): ?>
      <div class="alert alert-warning d-flex align-items-center gap-2" role="alert"><i class="bi bi-exclamation-circle-fill"></i> Payment was cancelled. You can try again when you are ready.</div>
    <?php elseif (($_GET['payment'] ?? '') === 'error'): ?>
      <div class="alert alert-danger d-flex align-items-center gap-2" role="alert"><i class="bi bi-exclamation-triangle-fill"></i> We could not verify the payment. Please contact support before trying again.</div>
    <?php endif; ?>

    <!-- Summary Cards -->
    <div class="row g-3 customer-kpis">
      <?php
      $cards = [
        ['E-bikes Owned', $ebikesOwned, 'Registered to account', 'text-danger', 'bi-bicycle'],
        ['Active Repairs', $activeRepairs, 'Pending or in progress', 'text-primary', 'bi-tools'],
        ['Completed Repairs', $completedRepairs, 'Finished service jobs', 'text-success', 'bi-check2-circle'],
        ['Active Warranties', $activeWarranties, 'Currently covered', 'text-warning', 'bi-shield-check'],
        ['Expiring Soon', $expiringSoon, 'Within 30 days', 'text-danger', 'bi-calendar-x'],
      ];
      foreach ($cards as $card):
      ?>
        <div class="col-md">
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

    <h4 class="section-title">Active Repair Status</h4>
    <div class="portal-panel p-4 mb-4">
      <?php if ($activeRepair): ?>
        <div class="d-flex justify-content-between mb-2">
          <div>
            <h6 class="mb-1"><?= htmlspecialchars($activeRepair['ebike_model']) ?></h6>
            <p class="text-muted mb-0"><?= htmlspecialchars($activeRepair['issue_description']) ?></p>
          </div>
          <span class="badge bg-<?= $activeRepair['repair_status'] === 'Completed' ? 'success' : ($activeRepair['repair_status'] === 'In Progress' ? 'primary' : 'warning') ?> align-self-start"><?= htmlspecialchars($activeRepair['repair_status']) ?></span>
        </div>
        <div class="progress my-4" style="height: 10px;">
          <div class="progress-bar bg-danger" style="width: <?= $progressWidth ?>%"></div>
        </div>
        <div class="d-flex">
          <?php foreach (array_keys($statusSteps) as $label): $isActive = $statusSteps[$label] <= $currentStep; ?>
            <div class="repair-step <?= $isActive ? 'active' : '' ?>">
              <div class="step-dot"><?= $statusSteps[$label] + 1 ?></div>
              <div><?= htmlspecialchars($label) ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p class="text-muted mb-0">No active repair status yet.</p>
      <?php endif; ?>
    </div>

    <!-- My E-bikes -->
    <h4 class="section-title">My E-bikes</h4>
    <div class="portal-panel p-4 mb-4">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <p class="text-muted mb-0">E-bikes registered to your account</p>
        <a href="customerbookrepair.php" class="btn btn-danger"><i class="bi bi-plus-circle me-1"></i>Book Repair</a>
      </div>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead><tr><th>E-bike</th><th>Purchase Date</th><th>Period</th><th>Status</th></tr></thead>
          <tbody>
            <?php if ($myEbikes && $myEbikes->num_rows > 0): while($bike = $myEbikes->fetch_assoc()): ?>
              <tr>
                <td><?= htmlspecialchars($bike['ebike_model']) ?></td>
                <td><?= htmlspecialchars($bike['purchase_date']) ?></td>
                <td><?= htmlspecialchars($bike['warranty_period']) ?> months</td>
                <td><span class="badge bg-<?= $bike['warranty_status'] === 'Active' ? 'success' : 'secondary' ?>"><?= htmlspecialchars($bike['warranty_status']) ?></span></td>
              </tr>
            <?php endwhile; else: ?>
              <tr><td colspan="4" class="text-center text-muted">No e-bikes registered yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Repair History -->
    <h4 class="section-title">Recent Repairs</h4>
    <div class="portal-panel p-4 mb-4">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead><tr><th>E-bike</th><th>Problem</th><th>Status</th><th>Date</th><th>Payment</th></tr></thead>
          <tbody>
            <?php if ($recentRepairs && $recentRepairs->num_rows > 0): while($repair = $recentRepairs->fetch_assoc()): ?>
              <tr>
                <td><?= htmlspecialchars($repair['ebike_model']) ?></td>
                <td><?= htmlspecialchars($repair['issue_description']) ?></td>
                <td><span class="badge bg-<?= $repair['repair_status'] === 'Completed' ? 'success' : ($repair['repair_status'] === 'In Progress' ? 'primary' : 'warning') ?>"><?= htmlspecialchars($repair['repair_status']) ?></span></td>
                <td><?= htmlspecialchars($repair['created_at']) ?></td>
                <td>
                  <?php if ((float)($repair['payable_amount'] ?? 0) > 0 && ($repair['payment_status'] ?? 'Pending') !== 'Paid'): ?>
                    <form action="create_checkout_session.php" method="post">
                      <input type="hidden" name="booking_id" value="<?= htmlspecialchars((string)$repair['booking_id']) ?>">
                      <button class="btn btn-sm btn-danger" type="submit"><i class="bi bi-credit-card me-1"></i>Pay PHP <?= number_format((float)$repair['payable_amount'], 2) ?></button>
                    </form>
                  <?php elseif ((float)($repair['payable_amount'] ?? 0) > 0): ?>
                    <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Paid</span>
                  <?php else: ?>
                    <span class="badge bg-info text-dark"><i class="bi bi-shield-check me-1"></i>Covered</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endwhile; else: ?>
              <tr><td colspan="5" class="text-center text-muted">No repair history yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Visit Our Shop -->
    <h4 class="section-title">KDA/KUDA and NWOW Stores in Laguna</h4>
    <div class="portal-panel p-4 mb-4">
      <p class="text-muted">Find nearby KDA/KUDA and NWOW e-bike stores around Laguna.</p>
      <div class="map-container mb-3">
        <iframe 
          src="https://www.google.com/maps?q=NWOW%20KUDA%20KDA%20E-bike%20Laguna%20Philippines&output=embed"
          width="100%" height="100%" style="border:0;" allowfullscreen="" loading="lazy">
        </iframe>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
