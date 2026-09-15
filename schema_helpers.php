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

function calculatedWarrantyRecordStatus(?string $purchaseDate, int $warrantyPeriod): string
{
    if (!$purchaseDate || $warrantyPeriod <= 0) {
        return 'Expired';
    }

    $expiresAt = strtotime($purchaseDate . ' +' . $warrantyPeriod . ' months');
    if ($expiresAt === false) {
        return 'Expired';
    }

    return $expiresAt >= strtotime(date('Y-m-d')) ? 'Active' : 'Expired';
}

function warrantyCoverageStatus(array $warranty): string
{
    if (($warranty['warranty_status'] ?? '') !== 'Active') {
        return 'Invalid';
    }

    return calculatedWarrantyRecordStatus(
        $warranty['purchase_date'] ?? null,
        (int)($warranty['warranty_period'] ?? 0)
    ) === 'Active' ? 'Valid' : 'Invalid';
}

function syncWarrantyStatuses(mysqli $conn): int
{
    $conn->query("
        UPDATE warranty_records
        SET warranty_status = CASE
            WHEN purchase_date IS NOT NULL
             AND warranty_period > 0
             AND DATE_ADD(purchase_date, INTERVAL warranty_period MONTH) >= CURDATE()
            THEN 'Active'
            ELSE 'Expired'
        END,
        claim_date = NULL
        WHERE warranty_status NOT IN ('Claimed', 'Rejected')
    ");

    return $conn->affected_rows;
}

function syncRepairWarrantyCoverage(mysqli $conn): int
{
    $conn->query("
        UPDATE repair_bookings rb
        LEFT JOIN warranty_records w ON w.warranty_id = rb.warranty_id
        SET rb.warranty_status = CASE
            WHEN w.warranty_status = 'Active'
             AND w.purchase_date IS NOT NULL
             AND w.warranty_period > 0
             AND DATE_ADD(w.purchase_date, INTERVAL w.warranty_period MONTH) >= CURDATE()
            THEN 'Valid'
            ELSE 'Invalid'
        END
        WHERE rb.warranty_id IS NOT NULL
    ");
    $bookingUpdates = max(0, $conn->affected_rows);

    $conn->query("
        UPDATE repairs r
        INNER JOIN repair_bookings rb ON rb.booking_id = r.booking_id
        SET r.warranty_status = rb.warranty_status
        WHERE r.booking_id IS NOT NULL
    ");
    $repairUpdates = max(0, $conn->affected_rows);

    return $bookingUpdates + $repairUpdates;
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

function ensureXenditSchema(mysqli $conn): void
{
    if (!columnExists($conn, 'repair_bookings', 'xendit_payment_session_id')) {
        $conn->query("ALTER TABLE repair_bookings ADD COLUMN xendit_payment_session_id VARCHAR(255) NULL AFTER stripe_checkout_session_id");
    }

    if (!columnExists($conn, 'repair_bookings', 'xendit_payment_request_id')) {
        $conn->query("ALTER TABLE repair_bookings ADD COLUMN xendit_payment_request_id VARCHAR(255) NULL AFTER xendit_payment_session_id");
    }

    if (!columnExists($conn, 'repair_bookings', 'xendit_payment_id')) {
        $conn->query("ALTER TABLE repair_bookings ADD COLUMN xendit_payment_id VARCHAR(255) NULL AFTER xendit_payment_session_id");
    }
}

function ensureNetcorepaySchema(mysqli $conn): void
{
    ensureXenditSchema($conn);
    if (!columnExists($conn, 'repair_bookings', 'netcorepay_payment_id')) {
        $conn->query("ALTER TABLE repair_bookings ADD COLUMN netcorepay_payment_id VARCHAR(255) NULL AFTER xendit_payment_id");
    }
}

?>
