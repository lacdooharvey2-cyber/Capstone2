<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

if (PHP_SAPI !== 'cli') {
    session_start();
    if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['Admin', 'SuperAdmin'], true)) {
        http_response_code(403);
        exit('Admin access required.');
    }
}

$technicians = $conn->query("SELECT user_id FROM users WHERE role = 'Technician' ORDER BY user_id");
if (!$technicians || $technicians->num_rows === 0) {
    exit('No technician accounts are available.');
}

$technicianIds = [];
while ($technician = $technicians->fetch_assoc()) {
    $technicianIds[] = (int)$technician['user_id'];
}

$unassigned = $conn->query("SELECT repair_id FROM repairs WHERE technician_id IS NULL ORDER BY repair_id");
if (!$unassigned) {
    exit('Unable to read unassigned repairs: ' . $conn->error);
}

$update = $conn->prepare('UPDATE repairs SET technician_id = ? WHERE repair_id = ? AND technician_id IS NULL');
$conn->begin_transaction();
$assigned = 0;
$index = 0;

try {
    while ($repair = $unassigned->fetch_assoc()) {
        $technicianId = $technicianIds[$index % count($technicianIds)];
        $repairId = (int)$repair['repair_id'];
        $update->bind_param('ii', $technicianId, $repairId);
        if (!$update->execute()) {
            throw new RuntimeException($update->error);
        }
        $assigned += $update->affected_rows;
        $index++;
    }
    $conn->commit();
} catch (Throwable $exception) {
    $conn->rollback();
    exit('No assignments were changed: ' . $exception->getMessage());
}

echo "Assigned {$assigned} previously unassigned repair ticket(s)." . PHP_EOL;
?>
