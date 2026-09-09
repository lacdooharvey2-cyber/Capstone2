<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/schema_helpers.php';
require_once __DIR__ . '/stripe_config.php';
require_once __DIR__ . '/vendor/autoload.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'Customer') {
    header('Location: login.php');
    exit();
}

ensureRepairAutomationSchema($conn);
ensureStripeSchema($conn);

$bookingId = (int)($_POST['booking_id'] ?? 0);
$customerId = (int)$_SESSION['user_id'];

if ($bookingId <= 0) {
    header('Location: customerdashboard.php?payment=invalid');
    exit();
}

$stmt = $conn->prepare("
    SELECT rb.booking_id, rb.estimated_amount, rb.payment_status, rb.warranty_status,
           COALESCE(NULLIF(rb.estimated_amount, 0), r.amount, 0) AS payable_amount,
           r.repair_id, r.amount, r.ebike_model, u.name AS customer_name, u.email AS customer_email
    FROM repair_bookings rb
    LEFT JOIN repairs r ON r.booking_id = rb.booking_id
    LEFT JOIN users u ON u.user_id = rb.customer_id
    WHERE rb.booking_id = ? AND rb.customer_id = ?
    LIMIT 1
");
$stmt->bind_param('ii', $bookingId, $customerId);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

$amount = (float)($booking['payable_amount'] ?? 0);

if (!$booking || $amount <= 0 || ($booking['payment_status'] ?? '') === 'Paid') {
    header('Location: customerdashboard.php?payment=not_required');
    exit();
}

try {
    $stripe = new \Stripe\StripeClient(stripeSecretKey());
    $session = $stripe->checkout->sessions->create([
        'mode' => 'payment',
        'customer_creation' => 'always',
        'line_items' => [[
            'price_data' => [
                'currency' => 'php',
                'product_data' => [
                    'name' => 'FixTrack E-bike Repair',
                    'description' => 'Repair for ' . ($booking['ebike_model'] ?: 'registered e-bike'),
                ],
                'unit_amount' => (int)round($amount * 100),
            ],
            'quantity' => 1,
        ]],
        'success_url' => stripeBaseUrl() . '/stripe_payment_success.php?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url' => stripeBaseUrl() . '/stripe_payment_cancel.php?booking_id=' . $bookingId,
        'invoice_creation' => [
            'enabled' => true,
            'invoice_data' => [
                'description' => 'FixTrack repair booking #' . $bookingId,
                'metadata' => [
                    'booking_id' => (string)$bookingId,
                    'customer_id' => (string)$customerId,
                ],
            ],
        ],
        'metadata' => [
            'booking_id' => (string)$bookingId,
            'customer_id' => (string)$customerId,
            'repair_id' => (string)($booking['repair_id'] ?? 0),
        ],
    ] + (filter_var($booking['customer_email'] ?? '', FILTER_VALIDATE_EMAIL)
        ? ['customer_email' => $booking['customer_email']]
        : []));

    $update = $conn->prepare("
        UPDATE repair_bookings
        SET stripe_checkout_session_id = ?, payment_status = 'Pending'
        WHERE booking_id = ? AND customer_id = ?
    ");
    $update->bind_param('sii', $session->id, $bookingId, $customerId);
    $update->execute();

    header('Location: ' . $session->url);
    exit();
} catch (Throwable $exception) {
    error_log('Stripe Checkout error: ' . $exception->getMessage());
    header('Location: customerdashboard.php?payment=error');
    exit();
}
?>
