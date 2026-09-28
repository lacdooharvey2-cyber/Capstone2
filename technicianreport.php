<?php
session_start();
require 'db.php';
require_once 'schema_helpers.php';
require_once 'activity_log_helper.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['Technician', 'HeadTechnician'], true)) {
    header('Location: login.php'); exit();
}
ensureRepairAutomationSchema($conn);
$repairId = (int)($_GET['repair_id'] ?? $_POST['repair_id'] ?? 0);
$userId = (int)$_SESSION['user_id'];
$isHead = ($_SESSION['role'] ?? '') === 'HeadTechnician';
if ($repairId <= 0) { header('Location: technicianrepair.php'); exit(); }

$repairStmt = $conn->prepare('SELECT r.repair_id, r.customer_id, r.ebike_model, r.issue_description, r.technician_id, u.name AS customer_name FROM repairs r LEFT JOIN users u ON u.user_id=r.customer_id WHERE r.repair_id=?' . ($isHead ? '' : ' AND r.technician_id=?'));
if ($isHead) { $repairStmt->bind_param('i', $repairId); } else { $repairStmt->bind_param('ii', $repairId, $userId); }
$repairStmt->execute();
$repair = $repairStmt->get_result()->fetch_assoc();
if (!$repair) { http_response_code(404); exit('Repair record not found or not assigned to you.'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $workPerformed = trim((string)($_POST['work_performed'] ?? ''));
    $notes = trim((string)($_POST['technician_notes'] ?? ''));
    $reportTechId = $isHead && (int)$repair['technician_id'] > 0 ? (int)$repair['technician_id'] : $userId;
    $reportStmt = $conn->prepare('INSERT INTO technician_repair_reports (repair_id, technician_id, work_performed, technician_notes) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE technician_id=VALUES(technician_id), work_performed=VALUES(work_performed), technician_notes=VALUES(technician_notes)');
    $reportStmt->bind_param('iiss', $repairId, $reportTechId, $workPerformed, $notes);
    $reportStmt->execute();
    $reportId = (int)$conn->query('SELECT report_id FROM technician_repair_reports WHERE repair_id=' . $repairId)->fetch_assoc()['report_id'];
    $conn->query('DELETE FROM repair_charge_items WHERE report_id=' . $reportId);
    $types = $_POST['item_type'] ?? []; $names = $_POST['item_name'] ?? []; $quantities = $_POST['quantity'] ?? []; $prices = $_POST['unit_price'] ?? [];
    $itemStmt = $conn->prepare('INSERT INTO repair_charge_items (report_id, item_type, item_name, quantity, unit_price) VALUES (?, ?, ?, ?, ?)');
    $total = 0.0;
    foreach ($names as $index => $name) {
        $name = trim((string)$name); if ($name === '') continue;
        $type = in_array($types[$index] ?? '', ['Labor', 'Part', 'Other'], true) ? $types[$index] : 'Part';
        $quantity = max(0.01, (float)($quantities[$index] ?? 1)); $price = max(0, (float)($prices[$index] ?? 0));
        $itemStmt->bind_param('issdd', $reportId, $type, $name, $quantity, $price); $itemStmt->execute(); $total += $quantity * $price;
    }
    $totalStmt = $conn->prepare('UPDATE repairs SET amount=? WHERE repair_id=?'); $totalStmt->bind_param('di', $total, $repairId); $totalStmt->execute();
    $bookingStmt = $conn->prepare('UPDATE repair_bookings rb INNER JOIN repairs r ON r.booking_id=rb.booking_id SET rb.estimated_amount=? WHERE r.repair_id=?'); $bookingStmt->bind_param('di', $total, $repairId); $bookingStmt->execute();
    logActivity($conn, $userId, (string)$_SESSION['role'], 'Technician report saved', 'Saved itemized service report for repair #' . $repairId . '.', 'repair', $repairId);
    header('Location: technicianreport.php?repair_id=' . $repairId . '&saved=1'); exit();
}
$report = $conn->query('SELECT * FROM technician_repair_reports WHERE repair_id=' . $repairId)->fetch_assoc();
$items = [];
if ($report) { $result = $conn->query('SELECT * FROM repair_charge_items WHERE report_id=' . (int)$report['report_id'] . ' ORDER BY item_id'); while ($row = $result->fetch_assoc()) $items[] = $row; }
if (!$items) $items[] = ['item_type' => 'Labor', 'item_name' => '', 'quantity' => '1', 'unit_price' => '0'];
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Service Report - FixTrack</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"><link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet"><style>.report-wrap{max-width:none;margin:28px 32px 48px}.charge-total{font-size:1.15rem;font-weight:800}@media(max-width:991px){.report-wrap{margin:24px 16px}}</style></head><body><?php include 'navbartechnician.php'; ?><main class="report-wrap"><div class="d-flex justify-content-between align-items-start gap-3 mb-4"><div><h1 class="h3 mb-1">Technician Service Report</h1><p class="text-muted mb-0">#<?= $repairId ?> · <?= htmlspecialchars($repair['customer_name'] ?? 'Customer') ?> · <?= htmlspecialchars($repair['ebike_model']) ?></p></div><a class="btn btn-outline-secondary" href="technicianrepair.php"><i class="bi bi-arrow-left me-1"></i>Back</a></div><?php if(isset($_GET['saved'])):?><div class="alert alert-success">Service report and itemized charges saved.</div><?php endif;?><form method="post" class="card shadow-sm"><div class="card-body p-4"><input type="hidden" name="repair_id" value="<?= $repairId ?>"><div class="mb-4"><label class="form-label fw-semibold">Work performed</label><textarea name="work_performed" class="form-control" rows="3" placeholder="Describe diagnosis, repairs, and testing completed."><?= htmlspecialchars($report['work_performed'] ?? '') ?></textarea></div><div class="mb-4"><label class="form-label fw-semibold">Technician notes</label><textarea name="technician_notes" class="form-control" rows="2" placeholder="Customer-facing notes or recommendations."><?= htmlspecialchars($report['technician_notes'] ?? '') ?></textarea></div><div class="d-flex justify-content-between align-items-center mb-2"><h2 class="h5 mb-0">Payment Breakdown</h2><button class="btn btn-outline-success btn-sm" type="button" id="addItem"><i class="bi bi-plus-lg me-1"></i>Add item</button></div><div class="table-responsive"><table class="table align-middle" id="itemsTable"><thead><tr><th>Type</th><th>Part or service</th><th>Qty</th><th>Unit price</th><th>Total</th><th></th></tr></thead><tbody><?php foreach($items as $item): ?><tr><td><select class="form-select" name="item_type[]"><option <?= $item['item_type']==='Labor'?'selected':'' ?>>Labor</option><option <?= $item['item_type']==='Part'?'selected':'' ?>>Part</option><option <?= $item['item_type']==='Other'?'selected':'' ?>>Other</option></select></td><td><input class="form-control" name="item_name[]" value="<?= htmlspecialchars($item['item_name']) ?>" required></td><td><input class="form-control line-qty" type="number" min="0.01" step="0.01" name="quantity[]" value="<?= htmlspecialchars((string)$item['quantity']) ?>"></td><td><input class="form-control line-price" type="number" min="0" step="0.01" name="unit_price[]" value="<?= htmlspecialchars((string)$item['unit_price']) ?>"></td><td class="line-total">PHP 0.00</td><td><button type="button" class="btn btn-outline-danger btn-sm remove-item" aria-label="Remove item"><i class="bi bi-trash"></i></button></td></tr><?php endforeach;?></tbody></table></div><div class="d-flex justify-content-end border-top pt-3"><span class="charge-total">Total payable: <span id="grandTotal">PHP 0.00</span></span></div><button class="btn btn-success mt-4" type="submit"><i class="bi bi-floppy me-1"></i>Save service report</button></div></form></main><template id="itemTemplate"><tr><td><select class="form-select" name="item_type[]"><option>Labor</option><option selected>Part</option><option>Other</option></select></td><td><input class="form-control" name="item_name[]" required></td><td><input class="form-control line-qty" type="number" min="0.01" step="0.01" name="quantity[]" value="1"></td><td><input class="form-control line-price" type="number" min="0" step="0.01" name="unit_price[]" value="0"></td><td class="line-total">PHP 0.00</td><td><button type="button" class="btn btn-outline-danger btn-sm remove-item" aria-label="Remove item"><i class="bi bi-trash"></i></button></td></tr></template><script>const body=document.querySelector('#itemsTable tbody');function totals(){let total=0;body.querySelectorAll('tr').forEach(r=>{const x=(+r.querySelector('.line-qty').value||0)*(+r.querySelector('.line-price').value||0);r.querySelector('.line-total').textContent='PHP '+x.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2});total+=x});document.querySelector('#grandTotal').textContent='PHP '+total.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})}document.querySelector('#addItem').onclick=()=>{body.insertAdjacentHTML('beforeend',document.querySelector('#itemTemplate').innerHTML);totals()};body.addEventListener('input',totals);body.addEventListener('click',e=>{if(e.target.closest('.remove-item')&&body.rows.length>1){e.target.closest('tr').remove();totals()}});totals();</script></body></html>
