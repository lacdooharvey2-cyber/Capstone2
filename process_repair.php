<?php
session_start();
include("db.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Customer') {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $customer_id = intval($_SESSION['user_id']);
    $service = trim($_POST['service_type'] ?? 'Repair');
    $preferred_date = $_POST['preferred_date'] ?? null;
    $time_slot = $_POST['time_slot'] ?? '';
    $model = trim(($_POST['brand'] ?? '') . ' ' . ($_POST['model'] ?? ''));
    $issue = trim($_POST['issue_description'] ?? '');
    $warranty = $_POST['warranty_request'] ?? 'No Warranty';
    $warranty_status = ($warranty === "Under Warranty") ? "Valid" : "Invalid";
    $tracking = 'RS-' . date('YmdHis') . '-' . $customer_id;
    $description = trim($model . "\n" . $issue . "\nPreferred time: " . $time_slot);

    $stmt = $conn->prepare("
        INSERT INTO repair_bookings
        (customer_id, service_type, preferred_date, description, booking_status, tracking_number, warranty_status, payment_status)
        VALUES (?, ?, ?, ?, 'Pending', ?, ?, 'Pending')
    ");
    $stmt->bind_param("isssss", $customer_id, $service, $preferred_date, $description, $tracking, $warranty_status);
    $stmt->execute();

    $repairStmt = $conn->prepare("
        INSERT INTO repairs (customer_id, ebike_model, issue_description, repair_status, amount)
        VALUES (?, ?, ?, 'Pending', 0.00)
    ");
    $repairStmt->bind_param("iss", $customer_id, $model, $issue);
    $repairStmt->execute();

    header("Location: customerdashboard.php?booking=success");
    exit();
}

header("Location: customerbookrepair.php");
exit();
?>
