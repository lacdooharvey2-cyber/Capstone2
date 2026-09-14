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

$customers = [];
$customerResult = $conn->query("SELECT user_id FROM users WHERE role = 'Customer' ORDER BY user_id LIMIT 10");
while ($row = $customerResult->fetch_assoc()) {
    $customers[] = (int)$row['user_id'];
}

if (count($customers) < 10) {
    exit("At least 10 customer accounts are required." . PHP_EOL);
}

$records = [
    [$customers[0], 'KUDA - New Wolf', '2023-02-15', 12, 'Expired', null],
    [$customers[1], 'KUDA - Wolf 3', '2023-06-20', 12, 'Expired', null],
    [$customers[2], 'KUDA - Mini Tiger', '2024-01-10', 12, 'Expired', null],
    [$customers[3], 'KUDA - Eagle', '2023-09-05', 24, 'Expired', null],
    [$customers[4], 'KUDA - Lion', '2024-03-18', 12, 'Expired', null],
    [$customers[5], 'NWOW - GB2', '2024-02-12', 12, 'Claimed', '2024-10-08'],
    [$customers[6], 'NWOW - GC10', '2024-04-25', 24, 'Claimed', '2025-01-16'],
    [$customers[7], 'NWOW - ERV', '2023-11-30', 12, 'Claimed', '2024-06-21'],
    [$customers[8], 'NWOW - V15', '2024-06-14', 12, 'Claimed', '2025-02-03'],
    [$customers[9], 'NWOW - TK10', '2024-08-09', 12, 'Expired', null],
];

$check = $conn->prepare('SELECT warranty_id FROM warranty_records WHERE customer_id = ? AND ebike_model = ? AND purchase_date = ? LIMIT 1');
$insert = $conn->prepare(
    'INSERT INTO warranty_records (customer_id, ebike_model, purchase_date, warranty_period, warranty_status, claim_date)
     VALUES (?, ?, ?, ?, ?, ?)'
);

$created = 0;
$skipped = 0;
foreach ($records as [$customerId, $model, $purchaseDate, $period, $status, $claimDate]) {
    $check->bind_param('iss', $customerId, $model, $purchaseDate);
    $check->execute();
    if ($check->get_result()->fetch_assoc()) {
        $skipped++;
        continue;
    }

    $insert->bind_param('ississ', $customerId, $model, $purchaseDate, $period, $status, $claimDate);
    $insert->execute();
    $created++;
}

echo "Created {$created} warranty record(s); skipped {$skipped} existing record(s)." . PHP_EOL;
