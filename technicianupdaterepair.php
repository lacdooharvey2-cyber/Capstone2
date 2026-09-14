<?php
session_start();
include("db.php");
include_once("activity_log_helper.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Technician') {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $repair_id = intval($_POST['repair_id'] ?? 0);
    $status = $_POST['status'] ?? 'Pending';
    $amount = max(0, (float)($_POST['amount'] ?? 0));
    $technician_id = intval($_SESSION['user_id']);
    $allowed = ['Pending', 'In Progress', 'Completed', 'Cancelled'];

    if (!in_array($status, $allowed, true) || $repair_id <= 0) {
        header("Location: technicianrepair.php?error=invalid_request");
        exit();
    }

    $stmt = $conn->prepare("UPDATE repairs SET repair_status = ?, amount = ?, technician_id = ? WHERE repair_id = ?");
    $stmt->bind_param("sdii", $status, $amount, $technician_id, $repair_id);
    $stmt->execute();

    $bookingStmt = $conn->prepare("
        UPDATE repair_bookings rb
        INNER JOIN repairs r ON r.booking_id = rb.booking_id
        SET rb.estimated_amount = ?,
            rb.payment_status = IF(? > 0, rb.payment_status, 'Paid')
        WHERE r.repair_id = ?
    ");
    $bookingStmt->bind_param("ddi", $amount, $amount, $repair_id);
    $bookingStmt->execute();
    logActivity($conn, $technician_id, 'Technician', 'Repair updated', 'Updated repair #' . $repair_id . ' to ' . $status . ' with cost PHP ' . number_format($amount, 2) . '.', 'repair', $repair_id);

    header("Location: technicianrepair.php?updated=1");
    exit();
}

header("Location: technicianrepair.php");
exit();
?>
