<?php
require 'db.php';
foreach (['warranty_records'] as $table) {
    echo "--{$table}--" . PHP_EOL;
    $result = $conn->query("DESCRIBE {$table}");
    if (!$result) { echo "missing" . PHP_EOL; continue; }
    while ($row = $result->fetch_assoc()) echo $row['Field'] . '|' . $row['Type'] . PHP_EOL;
}
echo "--warranties--" . PHP_EOL;
$result = $conn->query('SELECT warranty_id, customer_id, ebike_model, purchase_date, warranty_period, warranty_status, claim_date FROM warranty_records ORDER BY warranty_id DESC LIMIT 8');
while ($row = $result->fetch_assoc()) echo implode('|', $row) . PHP_EOL;
