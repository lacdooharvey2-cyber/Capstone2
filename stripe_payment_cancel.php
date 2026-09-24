<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/schema_helpers.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'Customer') {
    header('Location: login.php');
    exit();
}

$bookingId = (int)($_GET['booking_id'] ?? 0);
if ($bookingId > 0) {
    syncBookingPaymentToPayments($conn, $bookingId, 'PayPal', 'Cancelled');
}

header('Location: customerdashboard.php?payment=cancelled');
exit();
?>
