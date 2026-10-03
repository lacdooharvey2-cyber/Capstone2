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

function tableExists(mysqli $conn, string $table): bool
{
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
    ");
    $stmt->bind_param("s", $table);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    return (int)($row['total'] ?? 0) > 0;
}

function ensureRepairAutomationSchema(mysqli $conn): void
{
    $conn->query("ALTER TABLE users MODIFY role ENUM('Customer','Staff','Technician','HeadTechnician','Cashier','AssistantAdmin','Admin','AssistantSuperAdmin','SuperAdmin') NOT NULL");

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

    if (!columnExists($conn, 'repair_bookings', 'proof_file')) {
        $conn->query("ALTER TABLE repair_bookings ADD COLUMN proof_file VARCHAR(255) NULL AFTER description");
    }

    if (!columnExists($conn, 'repairs', 'proof_file')) {
        $conn->query("ALTER TABLE repairs ADD COLUMN proof_file VARCHAR(255) NULL AFTER issue_description");
    }

    if (!columnExists($conn, 'warranty_records', 'ebike_image_url')) {
        $conn->query("ALTER TABLE warranty_records ADD COLUMN ebike_image_url VARCHAR(500) NULL AFTER ebike_model");
    }

    $conn->query("CREATE TABLE IF NOT EXISTS technician_repair_reports (
        report_id INT AUTO_INCREMENT PRIMARY KEY,
        repair_id INT NOT NULL UNIQUE,
        technician_id INT NOT NULL,
        work_performed TEXT NULL,
        technician_notes TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_report_technician (technician_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS repair_charge_items (
        item_id INT AUTO_INCREMENT PRIMARY KEY,
        report_id INT NOT NULL,
        item_type ENUM('Labor','Part','Other') NOT NULL DEFAULT 'Part',
        item_name VARCHAR(150) NOT NULL,
        quantity DECIMAL(10,2) NOT NULL DEFAULT 1.00,
        unit_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_charge_report (report_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
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

function normalizePaymentMethod(string $method): string
{
    return in_array($method, ['Cashier', 'GCash', 'PayPal'], true) ? $method : 'Cashier';
}

function normalizePaymentStatus(string $status): string
{
    return in_array($status, ['Pending', 'Paid', 'Cancelled'], true) ? $status : 'Pending';
}

function statusBadgeClass(string $status): string
{
    return match ($status) {
        'Pending' => 'warning',
        'In Progress' => 'primary',
        'Completed', 'Paid', 'Valid', 'Active' => 'success',
        'Cancelled', 'Expired', 'Inactive' => 'secondary',
        'Rejected', 'Invalid' => 'danger',
        default => 'secondary',
    };
}

function syncPaymentRecord(mysqli $conn, int $repairId, int $customerId, float $amount, string $method, string $status): bool
{
    if (!tableExists($conn, 'payments') || $repairId <= 0 || $customerId <= 0) {
        return false;
    }

    $method = normalizePaymentMethod($method);
    $status = normalizePaymentStatus($status);
    $amount = max(0, $amount);

    $find = $conn->prepare("
        SELECT payment_id
        FROM payments
        WHERE repair_id = ? AND customer_id = ?
        ORDER BY payment_id DESC
        LIMIT 1
    ");
    $find->bind_param("ii", $repairId, $customerId);
    $find->execute();
    $existing = $find->get_result()->fetch_assoc();

    if ($existing) {
        $paymentId = (int)$existing['payment_id'];
        $update = $conn->prepare("
            UPDATE payments
            SET amount = ?, method = ?, status = ?
            WHERE payment_id = ?
        ");
        $update->bind_param("dssi", $amount, $method, $status, $paymentId);
        return $update->execute();
    }

    $insert = $conn->prepare("
        INSERT INTO payments (repair_id, customer_id, amount, method, status)
        VALUES (?, ?, ?, ?, ?)
    ");
    $insert->bind_param("iidss", $repairId, $customerId, $amount, $method, $status);
    return $insert->execute();
}

function syncBookingPaymentToPayments(mysqli $conn, int $bookingId, string $method = 'Cashier', ?string $statusOverride = null): bool
{
    if (!tableExists($conn, 'payments') || $bookingId <= 0) {
        return false;
    }

    $stmt = $conn->prepare("
        SELECT r.repair_id, rb.customer_id,
               COALESCE(NULLIF(rb.estimated_amount, 0), r.amount, 0) AS payable_amount,
               rb.payment_status
        FROM repair_bookings rb
        INNER JOIN repairs r ON r.booking_id = rb.booking_id
        WHERE rb.booking_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $bookingId);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();

    if (!$booking) {
        return false;
    }

    return syncPaymentRecord(
        $conn,
        (int)$booking['repair_id'],
        (int)$booking['customer_id'],
        (float)$booking['payable_amount'],
        $method,
        $statusOverride ?? (string)$booking['payment_status']
    );
}

function syncAllBookingPaymentsToPayments(mysqli $conn): int
{
    if (!tableExists($conn, 'payments')) {
        return 0;
    }

    ensureStripeSchema($conn);
    ensureNetcorepaySchema($conn);

    $result = $conn->query("
        SELECT rb.booking_id,
               CASE
                 WHEN COALESCE(rb.netcorepay_payment_id, rb.xendit_payment_id, rb.xendit_payment_request_id, rb.xendit_payment_session_id, '') <> '' THEN 'GCash'
                 WHEN COALESCE(rb.stripe_payment_intent_id, rb.stripe_checkout_session_id, '') <> '' THEN 'PayPal'
                 ELSE 'Cashier'
               END AS payment_method
        FROM repair_bookings rb
        INNER JOIN repairs r ON r.booking_id = rb.booking_id
        WHERE rb.payment_status IN ('Pending', 'Paid', 'Cancelled')
    ");

    if (!$result) {
        return 0;
    }

    $synced = 0;
    while ($row = $result->fetch_assoc()) {
        if (syncBookingPaymentToPayments($conn, (int)$row['booking_id'], (string)$row['payment_method'])) {
            $synced++;
        }
    }

    return $synced;
}

?>
