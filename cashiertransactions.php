<?php
session_start();
include("db.php");
include_once("schema_helpers.php");
include_once("activity_log_helper.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Cashier') {
    header("Location: login.php");
    exit();
}

ensureRepairAutomationSchema($conn);
ensureStripeSchema($conn);
ensureNetcorepaySchema($conn);
syncAllBookingPaymentsToPayments($conn);

$bookings = $conn->query("
  SELECT rb.booking_id, r.repair_id, rb.customer_id, u.name AS customer_name, rb.service_type,
         rb.preferred_date, rb.warranty_status, rb.estimated_amount, rb.booking_status, rb.payment_status, rb.created_at,
         p.payment_id, p.method AS payment_method, p.status AS synced_payment_status, p.amount AS synced_amount,
         r.repair_status, pr.receipt_number
  FROM repair_bookings rb
  LEFT JOIN repairs r ON r.booking_id = rb.booking_id
  LEFT JOIN users u ON u.user_id = rb.customer_id
  LEFT JOIN payments p ON p.payment_id = (
    SELECT p2.payment_id
    FROM payments p2
    WHERE p2.repair_id = r.repair_id AND p2.customer_id = rb.customer_id
    ORDER BY p2.payment_id DESC
    LIMIT 1
  )
  LEFT JOIN payment_receipts pr ON pr.booking_id = rb.booking_id
  ORDER BY rb.created_at DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Payment Transactions - FixTrack</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .cashier-payments { max-width: min(1320px, calc(100% - 24px)); }
    .payment-table { font-size: .86rem; min-width: 1180px; table-layout: fixed; }
    .payment-table th,
    .payment-table td { padding: .6rem .55rem; vertical-align: middle; }
    .payment-table .col-id { width: 86px; }
    .payment-table .col-payment { width: 94px; }
    .payment-table .col-customer { width: 150px; }
    .payment-table .col-service { width: 105px; }
    .payment-table .col-date { width: 112px; }
    .payment-table .col-warranty { width: 95px; }
    .payment-table .col-method { width: 95px; }
    .payment-table .col-amount { width: 112px; }
    .payment-table .col-status { width: 126px; }
    .payment-table .col-details { width: 160px; }
    .payment-table .col-action { width: 142px; }
    .payment-table .text-truncate-cell { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .payment-actions { display: grid; gap: 6px; }
    .payment-actions .btn { width: 100%; }
  </style>
</head>
<body class="bg-light">
  <?php include("navbarcashier.php"); ?>
  <div class="container mt-4 cashier-payments">
    <div class="page-hero">
      <h3 class="mb-1">Payment Transactions</h3>
      <p>Review repair charges, payment status, and warranty-covered bookings.</p>
    </div>
    <?php if (isset($_GET['updated'])): ?><div class="alert alert-success">Payment record updated.</div><?php endif; ?>
    <?php if (isset($_GET['payment_not_ready'])): ?><div class="alert alert-warning">Payment can only be recorded after the cashier has saved an itemized payment summary.</div><?php endif; ?>
    <?php if (isset($_GET['summary_not_ready'])): ?><div class="alert alert-warning">A payment summary cannot be prepared for cancelled repairs.</div><?php endif; ?>
    <div class="card shadow-sm"><div class="card-body"><div class="table-responsive">
    <table class="table table-hover table-bordered mb-0 payment-table">
      <thead class="table-danger"><tr><th class="col-id">Booking ID</th><th class="col-payment">Payment ID</th><th class="col-customer">Customer</th><th class="col-service">Service</th><th class="col-date">Preferred Date</th><th class="col-warranty">Warranty</th><th class="col-method">Method</th><th class="col-amount">Amount</th><th class="col-status">Booking Status</th><th class="col-status">Payment</th><th class="col-details">Details</th><th class="col-action">Action</th></tr></thead>
      <tbody>
        <?php if ($bookings && $bookings->num_rows > 0): while($row = $bookings->fetch_assoc()): ?>
          <?php
            $displayStatus = in_array((string)$row['synced_payment_status'], ['Paid', 'Cancelled'], true)
              ? (string)$row['synced_payment_status']
              : (string)$row['payment_status'];
            $displayAmount = $row['synced_amount'] !== null ? (float)$row['synced_amount'] : (float)$row['estimated_amount'];
          ?>
          <tr>
            <td><?= htmlspecialchars($row['booking_id']) ?></td>
            <td><?= $row['payment_id'] ? htmlspecialchars((string)$row['payment_id']) : '<span class="text-muted">Not synced</span>' ?></td>
            <td class="text-truncate-cell" title="<?= htmlspecialchars($row['customer_name'] ?? 'Customer #'.$row['customer_id']) ?>"><?= htmlspecialchars($row['customer_name'] ?? 'Customer #'.$row['customer_id']) ?></td>
            <td class="text-truncate-cell" title="<?= htmlspecialchars($row['service_type'] ?? '') ?>"><?= htmlspecialchars($row['service_type'] ?? '') ?></td>
            <td><?= htmlspecialchars($row['preferred_date'] ?? '') ?></td>
            <td><span class="badge bg-<?= $row['warranty_status'] === 'Valid' ? 'success' : 'secondary' ?>"><?= htmlspecialchars($row['warranty_status']) ?></span></td>
            <td><span class="badge bg-secondary"><?= htmlspecialchars($row['payment_method'] ?? 'Cashier') ?></span></td>
            <td>PHP <?= number_format($displayAmount, 2) ?></td>
            <td><?= htmlspecialchars($row['booking_status']) ?></td>
            <td><span class="badge bg-<?= $displayStatus === 'Paid' ? 'success' : ($displayStatus === 'Cancelled' ? 'danger' : 'warning') ?>"><?= htmlspecialchars($displayStatus) ?></span></td>
            <td>
              <div class="payment-actions">
              <?php if (!empty($row['repair_id'])): ?>
                <?php if ($displayStatus === 'Paid' && !empty($row['receipt_number'])): ?>
                  <a href="repairdetails.php?id=<?= urlencode((string)$row['repair_id']) ?>#receipt" class="btn btn-sm btn-outline-secondary"><i class="bi bi-receipt me-1"></i>Receipt <?= htmlspecialchars($row['receipt_number']) ?></a>
                <?php else: ?>
                  <a href="cashierpaymentbreakdown.php?repair_id=<?= urlencode((string)$row['repair_id']) ?>" class="btn btn-sm btn-outline-success"><i class="bi bi-receipt me-1"></i>Summary</a>
                  <a href="repairdetails.php?id=<?= urlencode((string)$row['repair_id']) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye me-1"></i>View</a>
                <?php endif; ?>
              <?php else: ?>
                <span class="text-muted">No repair</span>
              <?php endif; ?>
              </div>
            </td>
            <td>
              <?php if ($displayStatus === 'Paid'): ?>
                <span class="text-success fw-semibold"><i class="bi bi-check-circle me-1"></i>Recorded</span>
              <?php elseif (in_array($displayStatus, ['Pending Payment', 'Payment Submitted'], true)): ?>
                <span class="text-muted small">Waiting for customer payment</span>
              <?php else: ?>
                <span class="text-muted small">Awaiting payment summary</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endwhile; else: ?>
          <tr><td colspan="12" class="text-center text-muted">No payment records found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
    </div></div></div>
  </div>
</body>
</html>
