<?php
session_start();
include("db.php");
include_once("schema_helpers.php");

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'Customer') {
    header("Location: login.php");
    exit();
}

header("Location: customerdashboard.php");
exit();

$customer_id = (int)$_SESSION['user_id'];
$tracking_number = trim($_GET['tracking_number'] ?? '');
$booking = null;

if ($tracking_number !== '') {
    $sql = "
      SELECT rb.tracking_number, rb.booking_status, rb.preferred_date, rb.preferred_time,
             rb.service_type, rb.warranty_status, rb.payment_status, rb.estimated_amount,
             r.ebike_model, r.issue_description, r.repair_status, r.amount
      FROM repair_bookings rb
      LEFT JOIN repairs r ON r.booking_id = rb.booking_id
      WHERE rb.customer_id = ? AND rb.tracking_number = ?
      LIMIT 1
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $customer_id, $tracking_number);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Track Repair - FixTrack</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
   <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
   <link href="assets/css/app-theme.css?v=20261014" rel="stylesheet">
</head>
<body>
  <?php include("navbarcustomer.php"); ?>

  <main class="tracking-shell">
    <div class="page-hero">
      <h3 class="mb-1">Repair Tracking</h3>
      <p>Tracking is scoped to your logged-in customer account for privacy.</p>
    </div>

    <div class="card tracking-card shadow-sm">
      <div class="card-body">
        <form method="get" class="row g-2 align-items-end mb-4">
          <div class="col-md">
            <label class="form-label fw-semibold">Tracking Number</label>
            <input type="text" class="form-control tracking-code" name="tracking_number" value="<?= htmlspecialchars($tracking_number) ?>" placeholder="Example: RS-20260915123000-12" required>
          </div>
          <div class="col-md-auto">
            <button class="btn btn-success w-100" type="submit"><i class="bi bi-search me-1"></i>Track</button>
          </div>
        </form>

        <?php if ($tracking_number === ''): ?>
          <div class="alert alert-info mb-0"><i class="bi bi-info-circle me-1"></i>Enter your repair tracking number to view its status.</div>
        <?php elseif (!$booking): ?>
          <div class="alert alert-warning mb-0"><i class="bi bi-exclamation-circle me-1"></i>Tracking number not found for your account.</div>
        <?php else: ?>
          <?php
            $repairStatus = $booking['repair_status'] ?: $booking['booking_status'];
            $payableAmount = (float)($booking['estimated_amount'] ?: $booking['amount'] ?: 0);
          ?>
          <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
            <div>
              <p class="text-muted mb-1">Tracking Number</p>
              <h5 class="tracking-code mb-0"><?= htmlspecialchars($booking['tracking_number']) ?></h5>
            </div>
            <span class="badge bg-<?= statusBadgeClass((string)$repairStatus) ?> align-self-start"><?= htmlspecialchars((string)$repairStatus) ?></span>
          </div>

          <div class="detail-grid">
            <div class="detail-box">
              <div class="detail-label">E-bike</div>
              <p class="detail-value"><?= htmlspecialchars($booking['ebike_model'] ?? 'Pending assessment') ?></p>
            </div>
            <div class="detail-box">
              <div class="detail-label">Schedule</div>
              <p class="detail-value"><?= htmlspecialchars(($booking['preferred_date'] ?? '') . ' - ' . ($booking['preferred_time'] ?? '')) ?></p>
            </div>
            <div class="detail-box">
              <div class="detail-label">Warranty</div>
              <p class="detail-value"><span class="badge bg-<?= statusBadgeClass((string)($booking['warranty_status'] ?? '')) ?>"><?= htmlspecialchars($booking['warranty_status'] ?? 'Pending') ?></span></p>
            </div>
            <div class="detail-box">
              <div class="detail-label">Payment</div>
              <p class="detail-value"><span class="badge bg-<?= statusBadgeClass((string)($booking['payment_status'] ?? '')) ?>"><?= htmlspecialchars($booking['payment_status'] ?? 'Pending') ?></span></p>
            </div>
            <div class="detail-box">
              <div class="detail-label">Service Type</div>
              <p class="detail-value"><?= htmlspecialchars($booking['service_type'] ?? 'Repair') ?></p>
            </div>
            <div class="detail-box">
              <div class="detail-label">Estimated Cost</div>
              <p class="detail-value"><?= $payableAmount > 0 ? 'PHP ' . number_format($payableAmount, 2) : 'Pending technician assessment' ?></p>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </main>

  <footer class="border-top py-3 bg-white">
    <div class="container small text-muted d-flex flex-wrap justify-content-between gap-2">
      <span>FixTrack Repair Tracking</span>
      <span>Secure customer-only lookup</span>
    </div>
  </footer>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
