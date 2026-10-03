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

ensureRepairAutomationSchema($conn);

$target = ['active' => 500, 'expiring' => 400, 'expired' => 100];
$nwowImage = 'https://imgcdn.zigwheels.ph/large/gallery/color/154/3048/nwow-gb2-color-511905.jpg';
$kudaImage = 'https://www.tadiddc.com/images/ddc/kuda.jpg';
$records = $conn->query("SELECT warranty_id, ebike_model FROM warranty_records ORDER BY warranty_id LIMIT 1000");
if (!$records || $records->num_rows < array_sum($target)) {
    exit('At least 1,000 warranty records are required before applying this distribution.');
}

$update = $conn->prepare('UPDATE warranty_records SET purchase_date=?, warranty_period=?, warranty_status=?, claim_date=NULL, ebike_image_url=? WHERE warranty_id=?');
$conn->begin_transaction();
try {
    $index = 0;
    $retainedIds = [];
    while ($record = $records->fetch_assoc()) {
        if ($index < $target['active']) {
            $purchaseDate = date('Y-m-d', strtotime('-10 months -' . ($index % 20) . ' days'));
            $period = 12;
            $status = 'Active';
        } elseif ($index < $target['active'] + $target['expiring']) {
            $daysLeft = ($index % 30) + 1;
            $purchaseDate = date('Y-m-d', strtotime('-12 months +' . $daysLeft . ' days'));
            $period = 12;
            $status = 'Active';
        } else {
            $purchaseDate = date('Y-m-d', strtotime('-13 months -' . ($index % 180) . ' days'));
            $period = 12;
            $status = 'Expired';
        }
        $image = stripos((string)$record['ebike_model'], 'NWOW') !== false ? $nwowImage : $kudaImage;
        $id = (int)$record['warranty_id'];
        $retainedIds[] = $id;
        $update->bind_param('sissi', $purchaseDate, $period, $status, $image, $id);
        $update->execute();
        $index++;
    }

    // This seed intentionally resets the complete warranty dataset to the requested 1,000 records.
    $idList = implode(',', $retainedIds);
    $conn->query("UPDATE repair_bookings SET warranty_id = NULL WHERE warranty_id IS NOT NULL AND warranty_id NOT IN ({$idList})");
    $conn->query("DELETE FROM warranty_records WHERE warranty_id NOT IN ({$idList})");
    syncRepairWarrantyCoverage($conn);
    $conn->commit();
} catch (Throwable $error) {
    $conn->rollback();
    exit('No warranty data changed: ' . $error->getMessage());
}

$verification = $conn->query("SELECT
  COUNT(*) AS total,
  SUM(CASE WHEN warranty_status='Active' AND DATE_ADD(purchase_date, INTERVAL warranty_period MONTH) > DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS active,
  SUM(CASE WHEN warranty_status='Active' AND DATE_ADD(purchase_date, INTERVAL warranty_period MONTH) BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS expiring,
  SUM(CASE WHEN warranty_status='Expired' THEN 1 ELSE 0 END) AS expired
  FROM warranty_records")->fetch_assoc();
$message = "Warranty dataset synchronized: {$verification['total']} total, {$verification['active']} active, {$verification['expiring']} expiring soon, and {$verification['expired']} expired. Model images and linked booking coverage were updated.";
echo PHP_SAPI === 'cli' ? $message . PHP_EOL : htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
