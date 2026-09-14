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

$bookingId = (int)($_POST['booking_id'] ?? $_GET['booking_id'] ?? 0);
$customerId = (int)$_SESSION['user_id'];

if ($bookingId <= 0) {
    header('Location: customerdashboard.php?payment=invalid');
    exit();
}

$stmt = $conn->prepare("SELECT rb.booking_id, rb.estimated_amount, rb.payment_status,
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
    $referenceId = 'fixtrack-' . $bookingId . '-' . bin2hex(random_bytes(5));
    $payload = [
        'reference_id' => $referenceId,
        'session_type' => 'PAY',
        'mode' => 'PAYMENT_LINK',
        'amount' => round($amount, 2),
        'currency' => 'PHP',
        'country' => 'PH',
        'locale' => 'en',
        'description' => 'FixTrack repair booking #' . $bookingId,
        'items' => [[
            'reference_id' => 'repair-' . ($booking['repair_id'] ?: $bookingId),
            'type' => 'PHYSICAL_SERVICE',
            'name' => 'FixTrack E-bike Repair',
            'net_unit_amount' => round($amount, 2),
            'quantity' => 1,
            'currency' => 'PHP',
            'description' => 'Repair for ' . ($booking['ebike_model'] ?: 'registered e-bike'),
        ]],
        'success_return_url' => $baseUrl . '/xendit_payment_success.php?booking_id=' . $bookingId . '&customer_id=' . $customerId,
        'cancel_return_url' => $baseUrl . '/xendit_payment_cancel.php?booking_id=' . $bookingId,
        'metadata' => [
            'booking_id' => (string)$bookingId,
            'customer_id' => (string)$customerId,
            'repair_id' => (string)($booking['repair_id'] ?? 0),
        ],
    ];
    if (filter_var($booking['customer_email'] ?? '', FILTER_VALIDATE_EMAIL)) {
        $payload['customer'] = [
            'reference_id' => 'customer-' . $customerId,
            'type' => 'INDIVIDUAL',
            'email' => $booking['customer_email'],
        ];
    }

    $session = xenditRequest('POST', '/sessions', $payload);
    $paymentUrl = (string)($session['payment_link_url'] ?? '');
    $paymentSessionId = (string)($session['payment_session_id'] ?? '');
    if ($paymentUrl === '' || $paymentSessionId === '') {
        throw new RuntimeException('Xendit did not return a hosted payment link.');
    }

    $update = $conn->prepare("UPDATE repair_bookings SET xendit_payment_session_id = ?, payment_status = 'Pending' WHERE booking_id = ? AND customer_id = ?");
    $update->bind_param('sii', $paymentSessionId, $bookingId, $customerId);
    $update->execute();
    logActivity($conn, $customerId, 'Customer', 'Xendit payment started', 'Started Xendit Checkout for booking #' . $bookingId . '.', 'booking', $bookingId);

    header('Location: ' . $paymentUrl);
    exit();
} catch (Throwable $exception) {
    error_log('Xendit Checkout error: ' . $exception->getMessage());
    header('Location: customerdashboard.php?payment=error');
    exit();
}
