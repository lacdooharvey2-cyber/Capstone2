<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/schema_helpers.php';
require_once __DIR__ . '/netcorepay_config.php';
require_once __DIR__ . '/mail_helper.php';
require_once __DIR__ . '/activity_log_helper.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'Customer') {
    header('Location: login.php');
    exit();
}

ensureRepairAutomationSchema($conn);
ensureNetcorepaySchema($conn);
$bookingId = (int)($_GET['booking_id'] ?? 0);
$customerId = (int)$_SESSION['user_id'];

try {
    if ($bookingId <= 0 || $customerId !== (int)($_GET['customer_id'] ?? 0)) {
        throw new RuntimeException('Invalid NetCorePay return details.');
    }

    $stmt = $conn->prepare("SELECT rb.netcorepay_payment_id, rb.receipt_email_sent_at,
           COALESCE(NULLIF(rb.estimated_amount, 0), r.amount, 0) AS payable_amount,
           r.ebike_model, u.name AS customer_name, u.email AS customer_email
        FROM repair_bookings rb
        LEFT JOIN repairs r ON r.booking_id = rb.booking_id
        LEFT JOIN users u ON u.user_id = rb.customer_id
        WHERE rb.booking_id = ? AND rb.customer_id = ? LIMIT 1");
    $stmt->bind_param('ii', $bookingId, $customerId);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();
    if (!$booking || trim((string)$booking['netcorepay_payment_id']) === '') {
        throw new RuntimeException('NetCorePay payment was not found.');
    }

    $payment = netcorepayRequest('GET', '/v1/payments/' . rawurlencode((string)$booking['netcorepay_payment_id']));
    if (($payment['status'] ?? '') !== 'succeeded') {
        throw new RuntimeException('NetCorePay payment is not completed.');
    }

    $paymentId = (string)$payment['id'];
    $update = $conn->prepare("UPDATE repair_bookings SET payment_status = 'Paid', paid_at = NOW(), netcorepay_payment_id = ? WHERE booking_id = ? AND customer_id = ?");
    $update->bind_param('sii', $paymentId, $bookingId, $customerId);
    $update->execute();
    logActivity($conn, $customerId, 'Customer', 'NetCorePay payment completed', 'Completed GCash payment for booking #' . $bookingId . '.', 'booking', $bookingId);

    if (empty($booking['receipt_email_sent_at']) && filter_var($booking['customer_email'] ?? '', FILTER_VALIDATE_EMAIL)) {
        $amount = number_format((float)$booking['payable_amount'], 2);
        $safeName = htmlspecialchars($booking['customer_name'] ?? 'Customer', ENT_QUOTES, 'UTF-8');
        $safeModel = htmlspecialchars($booking['ebike_model'] ?? 'Registered e-bike', ENT_QUOTES, 'UTF-8');
        $safePaymentId = htmlspecialchars($paymentId, ENT_QUOTES, 'UTF-8');
        if (sendFixTrackEmail(
            $booking['customer_email'],
            (string)($booking['customer_name'] ?? 'Customer'),
            'RedStar NetCorePay payment receipt #' . $bookingId,
            "<h2>Payment received</h2><p>Hello {$safeName},</p><p>Your GCash repair payment was verified successfully.</p><table cellpadding='6'><tr><td><strong>Booking</strong></td><td>#{$bookingId}</td></tr><tr><td><strong>E-bike</strong></td><td>{$safeModel}</td></tr><tr><td><strong>Amount paid</strong></td><td>PHP {$amount}</td></tr><tr><td><strong>Payment ID</strong></td><td>{$safePaymentId}</td></tr></table>",
            "Payment receipt for RedStar booking #{$bookingId}\nE-bike: {$booking['ebike_model']}\nAmount paid: PHP {$amount}\nPayment ID: {$paymentId}"
        )) {
            $receiptStmt = $conn->prepare('UPDATE repair_bookings SET receipt_email_sent_at = NOW() WHERE booking_id = ? AND customer_id = ?');
            $receiptStmt->bind_param('ii', $bookingId, $customerId);
            $receiptStmt->execute();
        }
    }

    header('Location: customerdashboard.php?payment=success');
    exit();
} catch (Throwable $exception) {
    error_log('NetCorePay payment verification error: ' . $exception->getMessage());
    header('Location: customerdashboard.php?payment=error');
    exit();
}
