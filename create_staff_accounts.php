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
require_once 'schema_helpers.php';

// Keep the role enum aligned with the application roles before inserting accounts.
ensureRepairAutomationSchema($conn);

$accounts = [
    ['ASUPER-01', 'Assistant SuperAdmin', 'assistant.superadmin@fixtrack.local', 'AssistantSuperAdmin'],
    ['AADMIN-01', 'Assistant Admin', 'assistant.admin@fixtrack.local', 'AssistantAdmin'],
    ['HTECH-001', 'Head Technician', 'head.tech@fixtrack.local', 'HeadTechnician'],
    ['TECH-001', 'Technician 1', 'tech1@fixtrack.local', 'Technician'],
    ['TECH-002', 'Technician 2', 'tech2@fixtrack.local', 'Technician'],
    ['TECH-003', 'Technician 3', 'tech3@fixtrack.local', 'Technician'],
    ['TECH-004', 'Technician 4', 'tech4@fixtrack.local', 'Technician'],
    ['TECH-005', 'Technician 5', 'tech5@fixtrack.local', 'Technician'],
    ['CASH-001', 'Cashier 1', 'cashier1@fixtrack.local', 'Cashier'],
    ['CASH-002', 'Cashier 2', 'cashier2@fixtrack.local', 'Cashier'],
    ['CASH-003', 'Cashier 3', 'cashier3@fixtrack.local', 'Cashier'],
    ['ADMIN-01', 'System Admin', 'admin@fixtrack.local', 'Admin'],
    ['SUPER-01', 'System SuperAdmin', 'superadmin@fixtrack.local', 'SuperAdmin'],
];

$check = $conn->prepare('SELECT user_id FROM users WHERE custom_id = ? LIMIT 1');
$insert = $conn->prepare(
    'INSERT INTO users
        (name, email, password_hash, role, contact_number, account_status, custom_id, gender, dob, age, religion, civil_status, occupation, province_address)
     VALUES (?, ?, ?, ?, ?, "Active", ?, "Male", "1995-01-01", 31, "Roman Catholic", "Single", ?, "Santa Rosa, Laguna")'
);

foreach ($accounts as [$customId, $name, $email, $role]) {
    $check->bind_param('s', $customId);
    $check->execute();
    if ($check->get_result()->fetch_assoc()) {
        echo "EXISTS|{$customId}|{$role}|{$email}" . PHP_EOL;
        continue;
    }

    $password = 'FixTrack!' . strtoupper(bin2hex(random_bytes(4)));
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $contact = '09' . random_int(100000000, 999999999);
    $insert->bind_param('sssssss', $name, $email, $hash, $role, $contact, $customId, $role);
    $insert->execute();

    echo "CREATED|{$customId}|{$role}|{$email}|{$password}" . PHP_EOL;
}
