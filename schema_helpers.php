<?php

function columnExists(mysqli $conn, string $table, string $column): bool
{
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
    ");
    $stmt->bind_param("ss", $table, $column);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    return (int)($row['total'] ?? 0) > 0;
}

function ensureRepairAutomationSchema(mysqli $conn): void
{
    if (!columnExists($conn, 'repair_bookings', 'warranty_id')) {
        $conn->query("ALTER TABLE repair_bookings ADD COLUMN warranty_id INT NULL AFTER customer_id");
    }

    if (!columnExists($conn, 'repair_bookings', 'estimated_amount')) {
        $conn->query("ALTER TABLE repair_bookings ADD COLUMN estimated_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER warranty_status");
    }

    if (!columnExists($conn, 'repairs', 'booking_id')) {
        $conn->query("ALTER TABLE repairs ADD COLUMN booking_id INT NULL AFTER repair_id");
    }

    if (!columnExists($conn, 'repairs', 'warranty_status')) {
        $conn->query("ALTER TABLE repairs ADD COLUMN warranty_status VARCHAR(20) NOT NULL DEFAULT 'Invalid' AFTER repair_status");
    }
}

function ensureStripeSchema(mysqli $conn): void
{
    if (!columnExists($conn, 'repair_bookings', 'stripe_checkout_session_id')) {
        $conn->query("ALTER TABLE repair_bookings ADD COLUMN stripe_checkout_session_id VARCHAR(255) NULL AFTER payment_status");
    }

    if (!columnExists($conn, 'repair_bookings', 'stripe_payment_intent_id')) {
        $conn->query("ALTER TABLE repair_bookings ADD COLUMN stripe_payment_intent_id VARCHAR(255) NULL AFTER stripe_checkout_session_id");
    }

    if (!columnExists($conn, 'repair_bookings', 'paid_at')) {
        $conn->query("ALTER TABLE repair_bookings ADD COLUMN paid_at DATETIME NULL AFTER stripe_payment_intent_id");
    }

    if (!columnExists($conn, 'repair_bookings', 'stripe_invoice_id')) {
        $conn->query("ALTER TABLE repair_bookings ADD COLUMN stripe_invoice_id VARCHAR(255) NULL AFTER paid_at");
    }

    if (!columnExists($conn, 'repair_bookings', 'booking_email_sent_at')) {
        $conn->query("ALTER TABLE repair_bookings ADD COLUMN booking_email_sent_at DATETIME NULL AFTER stripe_invoice_id");
    }

    if (!columnExists($conn, 'repair_bookings', 'receipt_email_sent_at')) {
        $conn->query("ALTER TABLE repair_bookings ADD COLUMN receipt_email_sent_at DATETIME NULL AFTER booking_email_sent_at");
    }
}

?>
