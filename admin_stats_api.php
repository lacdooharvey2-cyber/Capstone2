<?php
session_start();
include("db.php");
include_once("schema_helpers.php");

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['AssistantAdmin', 'Admin', 'AssistantSuperAdmin', 'SuperAdmin'], true)) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

ensureRepairAutomationSchema($conn);

function adminStatValue(mysqli $conn, string $sql, string $key = 'total')
{
    $result = $conn->query($sql);
    if (!$result) {
        return 0;
    }
    $row = $result->fetch_assoc();
    return $row[$key] ?? 0;
}

$pendingRepairs = (int)adminStatValue($conn, "SELECT COUNT(*) AS total FROM repairs WHERE repair_status='Pending'");
$inProgressRepairs = (int)adminStatValue($conn, "SELECT COUNT(*) AS total FROM repairs WHERE repair_status='In Progress'");

$stats = [
    'total_repairs' => (int)adminStatValue($conn, "SELECT COUNT(*) AS total FROM repairs"),
    'pending_repairs' => $pendingRepairs,
    'in_progress_repairs' => $inProgressRepairs,
    'completed_repairs' => (int)adminStatValue($conn, "SELECT COUNT(*) AS total FROM repairs WHERE repair_status='Completed'"),
    'open_queue' => $pendingRepairs + $inProgressRepairs,
    'active_customers' => (int)adminStatValue($conn, "
      SELECT COUNT(DISTINCT u.user_id) AS total
      FROM users u
      LEFT JOIN repairs r ON r.customer_id = u.user_id
      LEFT JOIN warranty_records w ON w.customer_id = u.user_id
      WHERE u.role='Customer' AND (r.repair_id IS NOT NULL OR w.warranty_id IS NOT NULL)
    "),
    'monthly_revenue' => (float)adminStatValue($conn, "
      SELECT COALESCE(SUM(amount),0) AS total
      FROM repairs
      WHERE repair_status='Completed' AND updated_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
    "),
    'avg_turnaround' => (float)adminStatValue($conn, "
      SELECT COALESCE(AVG(TIMESTAMPDIFF(DAY, created_at, updated_at)),0) AS total
      FROM repairs
      WHERE repair_status='Completed' AND updated_at IS NOT NULL
    "),
    'cancellations_this_month' => (int)adminStatValue($conn, "
      SELECT COUNT(*) AS total
      FROM repairs
      WHERE repair_status='Cancelled' AND updated_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
    "),
    'last_updated' => date('Y-m-d H:i:s'),
];

echo json_encode($stats);
