<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/schema_helpers.php';
require_once __DIR__ . '/stripe_config.php';
require_once __DIR__ . '/mail_helper.php';
require_once __DIR__ . '/activity_log_helper.php';
require_once __DIR__ . '/vendor/autoload.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'Customer') {
    header('Location: login.php');
    exit();
}

ensureRepairAutomationSchema($conn);
ensureStripeSchema($conn);
$sessionId = trim($_GET['session_id'] ?? '');

try {
    if ($sessionId === '') {
        throw new RuntimeException('Missing Checkout Session.');
    }

    $stripe = new \Stripe\StripeClient(stripeSecretKey());
    $session = $stripe->checkout->sessions->retrieve($sessionId, []);
    $metadata = $session->metadata ? $session->metadata->toArray() : [];
    $bookingId = (int)($metadata['booking_id'] ?? 0);
    $customerId = (int)($metadata['customer_id'] ?? 0);

    if ($session->payment_status !== 'paid' || $customerId !== (int)$_SESSION['user_id'] || $bookingId <= 0) {
        throw new RuntimeException('Payment could not be verified.');
    }

    $paymentIntentId = is_string($session->payment_intent) ? $session->payment_intent : null;
    $invoiceId = is_string($session->invoice) ? $session->invoice : null;
    $update = $conn->prepare("
        UPDATE repair_bookings
        SET payment_status = 'Paid', paid_at = NOW(),
            stripe_payment_intent_id = ?, stripe_checkout_session_id = ?, stripe_invoice_id = ?
        WHERE booking_id = ? AND customer_id = ?
    ");
    $update->bind_param('sssii', $paymentIntentId, $session->id, $invoiceId, $bookingId, $customerId);
    $update->execute();
    logActivity($conn, $customerId, 'Customer', 'Stripe payment completed', 'Completed Stripe payment for booking #' . $bookingId . '.', 'booking', $bookingId);

    $bookingStmt = $conn->prepare("
        SELECT rb.estimated_amount, rb.receipt_email_sent_at,
               r.ebike_model, u.name AS customer_name, u.email AS customer_email
        FROM repair_bookings rb
        LEFT JOIN repairs r ON r.booking_id = rb.booking_id
        LEFT JOIN users u ON u.user_id = rb.customer_id
        WHERE rb.booking_id = ? AND rb.customer_id = ?
        LIMIT 1
    ");
    $bookingStmt->bind_param('ii', $bookingId, $customerId);
    $bookingStmt->execute();
    $booking = $bookingStmt->get_result()->fetch_assoc() ?: [];

    if (empty($booking['receipt_email_sent_at']) && filter_var($booking['customer_email'] ?? '', FILTER_VALIDATE_EMAIL)) {
        $invoiceUrl = '';
        if ($invoiceId !== null) {
            try {
                $invoice = $stripe->invoices->retrieve($invoiceId, []);
                $invoiceUrl = (string)($invoice->hosted_invoice_url ?: $invoice->invoice_pdf ?: '');
            } catch (Throwable $invoiceException) {
                error_log('Stripe invoice retrieval error: ' . $invoiceException->getMessage());
            }
        }

        $amount = number_format(((int)($session->amount_total ?? 0)) / 100, 2);
        $safeName = htmlspecialchars($booking['customer_name'] ?? 'Customer', ENT_QUOTES, 'UTF-8');
        $safeModel = htmlspecialchars($booking['ebike_model'] ?? 'Registered e-bike', ENT_QUOTES, 'UTF-8');
        $safeInvoiceUrl = htmlspecialchars($invoiceUrl, ENT_QUOTES, 'UTF-8');
        $invoiceLink = $safeInvoiceUrl !== ''
            ? "<p><a href='{$safeInvoiceUrl}'>View or download your Stripe invoice</a></p>"
            : "<p>Invoice ID: " . htmlspecialchars($invoiceId ?? 'Pending', ENT_QUOTES, 'UTF-8') . "</p>";
        $emailSent = sendFixTrackEmail(
            $booking['customer_email'],
            (string)($booking['customer_name'] ?? 'Customer'),
            'FixTrack payment receipt #' . $bookingId,
            "<h2>Payment received</h2><p>Hello {$safeName},</p><p>Your repair payment was verified successfully.</p><table cellpadding='6'><tr><td><strong>Booking</strong></td><td>#{$bookingId}</td></tr><tr><td><strong>E-bike</strong></td><td>{$safeModel}</td></tr><tr><td><strong>Amount paid</strong></td><td>PHP {$amount}</td></tr><tr><td><strong>Payment status</strong></td><td>Paid</td></tr></table>{$invoiceLink}<p>Thank you for using FixTrack.</p>",
            "Payment receipt for FixTrack booking #{$bookingId}\nE-bike: {$booking['ebike_model']}\nAmount paid: PHP {$amount}\nStatus: Paid\nInvoice: " . ($invoiceUrl !== '' ? $invoiceUrl : ($invoiceId ?? 'Pending'))
        );
        if ($emailSent) {
            $receiptStmt = $conn->prepare("UPDATE repair_bookings SET receipt_email_sent_at = NOW() WHERE booking_id = ? AND customer_id = ?");
            $receiptStmt->bind_param('ii', $bookingId, $customerId);
            $receiptStmt->execute();
        }
    }

    header('Location: customerdashboard.php?payment=success');
    exit();
} catch (Throwable $exception) {
    error_log('Stripe payment verification error: ' . $exception->getMessage());
    header('Location: customerdashboard.php?payment=error');
    exit();
}
?>
