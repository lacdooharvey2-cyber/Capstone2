<?php
declare(strict_types=1);

session_start();
require 'db.php';
require_once 'schema_helpers.php';

ensureRepairAutomationSchema($conn);

$role = $_SESSION['role'] ?? '';
$userId = (int)($_SESSION['user_id'] ?? 0);
$allowedRoles = ['AssistantAdmin', 'Admin', 'AssistantSuperAdmin', 'SuperAdmin', 'Technician', 'HeadTechnician', 'Cashier', 'Customer'];
if ($userId <= 0 || !in_array($role, $allowedRoles, true)) {
    header('Location: login.php');
    exit();
}

$repairId = (int)($_GET['id'] ?? 0);
if ($repairId <= 0) {
    header('Location: ' . ($role === 'Customer' ? 'customerdashboard.php' : 'adminrepairs.php'));
    exit();
}

$scope = '';
if ($role === 'Customer') {
    $scope = ' AND r.customer_id = ' . $userId;
} elseif ($role === 'Technician') {
    $scope = ' AND r.technician_id = ' . $userId;
}

$stmt = $conn->prepare("SELECT
    r.repair_id, r.booking_id, r.ebike_model, r.issue_description, r.proof_file, r.repair_status,
    r.warranty_status, r.amount, r.created_at, r.updated_at,
    c.name AS customer_name, c.custom_id AS customer_custom_id, c.email AS customer_email, c.contact_number AS customer_contact,
    t.name AS technician_name, t.custom_id AS technician_custom_id,
    rb.service_type, rb.preferred_date, rb.preferred_time, rb.booking_status,
    rb.payment_status, rb.estimated_amount, rb.paid_at, rb.tracking_number
  FROM repairs r
  LEFT JOIN users c ON c.user_id = r.customer_id
  LEFT JOIN users t ON t.user_id = r.technician_id
  LEFT JOIN repair_bookings rb ON rb.booking_id = r.booking_id
  WHERE r.repair_id = ? {$scope}
  LIMIT 1");
$stmt->bind_param('i', $repairId);
$stmt->execute();
$repair = $stmt->get_result()->fetch_assoc();

if (!$repair) {
    http_response_code(404);
    $pageError = 'Repair record not found or you do not have access to it.';
}

$serviceReport = null;
$chargeItems = [];
if ($repair) {
    $reportStmt = $conn->prepare('SELECT tr.*, u.name AS report_technician_name FROM technician_repair_reports tr LEFT JOIN users u ON u.user_id=tr.technician_id WHERE tr.repair_id=? LIMIT 1');
    $reportStmt->bind_param('i', $repairId);
    $reportStmt->execute();
    $serviceReport = $reportStmt->get_result()->fetch_assoc();
    if ($serviceReport) {
        $itemStmt = $conn->prepare('SELECT item_type, item_name, quantity, unit_price FROM repair_charge_items WHERE report_id=? ORDER BY item_id');
        $itemStmt->bind_param('i', $serviceReport['report_id']);
        $itemStmt->execute();
        $chargeItems = $itemStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

function detailValue(array $row, string $key, string $fallback = 'Not recorded'): string
{
    $value = trim((string)($row[$key] ?? ''));
    return $value !== '' ? $value : $fallback;
}

$backUrl = match ($role) {
    'Customer' => 'customerdashboard.php',
    'Technician', 'HeadTechnician' => 'technicianrepair.php',
    'Cashier' => 'cashiertransactions.php',
    default => 'adminrepairs.php',
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Repair Details - FixTrack</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    body { background: #f8f9fa; color: #212529; font-family: 'Segoe UI', sans-serif; }
    .details-wrap { max-width: 1050px; margin: 30px auto; padding: 0 16px 40px; }
    .details-card { background: #fff; border: 1px solid #dee2e6; border-radius: 12px; box-shadow: 0 .35rem 1rem rgba(31,41,55,.08); }
    .detail-label { color: #6c757d; font-size: .78rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
    .detail-value { color: #212529; margin-bottom: 0; overflow-wrap: anywhere; }
    .issue-box { white-space: pre-wrap; background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 8px; padding: 14px; }
  </style>
</head>
<body>
<?php
$navbar = match ($role) {
    'Customer' => 'navbarcustomer.php',
    'Technician', 'HeadTechnician' => 'navbartechnician.php',
    'Cashier' => 'navbarcashier.php',
    default => 'navbaradmin.php',
};
include $navbar;
?>
<main class="details-wrap">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
      <h1 class="h3 mb-1">Repair Details</h1>
      <p class="text-secondary mb-0">Complete service, warranty, and payment information.</p>
    </div>
    <a href="<?= htmlspecialchars($backUrl) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
  </div>

  <?php if (isset($pageError)): ?>
    <div class="details-card p-4 alert alert-danger mb-0"><?= htmlspecialchars($pageError) ?></div>
  <?php else: ?>
    <div class="details-card p-4 mb-4">
      <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
        <div>
          <p class="detail-label mb-1">Repair ID</p>
          <h2 class="h4 mb-2">#<?= htmlspecialchars((string)$repair['repair_id']) ?></h2>
          <p class="text-secondary mb-0"><?= htmlspecialchars(detailValue($repair, 'ebike_model')) ?></p>
        </div>
        <div class="d-flex gap-2">
          <span class="badge bg-<?= $repair['repair_status'] === 'Completed' ? 'success' : ($repair['repair_status'] === 'In Progress' ? 'primary' : ($repair['repair_status'] === 'Cancelled' ? 'danger' : 'warning')) ?> align-self-start"><?= htmlspecialchars($repair['repair_status']) ?></span>
          <span class="badge bg-<?= $repair['payment_status'] === 'Paid' ? 'success' : 'secondary' ?> align-self-start"><?= htmlspecialchars(detailValue($repair, 'payment_status', 'No booking')) ?></span>
        </div>
      </div>
    </div>

    <div class="row g-4">
      <div class="col-lg-7">
        <section class="details-card p-4 h-100">
          <h2 class="h5 mb-4"><i class="bi bi-tools text-danger me-2"></i>Repair Information</h2>
          <div class="row g-3">
            <div class="col-sm-6"><p class="detail-label">Service type</p><p class="detail-value"><?= htmlspecialchars(detailValue($repair, 'service_type')) ?></p></div>
            <div class="col-sm-6"><p class="detail-label">Warranty result</p><p class="detail-value"><?= htmlspecialchars(detailValue($repair, 'warranty_status')) ?></p></div>
            <div class="col-sm-6"><p class="detail-label">Created</p><p class="detail-value"><?= htmlspecialchars(detailValue($repair, 'created_at')) ?></p></div>
            <div class="col-sm-6"><p class="detail-label">Last updated</p><p class="detail-value"><?= htmlspecialchars(detailValue($repair, 'updated_at')) ?></p></div>
            <div class="col-12"><p class="detail-label">Reported issue</p><div class="issue-box"><?= htmlspecialchars(detailValue($repair, 'issue_description')) ?></div></div>
            <div class="col-12">
              <p class="detail-label">Issue proof</p>
              <?php if (!empty($repair['proof_file'])): ?>
                <a class="btn btn-outline-secondary btn-sm" href="<?= htmlspecialchars($repair['proof_file']) ?>" target="_blank"><i class="bi bi-paperclip me-1"></i>Open attachment</a>
              <?php else: ?>
                <p class="detail-value">No attachment uploaded</p>
              <?php endif; ?>
            </div>
          </div>
        </section>
      </div>
      <div class="col-lg-5">
        <section class="details-card p-4 h-100">
          <h2 class="h5 mb-4"><i class="bi bi-person text-danger me-2"></i>Customer</h2>
          <p class="detail-label">Name</p><p class="detail-value mb-3"><?= htmlspecialchars(detailValue($repair, 'customer_name')) ?></p>
          <p class="detail-label">Customer ID</p><p class="detail-value mb-3"><?= htmlspecialchars(detailValue($repair, 'customer_custom_id')) ?></p>
          <p class="detail-label">Email</p><p class="detail-value mb-3"><?= htmlspecialchars(detailValue($repair, 'customer_email')) ?></p>
          <p class="detail-label">Contact</p><p class="detail-value"><?= htmlspecialchars(detailValue($repair, 'customer_contact')) ?></p>
        </section>
      </div>
      <div class="col-lg-6">
        <section class="details-card p-4 h-100">
          <h2 class="h5 mb-4"><i class="bi bi-person-gear text-danger me-2"></i>Assignment & Schedule</h2>
          <p class="detail-label">Technician</p><p class="detail-value mb-3"><?= htmlspecialchars(detailValue($repair, 'technician_name')) ?><?= trim((string)($repair['technician_custom_id'] ?? '')) !== '' ? ' (' . htmlspecialchars($repair['technician_custom_id']) . ')' : '' ?></p>
          <p class="detail-label">Preferred date</p><p class="detail-value mb-3"><?= htmlspecialchars(detailValue($repair, 'preferred_date')) ?></p>
          <p class="detail-label">Preferred time</p><p class="detail-value"><?= htmlspecialchars(detailValue($repair, 'preferred_time')) ?></p>
        </section>
      </div>
      <div class="col-lg-6">
        <section class="details-card p-4 h-100">
          <h2 class="h5 mb-4"><i class="bi bi-receipt text-danger me-2"></i>Cost & Payment</h2>
          <p class="detail-label">Technician repair cost</p><p class="detail-value mb-3"><?= (float)$repair['amount'] > 0 ? 'PHP ' . number_format((float)$repair['amount'], 2) : 'Pending assessment' ?></p>
          <p class="detail-label">Booking estimate</p><p class="detail-value mb-3"><?= (float)$repair['estimated_amount'] > 0 ? 'PHP ' . number_format((float)$repair['estimated_amount'], 2) : 'Not recorded' ?></p>
          <p class="detail-label">Booking status</p><p class="detail-value mb-3"><?= htmlspecialchars(detailValue($repair, 'booking_status', 'No booking')) ?></p>
          <p class="detail-label">Tracking number</p><p class="detail-value"><?= htmlspecialchars(detailValue($repair, 'tracking_number')) ?></p>
        </section>
      </div>
    </div>
    <section class="details-card p-4 mt-4">
      <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
        <div>
          <h2 class="h5 mb-1"><i class="bi bi-receipt-cutoff text-success me-2"></i>Itemized Service Report</h2>
          <p class="text-secondary mb-0">Work completed and a transparent breakdown of the customer payment.</p>
        </div>
        <?php if ($serviceReport): ?><span class="badge bg-success">Reported by <?= htmlspecialchars($serviceReport['report_technician_name'] ?? 'Technician') ?></span><?php endif; ?>
      </div>
      <?php if ($serviceReport): ?>
        <?php if (trim((string)$serviceReport['work_performed']) !== ''): ?><p class="detail-label">Work performed</p><div class="issue-box mb-3"><?= htmlspecialchars($serviceReport['work_performed']) ?></div><?php endif; ?>
        <?php if (trim((string)$serviceReport['technician_notes']) !== ''): ?><p class="detail-label">Technician notes</p><div class="issue-box mb-3"><?= htmlspecialchars($serviceReport['technician_notes']) ?></div><?php endif; ?>
        <div class="table-responsive"><table class="table align-middle mb-0"><thead class="table-light"><tr><th>Type</th><th>Part or service</th><th class="text-end">Qty</th><th class="text-end">Unit price</th><th class="text-end">Line total</th></tr></thead><tbody>
          <?php $itemizedTotal = 0; foreach ($chargeItems as $item): $lineTotal = (float)$item['quantity'] * (float)$item['unit_price']; $itemizedTotal += $lineTotal; ?>
          <tr><td><?= htmlspecialchars($item['item_type']) ?></td><td><?= htmlspecialchars($item['item_name']) ?></td><td class="text-end"><?= number_format((float)$item['quantity'], 2) ?></td><td class="text-end">PHP <?= number_format((float)$item['unit_price'], 2) ?></td><td class="text-end">PHP <?= number_format($lineTotal, 2) ?></td></tr>
          <?php endforeach; ?>
        </tbody><tfoot><tr><th colspan="4" class="text-end">Total payable</th><th class="text-end text-success">PHP <?= number_format($itemizedTotal, 2) ?></th></tr></tfoot></table></div>
      <?php else: ?>
        <div class="alert alert-info mb-0"><i class="bi bi-info-circle me-1"></i>The technician has not submitted an itemized service report yet. The final payment amount will appear here after assessment.</div>
      <?php endif; ?>
    </section>
  <?php endif; ?>
</main>
</body>
</html>
