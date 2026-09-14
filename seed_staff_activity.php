<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    session_start();
    if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['Admin', 'SuperAdmin'], true)) {
        http_response_code(403);
        exit('CLI only or administrator access required.');
    }
}

require 'db.php';

$technicianIds = [];
$technicianResult = $conn->query("SELECT user_id FROM users WHERE role = 'Technician' ORDER BY user_id");
while ($row = $technicianResult->fetch_assoc()) {
    $technicianIds[] = (int)$row['user_id'];
}

if (count($technicianIds) < 5) {
    exit("Five technician accounts are required." . PHP_EOL);
}

$repairs = $conn->query('SELECT repair_id, booking_id, repair_status FROM repairs ORDER BY repair_id');
if (!$repairs) {
    exit('Unable to read repair tickets: ' . $conn->error . PHP_EOL);
}

$assign = $conn->prepare('UPDATE repairs SET technician_id = ? WHERE repair_id = ?');
$updateCompleted = $conn->prepare('UPDATE repairs SET technician_id = ?, amount = ?, updated_at = ? WHERE repair_id = ?');
$updateBookingPaid = $conn->prepare("UPDATE repair_bookings SET estimated_amount = ?, payment_status = 'Paid', paid_at = ?, booking_status = 'Completed' WHERE booking_id = ?");

$conn->begin_transaction();
$assigned = 0;
$completedPriced = 0;
$index = 0;

try {
    while ($repair = $repairs->fetch_assoc()) {
        $repairId = (int)$repair['repair_id'];
        $technicianId = $technicianIds[$index % count($technicianIds)];
        $index++;

        if ($repair['repair_status'] === 'Completed') {
            $amount = 850.00 + (($repairId % 8) * 375.00);
            $daysAgo = $repairId % 165;
            $completedAt = date('Y-m-d H:i:s', time() - ($daysAgo * 86400));
            $updateCompleted->bind_param('idsi', $technicianId, $amount, $completedAt, $repairId);
            $updateCompleted->execute();
            $completedPriced++;

            $bookingId = (int)($repair['booking_id'] ?? 0);
            if ($bookingId > 0) {
                $updateBookingPaid->bind_param('dsi', $amount, $completedAt, $bookingId);
                $updateBookingPaid->execute();
            }
        } else {
            $assign->bind_param('ii', $technicianId, $repairId);
            $assign->execute();
        }
        $assigned++;
    }

    $conn->commit();
} catch (Throwable $exception) {
    $conn->rollback();
    exit('No staff activity was changed: ' . $exception->getMessage() . PHP_EOL);
}

echo "Assigned {$assigned} repair ticket(s) across " . count($technicianIds) . " technicians." . PHP_EOL;
echo "Priced {$completedPriced} completed repair(s) and marked linked bookings paid." . PHP_EOL;
