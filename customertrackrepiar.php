<?php
include("db.php");
$tracking_number = $_GET['tracking_number'] ?? '';

$sql = "SELECT booking_status FROM repair_bookings WHERE tracking_number = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $tracking_number);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo "Status: " . $row['booking_status'];
} else {
    echo "Tracking number not found.";
}
?>
