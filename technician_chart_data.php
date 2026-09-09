<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'Technician') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$technicianId = (int)$_SESSION['user_id'];
$weekStart = date('Y-m-d', strtotime('monday this week'));
$weekEnd = date('Y-m-d', strtotime($weekStart . ' +7 days'));

$stmt = $conn->prepare("
    SELECT WEEKDAY(created_at) AS weekday_index, COUNT(*) AS total
    FROM repairs
    WHERE technician_id = ?
      AND created_at >= ?
      AND created_at < ?
    GROUP BY WEEKDAY(created_at)
");
$stmt->bind_param('iss', $technicianId, $weekStart, $weekEnd);
$stmt->execute();

$weekCounts = array_fill(0, 7, 0);
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $weekCounts[(int)$row['weekday_index']] = (int)$row['total'];
}

echo json_encode([
    'weekCounts' => $weekCounts,
    'updatedAt' => date('c'),
], JSON_NUMERIC_CHECK);
?>
