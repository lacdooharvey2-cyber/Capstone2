<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/schema_helpers.php';
require_once __DIR__ . '/xendit_config.php';
require_once __DIR__ . '/mail_helper.php';
require_once __DIR__ . '/activity_log_helper.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'Customer') {
    header('Location: login.php');
    exit();
}

ensureRepairAutomationSchema($conn);
ensureXenditSchema($conn);
$bookingId = (int)($_GET['booking_id'] ?? 0);
$customerId = (int)$_SESSION['user_id'];

try {
    if ($bookingId <= 0 || $customerId !== (int)($_GET['customer_id'] ?? 0)) {
        throw new RuntimeException('Invalid Xendit return details.');
    }

    $stmt = $conn->prepare("SELECT rb.xendit_payment_session_id, rb.xendit_payment_request_id,
           rb.estimated_amount, rb.receipt_email_sent_at,
           r.ebike_model, u.name AS customer_name, u.email AS customer_email
        FROM repair_bookings rb
        LEFT JOIN repairs r ON r.booking_id = rb.booking_id
        LEFT JOIN users u ON u.user_id = rb.customer_id
        WHERE rb.booking_id = ? AND rb.customer_id = ? LIMIT 1");
    $stmt->bind_param('ii', $bookingId, $customerId);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();
    if (!$booking) {
        throw new RuntimeException('Xendit payment record was not found.');
    }
    $paymentRequestId = (string)($booking['xendit_payment_request_id'] ?? '');
    $sessionId = (string)($booking['xendit_payment_session_id'] ?? '');
    if ($paymentRequestId === '' && $sessionId === '') {
        throw new RuntimeException('Xendit payment record was not found.');
    }

    if ($paymentRequestId !== '') {
        $payment = xenditRequest('GET', '/v3/payment_requests/' . rawurlencode($paymentRequestId), null, [
            'api-version: 2024-11-11',
        ]);
        if (!in_array(($payment['status'] ?? ''), ['SUCCEEDED', 'AUTHORIZED'], true)) {
            throw new RuntimeException('Xendit payment is not completed.');
        }
    } else {
        $payment = xenditRequest('GET', '/sessions/' . rawurlencode($sessionId));
        if (($payment['status'] ?? '') !== 'COMPLETED') {
            throw new RuntimeException('Xendit payment is not completed.');
        }
    }

    $paymentId = (string)($payment['payment_id'] ?? $payment['payment_request_id'] ?? $paymentRequestId);
    $update = $conn->prepare("UPDATE repair_bookings SET payment_status = 'Paid', paid_at = NOW(), xendit_payment_id = ? WHERE booking_id = ? AND customer_id = ?");
    $update->bind_param('sii', $paymentId, $bookingId, $customerId);
    $update->execute();
    syncBookingPaymentToPayments($conn, $bookingId, 'GCash', 'Paid');
    logActivity($conn, $customerId, 'Customer', 'Xendit payment completed', 'Completed Xendit payment for booking #' . $bookingId . '.', 'booking', $bookingId);

    if (empty($booking['receipt_email_sent_at']) && filter_var($booking['customer_email'] ?? '', FILTER_VALIDATE_EMAIL)) {
        $amount = number_format((float)$booking['estimated_amount'], 2);
        $safeName = htmlspecialchars($booking['customer_name'] ?? 'Customer', ENT_QUOTES, 'UTF-8');
        $safeModel = htmlspecialchars($booking['ebike_model'] ?? 'Registered e-bike', ENT_QUOTES, 'UTF-8');
        $safePaymentId = htmlspecialchars($paymentId ?: 'Pending', ENT_QUOTES, 'UTF-8');
        $emailSent = sendFixTrackEmail(
            $booking['customer_email'],
            (string)($booking['customer_name'] ?? 'Customer'),
            'FixTrack Xendit payment receipt #' . $bookingId,
            "<h2>Payment received</h2><p>Hello {$safeName},</p><p>Your Xendit repair payment was verified successfully.</p><table cellpadding='6'><tr><td><strong>Booking</strong></td><td>#{$bookingId}</td></tr><tr><td><strong>E-bike</strong></td><td>{$safeModel}</td></tr><tr><td><strong>Amount paid</strong></td><td>PHP {$amount}</td></tr><tr><td><strong>Payment ID</strong></td><td>{$safePaymentId}</td></tr></table><p>Thank you for using FixTrack.</p>",
            "Payment receipt for FixTrack booking #{$bookingId}\nE-bike: {$booking['ebike_model']}\nAmount paid: PHP {$amount}\nPayment ID: " . ($paymentId ?: 'Pending')
        );
        if ($emailSent) {
            $receiptStmt = $conn->prepare('UPDATE repair_bookings SET receipt_email_sent_at = NOW() WHERE booking_id = ? AND customer_id = ?');
            $receiptStmt->bind_param('ii', $bookingId, $customerId);
            $receiptStmt->execute();
        }
    }
    header('Location: customerdashboard.php?payment=success');
    exit();
} catch (Throwable $exception) {
    error_log('Xendit payment verification error: ' . $exception->getMessage());
    header('Location: customerdashboard.php?payment=error');
    exit();
}
