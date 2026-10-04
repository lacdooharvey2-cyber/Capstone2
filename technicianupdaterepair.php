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
    $status = $_POST['status'] ?? null;
    $sessionUserId = intval($_SESSION['user_id']);
    $isHeadTechnician = ($_SESSION['role'] ?? '') === 'HeadTechnician';
    $technician_id = $isHeadTechnician ? intval($_POST['technician_id'] ?? 0) : $sessionUserId;
    $allowed = ['Pending', 'In Progress', 'Completed', 'Cancelled'];

    if ($repair_id <= 0 || (!$isHeadTechnician && !in_array((string)$status, $allowed, true))) {
        header("Location: technicianrepair.php?error=invalid_request");
        exit();
    }

    if ($isHeadTechnician) {
        if ($technician_id <= 0) {
            $stmt = $conn->prepare("UPDATE repairs SET technician_id = NULL WHERE repair_id = ?");
            $stmt->bind_param("i", $repair_id);
        } else {
            $stmt = $conn->prepare("UPDATE repairs SET technician_id = ? WHERE repair_id = ?");
            $stmt->bind_param("ii", $technician_id, $repair_id);
        }
    } else {
        $stmt = $conn->prepare("UPDATE repairs SET repair_status = ?, technician_id = ? WHERE repair_id = ?");
        $stmt->bind_param("sii", $status, $technician_id, $repair_id);
    }
    $stmt->execute();

    $activityDetail = $isHeadTechnician
        ? 'Updated technician assignment for repair #' . $repair_id . '.'
        : 'Updated repair #' . $repair_id . ' to ' . $status . '. Final payment is prepared by the cashier.';
    logActivity($conn, $sessionUserId, (string)$_SESSION['role'], 'Repair updated', $activityDetail, 'repair', $repair_id);

    header("Location: technicianrepair.php?updated=1");
    exit();
}

header("Location: technicianrepair.php");
exit();
?>
