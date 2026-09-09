<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

if (PHP_SAPI !== 'cli') {
    session_start();
    if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'Admin') {
        http_response_code(403);
        exit('Admin access required.');
    }
}

$models = [
    'KUDA KDA Pro',
    'KUDA KDA Lite',
    'NWOW Urban',
    'NWOW City Rider',
];

$customers = $conn->query("
    SELECT u.user_id, u.name, COUNT(w.warranty_id) AS registered_ebikes
    FROM users u
    LEFT JOIN warranty_records w ON w.customer_id = u.user_id
    WHERE u.role = 'Customer'
    GROUP BY u.user_id, u.name
    ORDER BY u.user_id
");

if (!$customers) {
    exit('Unable to read customer records: ' . $conn->error);
}

$insert = $conn->prepare("
    INSERT INTO warranty_records
      (customer_id, ebike_model, purchase_date, warranty_period, warranty_status, claim_date)
    VALUES (?, ?, ?, 12, 'Active', NULL)
");

if (!$insert) {
    exit('Unable to prepare e-bike insert: ' . $conn->error);
}

$conn->begin_transaction();
$customersUpdated = 0;
$ebikesAdded = 0;

try {
    while ($customer = $customers->fetch_assoc()) {
        $existing = (int)$customer['registered_ebikes'];
        $target = ((int)$customer['user_id'] % 3) + 1;
        $numberToAdd = max(0, $target - $existing);
        if ($numberToAdd === 0) {
            continue;
        }

        // Stable 1-3 assignment makes the seed repeatable without adding duplicates.
        for ($index = 0; $index < $numberToAdd; $index++) {
            $customerId = (int)$customer['user_id'];
            $model = $models[($customerId + $index) % count($models)];
            $purchaseDate = date('Y-m-d', strtotime('-' . (12 + $index * 10) . ' months'));
            $insert->bind_param('iss', $customerId, $model, $purchaseDate);
            if (!$insert->execute()) {
                throw new RuntimeException($insert->error);
            }
            $ebikesAdded++;
        }
        $customersUpdated++;
    }

    $conn->commit();
} catch (Throwable $exception) {
    $conn->rollback();
    exit('No records were changed: ' . $exception->getMessage());
}

$verification = $conn->query("
    SELECT COUNT(*) AS customers,
           MIN(registered_ebikes) AS minimum_bikes,
           MAX(registered_ebikes) AS maximum_bikes
    FROM (
        SELECT u.user_id, COUNT(w.warranty_id) AS registered_ebikes
        FROM users u
        LEFT JOIN warranty_records w ON w.customer_id = u.user_id
        WHERE u.role = 'Customer'
        GROUP BY u.user_id
    ) AS customer_totals
");
$verified = $verification ? $verification->fetch_assoc() : [];
$summary = "Updated {$customersUpdated} customer accounts and added {$ebikesAdded} registered e-bike(s).";
if ($verified) {
    $summary .= " Verified {$verified['customers']} customer(s): {$verified['minimum_bikes']}-{$verified['maximum_bikes']} e-bike(s) each.";
}
if (PHP_SAPI === 'cli') {
    echo $summary . PHP_EOL;
} else {
    echo htmlspecialchars($summary, ENT_QUOTES, 'UTF-8');
}
?>
