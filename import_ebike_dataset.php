<?php
include("db.php");

$csvPath = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'NWOW_KUDA_Ebike_Synthetic_Dataset_1000_Users.csv';

if (!is_readable($csvPath)) {
    die("CSV file not found: " . htmlspecialchars($csvPath));
}

$conn->query("
CREATE TABLE IF NOT EXISTS ebike_synthetic_dataset (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  dataset_user_id VARCHAR(20) NOT NULL UNIQUE,
  brand VARCHAR(20) NOT NULL,
  model VARCHAR(100) NOT NULL,
  name VARCHAR(100) NOT NULL,
  age INT NOT NULL,
  address VARCHAR(255) NOT NULL,
  number_of_issues INT NOT NULL DEFAULT 0,
  issue_1 VARCHAR(150) NULL,
  issue_2 VARCHAR(150) NULL,
  issue_3 VARCHAR(150) NULL,
  imported_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
)
");

$stmt = $conn->prepare("
INSERT INTO ebike_synthetic_dataset
(dataset_user_id, brand, model, name, age, address, number_of_issues, issue_1, issue_2, issue_3)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
ON DUPLICATE KEY UPDATE
  brand=VALUES(brand),
  model=VALUES(model),
  name=VALUES(name),
  age=VALUES(age),
  address=VALUES(address),
  number_of_issues=VALUES(number_of_issues),
  issue_1=VALUES(issue_1),
  issue_2=VALUES(issue_2),
  issue_3=VALUES(issue_3)
");

$handle = fopen($csvPath, 'r');
$headers = fgetcsv($handle);
$imported = 0;

while (($row = fgetcsv($handle)) !== false) {
    $data = array_combine($headers, $row);
    $datasetUserId = $data['User ID'];
    $brand = $data['Brand'];
    $model = $data['Model'];
    $name = $data['Name'];
    $age = (int)$data['Age'];
    $address = $data['Address'];
    $numberOfIssues = (int)$data['Number of Issues'];
    $issue1 = $data['Issue 1'] !== '' ? $data['Issue 1'] : null;
    $issue2 = $data['Issue 2'] !== '' ? $data['Issue 2'] : null;
    $issue3 = $data['Issue 3'] !== '' ? $data['Issue 3'] : null;

    $stmt->bind_param(
        "ssssisisss",
        $datasetUserId,
        $brand,
        $model,
        $name,
        $age,
        $address,
        $numberOfIssues,
        $issue1,
        $issue2,
        $issue3
    );
    $stmt->execute();
    $imported++;
}

fclose($handle);

$counts = $conn->query("SELECT brand, COUNT(*) AS total FROM ebike_synthetic_dataset GROUP BY brand ORDER BY brand");

echo "Imported/updated {$imported} dataset rows.\n";
while ($row = $counts->fetch_assoc()) {
    echo $row['brand'] . ": " . $row['total'] . "\n";
}
?>
