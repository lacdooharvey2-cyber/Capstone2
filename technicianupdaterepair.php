<?php
session_start();
include("db.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Technician') {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $repair_id = intval($_POST['repair_id'] ?? 0);
    $status = $_POST['status'] ?? 'Pending';
    $allowed = ['Pending', 'In Progress', 'Completed', 'Cancelled'];

    if (!in_array($status, $allowed, true) || $repair_id <= 0) {
        header("Location: technicianrepair.php?error=invalid_request");
        exit();
    }

    $stmt = $conn->prepare("UPDATE repairs SET repair_status = ?, technician_id = ? WHERE repair_id = ?");
    $stmt->bind_param("sii", $status, $_SESSION['user_id'], $repair_id);
    $stmt->execute();

    header("Location: technicianrepair.php?updated=1");
    exit();
}

header("Location: technicianrepair.php");
exit();
?>
