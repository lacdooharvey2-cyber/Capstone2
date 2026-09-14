<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/schema_helpers.php';
require_once __DIR__ . '/netcorepay_config.php';
require_once __DIR__ . '/activity_log_helper.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'Customer') {
    header('Location: login.php');
    exit();
}

ensureRepairAutomationSchema($conn);
ensureNetcorepaySchema($conn);
$bookingId = (int)($_POST['booking_id'] ?? $_POST['repair_id'] ?? 0);
$customerId = (int)$_SESSION['user_id'];

if ($bookingId <= 0) {
    header('Location: customerdashboard.php?payment=invalid');
    exit();
}

$stmt = $conn->prepare("SELECT rb.booking_id, rb.payment_status,
        COALESCE(NULLIF(rb.estimated_amount, 0), r.amount, 0) AS payable_amount,
        r.repair_id, r.ebike_model
    FROM repair_bookings rb
    LEFT JOIN repairs r ON r.booking_id = rb.booking_id
    WHERE rb.booking_id = ? AND rb.customer_id = ? LIMIT 1");
$stmt->bind_param('ii', $bookingId, $customerId);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();
$amount = (float)($booking['payable_amount'] ?? 0);

if (!$booking || $amount <= 0 || ($booking['payment_status'] ?? '') === 'Paid') {
    header('Location: customerdashboard.php?payment=not_required');
    exit();
}

try {
    $payment = netcorepayRequest('POST', '/v1/payments', [
        'amount' => round($amount, 2),
        'currency' => 'PHP',
        'method' => 'gcash_qr',
        'description' => 'RedStar E-bike Repair #' . $bookingId,
        'return_url' => netcorepayReturnUrl() . '/payment_success.php?booking_id=' . $bookingId . '&customer_id=' . $customerId,
        'metadata' => [
            'booking_id' => (string)$bookingId,
            'customer_id' => (string)$customerId,
            'repair_id' => (string)($booking['repair_id'] ?? 0),
        ],
    ], [
        'Idempotency-Key: redstar-repair-' . $bookingId . '-' . bin2hex(random_bytes(8)),
    ]);

    $paymentId = (string)($payment['id'] ?? '');
    $checkoutUrl = (string)($payment['checkout_url'] ?? '');
    if ($paymentId === '' || !filter_var($checkoutUrl, FILTER_VALIDATE_URL)) {
        throw new RuntimeException('NetCorePay did not return a checkout URL.');
    }

    $update = $conn->prepare("UPDATE repair_bookings SET netcorepay_payment_id = ?, payment_status = 'Pending' WHERE booking_id = ? AND customer_id = ?");
    $update->bind_param('sii', $paymentId, $bookingId, $customerId);
    $update->execute();
    logActivity($conn, $customerId, 'Customer', 'NetCorePay payment started', 'Started GCash payment for booking #' . $bookingId . '.', 'booking', $bookingId);

    header('Location: ' . $checkoutUrl);
    exit();
} catch (Throwable $exception) {
    error_log('NetCorePay payment error: ' . $exception->getMessage());
    header('Location: customerdashboard.php?payment=error');
    exit();
}
