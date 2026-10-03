<?php
declare(strict_types=1);

session_start();
require 'db.php';
require 'activity_log_helper.php';

$role = $_SESSION['role'] ?? '';
$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0 || !in_array($role, ['Admin', 'SuperAdmin', 'Technician', 'Cashier', 'Customer'], true)) {
    header('Location: login.php');
    exit();
}

ensureActivityLogSchema($conn);
$isAdmin = in_array($role, ['Admin', 'SuperAdmin'], true);
$logs = [];

if (activityLogHasColumn($conn, 'actor_id')) {
    $where = $isAdmin ? '' : ' WHERE actor_id = ' . $userId;
    $result = $conn->query("SELECT actor_role, action, description, entity_type, entity_id, created_at FROM activity_logs{$where} ORDER BY created_at DESC LIMIT 100");
    while ($result && ($row = $result->fetch_assoc())) {
        $logs[] = $row;
    }
} else {
    $where = $isAdmin ? '' : ' WHERE user_id = ' . $userId;
    $result = $conn->query("SELECT '' AS actor_role, activity AS action, description, '' AS entity_type, NULL AS entity_id, timestamp AS created_at FROM activity_logs{$where} ORDER BY timestamp DESC LIMIT 100");
    while ($result && ($row = $result->fetch_assoc())) {
        $logs[] = $row;
    }
}

$navbar = match ($role) {
    'Customer' => 'navbarcustomer.php',
    'Technician' => 'navbartechnician.php',
    'Cashier' => 'navbarcashier.php',
    default => 'navbaradmin.php',
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Audit Trail - FixTrack</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/css/app-theme.css" rel="stylesheet">
  <style>
    body { background: #f8f9fa; }
    .logs-wrap { max-width: 1180px; margin: 30px auto; padding: 0 16px 40px; }
    .logs-panel { background: #fff; border: 1px solid #dee2e6; border-radius: 12px; box-shadow: 0 .35rem 1rem rgba(31,41,55,.07); overflow: hidden; }
    .logs-panel thead th { background: #f3f5f3; color: #374151; white-space: nowrap; }
    .logs-panel td { vertical-align: middle; }
    .log-description { max-width: 560px; }
  </style>
</head>
<body>
  <?php include $navbar; ?>
  <main class="logs-wrap">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
      <div>
        <h1 class="h3 mb-1"><i class="bi bi-clock-history text-success me-2"></i>Audit Trail</h1>
        <p class="text-secondary mb-0"><?= $isAdmin ? 'System-wide activity history' : 'Your account activity history' ?></p>
      </div>
      <span class="badge text-bg-light border">Latest 100 records</span>
    </div>
    <section class="logs-panel">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead><tr><th>Date and Time</th><th>Role</th><th>Action</th><th>Description</th><th>Reference</th></tr></thead>
          <tbody>
            <?php if ($logs): foreach ($logs as $log): ?>
              <tr>
                <td><?= htmlspecialchars((string)$log['created_at']) ?></td>
                <td><?= htmlspecialchars(($log['actor_role'] ?? '') === 'AssistantAdmin' ? 'Assistant Admin' : (string)($log['actor_role'] ?: 'Activity')) ?></td>
                <td><span class="badge bg-danger-subtle text-danger-emphasis"><?= htmlspecialchars((string)$log['action']) ?></span></td>
                <td class="log-description"><?= htmlspecialchars((string)$log['description']) ?></td>
                <td><?= !empty($log['entity_type']) && !empty($log['entity_id']) ? htmlspecialchars($log['entity_type'] . ' #' . $log['entity_id']) : '—' ?></td>
              </tr>
            <?php endforeach; else: ?>
              <tr><td colspan="5" class="text-center text-muted py-4">No audit trail records yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>
  </main>
</body>
</html>
