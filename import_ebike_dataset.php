<?php
include("db.php");

$csvPath = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'NWOW_KUDA_Ebike_Synthetic_Dataset_1000_Users.csv';

if (!is_readable($csvPath)) {
    die("CSV file not found: " . htmlspecialchars($csvPath));
}

function cleanDatasetText(?string $value, bool $titleCase = false): string
{
    $value = trim((string)$value);
    $value = str_replace(
        ['Ã±', 'Ã‘', 'â€“', 'â€”', 'â€™', 'â€œ', 'â€�', "\xC2\xA0"],
        ['ñ', 'Ñ', '-', '-', "'", '"', '"', ' '],
        $value
    );
    $value = preg_replace('/\s+/', ' ', $value) ?? $value;

    if ($titleCase && $value !== '') {
        if (function_exists('mb_convert_case') && function_exists('mb_strtolower')) {
            $value = mb_convert_case(mb_strtolower($value, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
        } else {
            $value = ucwords(strtolower($value));
        }
    }

    return $value;
}

function cleanDatasetBrand(?string $value): string
{
    $brand = strtoupper(cleanDatasetText($value));
    if (strpos($brand, 'KUDA') !== false || $brand === 'KDA') {
        return 'KUDA';
    }
    if (strpos($brand, 'NWOW') !== false) {
        return 'NWOW';
    }
    return $brand !== '' ? $brand : 'Unknown';
}

function cleanDatasetIssue(?string $value): ?string
{
    $issue = cleanDatasetText($value, true);
    return $issue !== '' ? $issue : null;
}

function cleanDatasetAge(?string $value): int
{
    $age = (int)preg_replace('/[^\d]/', '', (string)$value);
    return max(0, min($age, 120));
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
    if (count($row) !== count($headers)) {
        continue;
    }

    $data = array_combine($headers, $row);
    if ($data === false) {
        continue;
    }

    $datasetUserId = strtoupper(cleanDatasetText($data['User ID'] ?? ''));
    if ($datasetUserId === '') {
        continue;
    }

    $brand = cleanDatasetBrand($data['Brand'] ?? '');
    $model = strtoupper(cleanDatasetText($data['Model'] ?? ''));
    $name = cleanDatasetText($data['Name'] ?? '', true);
    $age = cleanDatasetAge($data['Age'] ?? '');
    $address = cleanDatasetText($data['Address'] ?? '', true);
    $issue1 = cleanDatasetIssue($data['Issue 1'] ?? '');
    $issue2 = cleanDatasetIssue($data['Issue 2'] ?? '');
    $issue3 = cleanDatasetIssue($data['Issue 3'] ?? '');
    $numberOfIssues = count(array_filter([$issue1, $issue2, $issue3]));

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
