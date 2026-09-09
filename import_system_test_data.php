<?php
include("db.php");

$csvPath = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'NWOW_KUDA_Ebike_Synthetic_Dataset_1000_Users.csv';

if (!is_readable($csvPath)) {
    die("CSV file not found: " . $csvPath . PHP_EOL);
}

function detectGenderFromName(string $name): string
{
    $first = strtolower(strtok($name, ' '));
    $femaleNames = [
        'maria', 'clarisse', 'beatrice', 'angela', 'michelle', 'jennifer',
        'christine', 'patricia', 'ana', 'marie', 'jessica', 'katrina',
        'camille', 'nicole', 'sarah', 'princess', 'grace'
    ];

    return in_array($first, $femaleNames, true) ? 'Female' : 'Male';
}

function firstIssue(array $data): string
{
    $issues = array_filter([
        trim($data['Issue 1'] ?? ''),
        trim($data['Issue 2'] ?? ''),
        trim($data['Issue 3'] ?? ''),
    ]);

    return $issues ? implode(', ', $issues) : 'General maintenance';
}

$conn->begin_transaction();

try {
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

    $syntheticIds = [];
    $handle = fopen($csvPath, 'r');
    $headers = fgetcsv($handle);

    while (($row = fgetcsv($handle)) !== false) {
        $data = array_combine($headers, $row);
        $syntheticIds[] = $data['User ID'];
    }
    fclose($handle);

    if (!$syntheticIds) {
        throw new RuntimeException("CSV file has no rows.");
    }

    $customerIdLookup = [];
    $existingSyntheticUsers = $conn->query("SELECT user_id, custom_id FROM users WHERE custom_id REGEXP '^(NWOW|KUDA)-[0-9]{4}$'");
    while ($row = $existingSyntheticUsers->fetch_assoc()) {
        $customerIdLookup[$row['custom_id']] = (int)$row['user_id'];
    }

    if ($customerIdLookup) {
        $ids = implode(',', array_map('intval', array_values($customerIdLookup)));
        $conn->query("DELETE FROM messages WHERE sender_id IN ($ids) OR receiver_id IN ($ids)");
        $conn->query("DELETE FROM repairs WHERE customer_id IN ($ids)");
        $conn->query("DELETE FROM repair_bookings WHERE customer_id IN ($ids)");
        $conn->query("DELETE FROM warranty_records WHERE customer_id IN ($ids)");
        $conn->query("DELETE FROM users WHERE user_id IN ($ids)");
    }

    $datasetStmt = $conn->prepare("
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
    $userStmt = $conn->prepare("
        INSERT INTO users
        (custom_id, name, email, password_hash, role, contact_number, account_status, gender, dob, age, religion, civil_status, occupation, province_address)
        VALUES (?, ?, ?, ?, 'Customer', ?, 'Active', ?, ?, ?, 'N/A', 'Single', 'E-bike Owner', ?)
    ");
    $repairStmt = $conn->prepare("
        INSERT INTO repairs (customer_id, ebike_model, issue_description, repair_status, amount)
        VALUES (?, ?, ?, ?, ?)
    ");
    $bookingStmt = $conn->prepare("
        INSERT INTO repair_bookings
        (customer_id, service_type, preferred_date, preferred_time, description, booking_status, tracking_number, warranty_status, payment_status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $warrantyStmt = $conn->prepare("
        INSERT INTO warranty_records
        (customer_id, ebike_model, purchase_date, warranty_period, warranty_status, claim_date)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $handle = fopen($csvPath, 'r');
    $headers = fgetcsv($handle);
    $passwordHash = password_hash('password123', PASSWORD_DEFAULT);
    $importedUsers = 0;
    $importedRepairs = 0;
    $importedBookings = 0;
    $importedWarranties = 0;

    while (($row = fgetcsv($handle)) !== false) {
        $data = array_combine($headers, $row);
        $customId = $data['User ID'];
        $brand = $data['Brand'];
        $model = $data['Model'];
        $name = $data['Name'];
        $age = (int)$data['Age'];
        $address = $data['Address'];
        $issueCount = (int)$data['Number of Issues'];
        $issue1 = $data['Issue 1'] !== '' ? $data['Issue 1'] : null;
        $issue2 = $data['Issue 2'] !== '' ? $data['Issue 2'] : null;
        $issue3 = $data['Issue 3'] !== '' ? $data['Issue 3'] : null;
        $issueDescription = firstIssue($data);
        $ebikeModel = $brand . ' ' . $model;
        $number = '09' . str_pad((string)(100000000 + $importedUsers), 9, '0', STR_PAD_LEFT);
        $email = strtolower(str_replace('-', '', $customId)) . '@fixtrack.test';
        $gender = detectGenderFromName($name);
        $birthYear = date('Y') - $age;
        $dob = $birthYear . '-' . str_pad((string)(($importedUsers % 12) + 1), 2, '0', STR_PAD_LEFT) . '-' . str_pad((string)(($importedUsers % 28) + 1), 2, '0', STR_PAD_LEFT);

        $datasetStmt->bind_param("ssssisisss", $customId, $brand, $model, $name, $age, $address, $issueCount, $issue1, $issue2, $issue3);
        $datasetStmt->execute();

        $userStmt->bind_param("sssssssis", $customId, $name, $email, $passwordHash, $number, $gender, $dob, $age, $address);
        $userStmt->execute();
        $customerId = $conn->insert_id;
        $importedUsers++;

        $repairStatuses = ['Pending', 'In Progress', 'Completed', 'Cancelled'];
        $repairStatus = $repairStatuses[$importedUsers % count($repairStatuses)];
        $amount = 350 + ($issueCount * 275) + (($importedUsers % 8) * 125);
        $repairStmt->bind_param("isssd", $customerId, $ebikeModel, $issueDescription, $repairStatus, $amount);
        $repairStmt->execute();
        $importedRepairs++;

        $bookingStatuses = ['Pending', 'Confirmed', 'In Progress', 'Completed', 'Rescheduled'];
        $bookingStatus = $bookingStatuses[$importedUsers % count($bookingStatuses)];
        $serviceType = $issueCount > 1 ? 'Repair' : 'Maintenance';
        $preferredDate = date('Y-m-d', strtotime('2026-09-01 +' . ($importedUsers % 90) . ' days'));
        $preferredTime = sprintf('%02d:00:00', 8 + ($importedUsers % 9));
        $tracking = 'RS-DATA-' . $customId;
        $warrantyCheck = $importedUsers % 5 === 0 ? 'Invalid' : 'Valid';
        $paymentStatus = $repairStatus === 'Completed' ? 'Paid' : 'Pending';
        $bookingStmt->bind_param("issssssss", $customerId, $serviceType, $preferredDate, $preferredTime, $issueDescription, $bookingStatus, $tracking, $warrantyCheck, $paymentStatus);
        $bookingStmt->execute();
        $importedBookings++;

        $warrantyStatuses = ['Active', 'Active', 'Active', 'Expired', 'Claimed', 'Rejected'];
        $warrantyStatus = $warrantyStatuses[$importedUsers % count($warrantyStatuses)];
        $purchaseDate = date('Y-m-d', strtotime('2024-01-01 +' . ($importedUsers % 700) . ' days'));
        $warrantyPeriod = $brand === 'NWOW' ? 12 : 18;
        $claimDate = $warrantyStatus === 'Claimed' ? date('Y-m-d H:i:s', strtotime($purchaseDate . ' +6 months')) : null;
        $warrantyStmt->bind_param("ississ", $customerId, $ebikeModel, $purchaseDate, $warrantyPeriod, $warrantyStatus, $claimDate);
        $warrantyStmt->execute();
        $importedWarranties++;
    }

    fclose($handle);
    $conn->commit();

    echo "System test data imported successfully." . PHP_EOL;
    echo "Customers: {$importedUsers}" . PHP_EOL;
    echo "Repair records: {$importedRepairs}" . PHP_EOL;
    echo "Repair bookings: {$importedBookings}" . PHP_EOL;
    echo "Warranty records: {$importedWarranties}" . PHP_EOL;
    echo "Default password for synthetic customer accounts: password123" . PHP_EOL;
} catch (Throwable $error) {
    $conn->rollback();
    die("Import failed: " . $error->getMessage() . PHP_EOL);
}
?>
