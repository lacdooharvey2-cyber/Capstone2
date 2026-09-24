<?php
session_start();
include("db.php");

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Customer') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$year = (int)($_GET['year'] ?? date('Y'));
$month = (int)($_GET['month'] ?? date('n'));
if ($year < 2000 || $year > 2100 || $month < 1 || $month > 12) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid month']);
    exit();
}

$slotCapacity = 3;
$timeSlots = ['Morning', 'Afternoon', 'Evening'];
$start = sprintf('%04d-%02d-01', $year, $month);
$end = date('Y-m-t', strtotime($start));

$stmt = $conn->prepare("
    SELECT preferred_date, preferred_time, COUNT(*) AS total
    FROM repair_bookings
    WHERE preferred_date BETWEEN ? AND ?
      AND booking_status NOT IN ('Cancelled', 'Rejected')
    GROUP BY preferred_date, preferred_time
");
$stmt->bind_param("ss", $start, $end);
$stmt->execute();
$result = $stmt->get_result();

$slotCounts = [];
$fullSlots = [];
$fullDates = [];
while ($row = $result->fetch_assoc()) {
    $date = (string)$row['preferred_date'];
    $slot = (string)$row['preferred_time'];
    if (!in_array($slot, $timeSlots, true)) {
        continue;
    }

    $count = (int)$row['total'];
    $slotCounts[$date][$slot] = $count;
    if ($count >= $slotCapacity) {
        $fullSlots[$date][] = $slot;
    }
}

$cursor = strtotime($start);
$last = strtotime($end);
$today = strtotime(date('Y-m-d'));
while ($cursor !== false && $cursor <= $last) {
    $date = date('Y-m-d', $cursor);
    $allSlotsFull = true;
    foreach ($timeSlots as $slot) {
        if (($slotCounts[$date][$slot] ?? 0) < $slotCapacity) {
            $allSlotsFull = false;
            break;
        }
    }

    if ($cursor < $today || $allSlotsFull) {
        $fullDates[] = $date;
    }

    $cursor = strtotime('+1 day', $cursor);
}

echo json_encode([
    'slot_capacity' => $slotCapacity,
    'time_slots' => $timeSlots,
    'full_dates' => $fullDates,
    'full_slots' => $fullSlots,
    'slot_counts' => $slotCounts,
]);
