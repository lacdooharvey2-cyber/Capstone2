<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/schema_helpers.php';
require_once __DIR__ . '/xendit_config.php';
require_once __DIR__ . '/activity_log_helper.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'Customer') {
    header('Location: login.php');
    exit();
}

ensureRepairAutomationSchema($conn);
ensureXenditSchema($conn);

$bookingId = (int)($_POST['booking_id'] ?? $_POST['repair_id'] ?? 0);
$customerId = (int)$_SESSION['user_id'];

if ($bookingId <= 0) {
    header('Location: customerdashboard.php?payment=invalid');
    exit();
}

$stmt = $conn->prepare("SELECT rb.booking_id, rb.payment_status,
        COALESCE(NULLIF(rb.estimated_amount, 0), r.amount, 0) AS payable_amount,
        r.repair_id, r.ebike_model, u.name AS customer_name, u.email AS customer_email
    FROM repair_bookings rb
    LEFT JOIN repairs r ON r.booking_id = rb.booking_id
    LEFT JOIN users u ON u.user_id = rb.customer_id
    WHERE rb.booking_id = ? AND rb.customer_id = ?
    LIMIT 1");
$stmt->bind_param('ii', $bookingId, $customerId);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

$amount = (float)($booking['payable_amount'] ?? 0);
if (!$booking || $amount <= 0 || ($booking['payment_status'] ?? '') === 'Paid') {
    header('Location: customerdashboard.php?payment=not_required');
    exit();
}

try {
    $baseUrl = xenditBaseUrl();
    $referenceId = 'REPAIR-' . $bookingId . '-' . bin2hex(random_bytes(4));
    $payload = [
        'reference_id' => $referenceId,
        'type' => 'PAY',
        'country' => 'PH',
        'currency' => 'PHP',
        'request_amount' => round($amount, 2),
        'capture_method' => 'AUTOMATIC',
        'channel_code' => 'GCASH',
        'channel_properties' => [
            'success_return_url' => $baseUrl . '/payment_success.php?booking_id=' . $bookingId . '&customer_id=' . $customerId,
            'failure_return_url' => $baseUrl . '/payment_failed.php?booking_id=' . $bookingId,
        ],
        'description' => 'RedStar E-bike Repair Payment',
        'metadata' => [
            'booking_id' => (string)$bookingId,
            'customer_id' => (string)$customerId,
            'repair_id' => (string)($booking['repair_id'] ?? 0),
        ],
    ];

    $result = xenditRequest('POST', '/v3/payment_requests', $payload, [
        'api-version: 2024-11-11',
    ]);
    $paymentRequestId = (string)($result['payment_request_id'] ?? '');
    $redirectUrl = '';
    foreach (($result['actions'] ?? []) as $action) {
        if (($action['type'] ?? '') === 'REDIRECT_CUSTOMER') {
            $redirectUrl = (string)($action['value'] ?? '');
            break;
        }
    }

    if ($paymentRequestId === '' || !filter_var($redirectUrl, FILTER_VALIDATE_URL)) {
        throw new RuntimeException('Xendit did not return a GCash redirect URL.');
    }

    $update = $conn->prepare("UPDATE repair_bookings
        SET xendit_payment_request_id = ?, payment_status = 'Pending'
        WHERE booking_id = ? AND customer_id = ?");
    $update->bind_param('sii', $paymentRequestId, $bookingId, $customerId);
    $update->execute();
    syncBookingPaymentToPayments($conn, $bookingId, 'GCash', 'Pending');
    logActivity($conn, $customerId, 'Customer', 'Xendit payment started', 'Started GCash payment for booking #' . $bookingId . '.', 'booking', $bookingId);

    header('Location: ' . $redirectUrl);
    exit();
} catch (Throwable $exception) {
    error_log('Xendit Payment Request error: ' . $exception->getMessage());
    header('Location: customerdashboard.php?payment=error');
    exit();
}
