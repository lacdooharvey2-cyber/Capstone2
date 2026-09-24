<?php
session_start();
include("db.php");
include_once("activity_log_helper.php");

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['Technician', 'HeadTechnician'], true)) {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $repair_id = intval($_POST['repair_id'] ?? 0);
    $status = $_POST['status'] ?? 'Pending';
    $amount = max(0, (float)($_POST['amount'] ?? 0));
    $sessionUserId = intval($_SESSION['user_id']);
    $isHeadTechnician = ($_SESSION['role'] ?? '') === 'HeadTechnician';
    $technician_id = $isHeadTechnician ? intval($_POST['technician_id'] ?? 0) : $sessionUserId;
    $allowed = ['Pending', 'In Progress', 'Completed', 'Cancelled'];

    if (!in_array($status, $allowed, true) || $repair_id <= 0) {
        header("Location: technicianrepair.php?error=invalid_request");
        exit();
    }

    if ($isHeadTechnician && $technician_id <= 0) {
        $stmt = $conn->prepare("UPDATE repairs SET repair_status = ?, amount = ?, technician_id = NULL WHERE repair_id = ?");
        $stmt->bind_param("sdi", $status, $amount, $repair_id);
    } else {
        $stmt = $conn->prepare("UPDATE repairs SET repair_status = ?, amount = ?, technician_id = ? WHERE repair_id = ?");
        $stmt->bind_param("sdii", $status, $amount, $technician_id, $repair_id);
    }
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
    logActivity($conn, $sessionUserId, (string)$_SESSION['role'], 'Repair updated', 'Updated repair #' . $repair_id . ' to ' . $status . ' with cost PHP ' . number_format($amount, 2) . '.', 'repair', $repair_id);

    header("Location: technicianrepair.php?updated=1");
    exit();
}

header("Location: technicianrepair.php");
exit();
?>
