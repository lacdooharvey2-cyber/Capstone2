<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/schema_helpers.php';

if (PHP_SAPI !== 'cli') {
    session_start();
    if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['Admin', 'SuperAdmin'], true)) {
        http_response_code(403);
        exit('Admin access required.');
    }
}

$updated = syncWarrantyStatuses($conn);
$repairCoverageUpdated = syncRepairWarrantyCoverage($conn);

$verification = $conn->query("
    SELECT COUNT(*) AS total,
           SUM(warranty_status = 'Active') AS active,
           SUM(warranty_status = 'Expired') AS expired,
           SUM(warranty_status IN ('Claimed', 'Rejected')) AS closed
    FROM warranty_records w
    INNER JOIN users u ON u.user_id = w.customer_id
    WHERE u.role = 'Customer'
");
$summary = $verification ? $verification->fetch_assoc() : [];
$message = "Synced {$updated} warranty record(s) using purchase date and warranty period.";
$message .= " Updated {$repairCoverageUpdated} linked repair warranty check(s).";
if ($summary) {
    $message .= " Customer e-bike warranties: {$summary['active']} active, {$summary['expired']} expired, {$summary['closed']} claimed/rejected.";
}

if (PHP_SAPI === 'cli') {
    echo $message . PHP_EOL;
} else {
    echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
}
?>
