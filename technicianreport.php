<?php
session_start();
require 'db.php';
require_once 'schema_helpers.php';
require_once 'activity_log_helper.php';
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['Technician', 'HeadTechnician'], true)) { header('Location: login.php'); exit(); }
ensureRepairAutomationSchema($conn);
$repairId = (int)($_GET['repair_id'] ?? $_POST['repair_id'] ?? 0);
$userId = (int)$_SESSION['user_id'];
$isHead = ($_SESSION['role'] ?? '') === 'HeadTechnician';
$stmt = $conn->prepare('SELECT r.repair_id, r.ebike_model, r.issue_description, r.technician_id, u.name customer_name FROM repairs r LEFT JOIN users u ON u.user_id=r.customer_id WHERE r.repair_id=?' . ($isHead ? '' : ' AND r.technician_id=?'));
if ($isHead) $stmt->bind_param('i', $repairId); else $stmt->bind_param('ii', $repairId, $userId);
$stmt->execute(); $repair = $stmt->get_result()->fetch_assoc();
if (!$repair) { http_response_code(404); exit('Repair record not found or not assigned to you.'); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $work = trim((string)($_POST['work_performed'] ?? '')); $notes = trim((string)($_POST['technician_notes'] ?? ''));
  $technicianId = $isHead && (int)$repair['technician_id'] > 0 ? (int)$repair['technician_id'] : $userId;
  $save = $conn->prepare('INSERT INTO technician_repair_reports (repair_id, technician_id, work_performed, technician_notes) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE technician_id=VALUES(technician_id), work_performed=VALUES(work_performed), technician_notes=VALUES(technician_notes)');
  $save->bind_param('iiss', $repairId, $technicianId, $work, $notes); $save->execute();
  logActivity($conn, $userId, (string)$_SESSION['role'], 'Technician report saved', 'Saved service work report for repair #' . $repairId . '.', 'repair', $repairId);
  header('Location: technicianreport.php?repair_id=' . $repairId . '&saved=1'); exit();
}
$report = $conn->query('SELECT * FROM technician_repair_reports WHERE repair_id=' . $repairId)->fetch_assoc();
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Service Report - FixTrack</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"><link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet"></head><body><?php include 'navbartechnician.php'; ?><main class="technician-workspace"><div class="page-hero"><h1 class="h3 mb-1">Technician Service Report</h1><p>Repair #<?= $repairId ?> for <?= htmlspecialchars($repair['customer_name'] ?? 'Customer') ?> - <?= htmlspecialchars($repair['ebike_model']) ?></p></div><?php if(isset($_GET['saved'])):?><div class="alert alert-success">Service report saved. The cashier prepares the payment summary.</div><?php endif;?><div class="card shadow-sm"><div class="card-body p-4"><p class="text-muted mb-4"><strong>Reported issue:</strong> <?= htmlspecialchars($repair['issue_description']) ?></p><form method="post"><input type="hidden" name="repair_id" value="<?= $repairId ?>"><div class="mb-4"><label class="form-label fw-semibold">Work performed</label><textarea name="work_performed" class="form-control" rows="5" placeholder="Describe diagnosis, repairs, and testing completed."><?= htmlspecialchars($report['work_performed'] ?? '') ?></textarea></div><div class="mb-4"><label class="form-label fw-semibold">Technician notes</label><textarea name="technician_notes" class="form-control" rows="3" placeholder="Customer-facing findings or recommendations."><?= htmlspecialchars($report['technician_notes'] ?? '') ?></textarea></div><button class="btn btn-success" type="submit"><i class="bi bi-floppy me-1"></i>Save service report</button><a class="btn btn-outline-secondary ms-2" href="technicianrepair.php">Back</a></form></div></div></main></body></html>
