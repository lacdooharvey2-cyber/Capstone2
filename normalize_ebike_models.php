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

$models = [
    ['KUDA', 'New Wolf', 'E-Bike'],
    ['KUDA', 'Wolf 3', 'E-Bike'],
    ['KUDA', 'Mini Tiger', 'E-Bike'],
    ['KUDA', 'Mika', 'E-Bike'],
    ['KUDA', 'Eagle', 'E-Bike'],
    ['KUDA', 'Express', 'E-Bike'],
    ['KUDA', 'Lion', 'E-Bike'],
    ['KUDA', 'Mini Lion', 'E-Bike'],
    ['KUDA', 'Rhino', 'E-Bike'],
    ['KUDA', 'Buffalo', 'E-Bike'],
    ['NWOW', 'GB2', 'Electric Bicycle'],
    ['NWOW', 'GC10', 'Electric Bicycle'],
    ['NWOW', 'ERV', 'Electric Vehicle'],
    ['NWOW', 'ERVS2', 'Electric Vehicle'],
    ['NWOW', 'ERVS3', 'Electric Vehicle'],
    ['NWOW', 'ERV2', 'Electric Vehicle'],
    ['NWOW', 'ERVS4', 'Electric Vehicle'],
    ['NWOW', 'V15', 'Electric Scooter'],
    ['NWOW', 'V11', 'Electric Scooter'],
    ['NWOW', 'WSP', 'Electric Scooter'],
    ['NWOW', 'TK10', 'Electric Scooter'],
    ['NWOW', 'ARS', 'Electric Scooter'],
    ['NWOW', 'EMC-GOLF', '4-Wheel EV'],
    ['NWOW', 'EMC-GOLF2', '4-Wheel EV'],
];

$warranties = $conn->query("\n    SELECT warranty_id, customer_id\n    FROM warranty_records\n    ORDER BY warranty_id\n");
if (!$warranties) {
    exit('Unable to read warranty records: ' . $conn->error);
}

$updateWarranty = $conn->prepare('UPDATE warranty_records SET ebike_model = ? WHERE warranty_id = ?');
$updateRepair = $conn->prepare('UPDATE repairs SET ebike_model = ? WHERE repair_id = ?');
if (!$updateWarranty || !$updateRepair) {
    exit('Unable to prepare model updates: ' . $conn->error);
}

$customerModels = [];
$warrantyUpdated = 0;
$modelIndex = 0;

$conn->begin_transaction();
try {
    while ($warranty = $warranties->fetch_assoc()) {
        $modelData = $models[$modelIndex % count($models)];
        $model = $modelData[0] . ' - ' . $modelData[1];
        $warrantyId = (int)$warranty['warranty_id'];
        $customerId = (int)$warranty['customer_id'];

        $updateWarranty->bind_param('si', $model, $warrantyId);
        if (!$updateWarranty->execute()) {
            throw new RuntimeException($updateWarranty->error);
        }

        $customerModels[$customerId] ??= $model;
        $warrantyUpdated++;
        $modelIndex++;
    }

    $repairs = $conn->query('SELECT repair_id, customer_id FROM repairs ORDER BY repair_id');
    if (!$repairs) {
        throw new RuntimeException($conn->error);
    }

    $repairsUpdated = 0;
    while ($repair = $repairs->fetch_assoc()) {
        $repairId = (int)$repair['repair_id'];
        $customerId = (int)$repair['customer_id'];
        $model = $customerModels[$customerId] ?? $models[$repairId % count($models)][0] . ' - ' . $models[$repairId % count($models)][1];

        $updateRepair->bind_param('si', $model, $repairId);
        if (!$updateRepair->execute()) {
            throw new RuntimeException($updateRepair->error);
        }
        $repairsUpdated += $updateRepair->affected_rows;
    }

    $conn->commit();
} catch (Throwable $exception) {
    $conn->rollback();
    exit('No model records were changed: ' . $exception->getMessage());
}

$summary = "Updated {$warrantyUpdated} warranty e-bike model(s) and {$repairsUpdated} repair model(s).";
if (PHP_SAPI === 'cli') {
    echo $summary . PHP_EOL;
} else {
    echo htmlspecialchars($summary, ENT_QUOTES, 'UTF-8');
}
?>
