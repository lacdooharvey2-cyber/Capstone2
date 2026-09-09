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

$result = $conn->query("
    SELECT w.warranty_id
    FROM warranty_records w
    INNER JOIN users u ON u.user_id = w.customer_id
    WHERE u.role = 'Customer'
      AND w.warranty_status <> 'Active'
");

if (!$result) {
    exit('Unable to read warranty records: ' . $conn->error);
}

$warrantyIds = [];
while ($row = $result->fetch_assoc()) {
    $warrantyIds[] = (int)$row['warranty_id'];
}

$updated = 0;
if ($warrantyIds) {
    $stmt = $conn->prepare("
        UPDATE warranty_records
        SET warranty_status = 'Active', claim_date = NULL
        WHERE warranty_id = ?
    ");

    if (!$stmt) {
        exit('Unable to prepare warranty update: ' . $conn->error);
    }

    $conn->begin_transaction();
    try {
        foreach ($warrantyIds as $warrantyId) {
            $stmt->bind_param('i', $warrantyId);
            if (!$stmt->execute()) {
                throw new RuntimeException($stmt->error);
            }
            $updated += $stmt->affected_rows;
        }
        $conn->commit();
    } catch (Throwable $exception) {
        $conn->rollback();
        exit('No warranty records were changed: ' . $exception->getMessage());
    }
}

$verification = $conn->query("
    SELECT COUNT(*) AS total,
           SUM(warranty_status = 'Active') AS active,
           SUM(warranty_status <> 'Active') AS non_active
    FROM warranty_records w
    INNER JOIN users u ON u.user_id = w.customer_id
    WHERE u.role = 'Customer'
");
$summary = $verification ? $verification->fetch_assoc() : [];
$message = "Updated {$updated} warranty record(s).";
if ($summary) {
    $message .= " Customer e-bike warranties: {$summary['active']} active, {$summary['non_active']} non-active.";
}

if (PHP_SAPI === 'cli') {
    echo $message . PHP_EOL;
} else {
    echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
}
?>
