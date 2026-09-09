<?php
session_start();
include("db.php");
include_once("schema_helpers.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Cashier') {
    header("Location: login.php");
    exit();
}

ensureRepairAutomationSchema($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['booking_id'], $_POST['payment_status'])) {
    $booking_id = intval($_POST['booking_id']);
    $payment_status = $_POST['payment_status'] === 'Paid' ? 'Paid' : 'Pending';
    $stmt = $conn->prepare("UPDATE repair_bookings SET payment_status=? WHERE booking_id=?");
    $stmt->bind_param("si", $payment_status, $booking_id);
    $stmt->execute();
    header("Location: cashiertransactions.php?updated=1");
    exit();
}

$bookings = $conn->query("
  SELECT rb.booking_id, rb.customer_id, u.name AS customer_name, rb.service_type,
         rb.preferred_date, rb.warranty_status, rb.estimated_amount, rb.booking_status, rb.payment_status, rb.created_at
  FROM repair_bookings rb
  LEFT JOIN users u ON u.user_id = rb.customer_id
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
</head>
<body class="bg-light">
  <?php include("navbarcashier.php"); ?>
  <div class="container mt-4">
    <div class="page-hero">
      <h3 class="mb-1">Payment Transactions</h3>
      <p>Review repair charges, payment status, and warranty-covered bookings.</p>
    </div>
    <?php if (isset($_GET['updated'])): ?><div class="alert alert-success">Payment status updated.</div><?php endif; ?>
    <div class="card shadow-sm"><div class="card-body"><div class="table-responsive">
    <table class="table table-hover table-bordered mb-0">
      <thead class="table-danger"><tr><th>Booking ID</th><th>Customer</th><th>Service</th><th>Preferred Date</th><th>Warranty</th><th>Amount</th><th>Booking Status</th><th>Payment</th><th>Action</th></tr></thead>
      <tbody>
        <?php if ($bookings && $bookings->num_rows > 0): while($row = $bookings->fetch_assoc()): ?>
          <tr>
            <td><?= htmlspecialchars($row['booking_id']) ?></td>
            <td><?= htmlspecialchars($row['customer_name'] ?? 'Customer #'.$row['customer_id']) ?></td>
            <td><?= htmlspecialchars($row['service_type'] ?? '') ?></td>
            <td><?= htmlspecialchars($row['preferred_date'] ?? '') ?></td>
            <td><span class="badge bg-<?= $row['warranty_status'] === 'Valid' ? 'success' : 'secondary' ?>"><?= htmlspecialchars($row['warranty_status']) ?></span></td>
            <td>PHP <?= number_format((float)$row['estimated_amount'], 2) ?></td>
            <td><?= htmlspecialchars($row['booking_status']) ?></td>
            <td><span class="badge bg-<?= $row['payment_status'] === 'Paid' ? 'success' : 'warning' ?>"><?= htmlspecialchars($row['payment_status']) ?></span></td>
            <td>
              <form method="post" class="d-flex gap-2">
                <input type="hidden" name="booking_id" value="<?= htmlspecialchars($row['booking_id']) ?>">
                <select name="payment_status" class="form-select form-select-sm">
                  <option <?= $row['payment_status'] === 'Pending' ? 'selected' : '' ?>>Pending</option>
                  <option <?= $row['payment_status'] === 'Paid' ? 'selected' : '' ?>>Paid</option>
                </select>
                <button class="btn btn-sm btn-danger">Save</button>
              </form>
            </td>
          </tr>
        <?php endwhile; else: ?>
          <tr><td colspan="9" class="text-center text-muted">No payment records found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
    </div></div></div>
  </div>
</body>
</html>
