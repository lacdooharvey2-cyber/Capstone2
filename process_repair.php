<?php
session_start();
include("db.php");
include_once("schema_helpers.php");
include_once("mail_helper.php");
include_once("activity_log_helper.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Customer') {
    header("Location: login.php");
    exit();
}

ensureRepairAutomationSchema($conn);
ensureStripeSchema($conn);
syncWarrantyStatuses($conn);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $customer_id = intval($_SESSION['user_id']);
    $service = trim($_POST['service_type'] ?? 'Repair');
    $service = in_array($service, ['Repair', 'Maintenance'], true) ? $service : 'Repair';
    $preferred_date = $_POST['preferred_date'] ?? null;
    $time_slot = $_POST['time_slot'] ?? '';
    $issue = trim($_POST['issue_description'] ?? '');
    $warranty_id = intval($_POST['warranty_id'] ?? 0);

    if ($warranty_id <= 0 || $issue === '' || empty($preferred_date) || $time_slot === '') {
        header("Location: customerbookrepair.php?error=incomplete");
        exit();
    }

    if (strtotime((string)$preferred_date) < strtotime(date('Y-m-d'))) {
        header("Location: customerbookrepair.php?error=past_date");
        exit();
    }

    $slotCapacity = 3;
    if (!in_array($time_slot, ['Morning', 'Afternoon', 'Evening'], true)) {
        header("Location: customerbookrepair.php?error=incomplete");
        exit();
    }
    $slotStmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM repair_bookings
        WHERE preferred_date = ?
          AND preferred_time = ?
          AND booking_status NOT IN ('Cancelled', 'Rejected')
    ");
    $slotStmt->bind_param("ss", $preferred_date, $time_slot);
    $slotStmt->execute();
    $slotCount = (int)($slotStmt->get_result()->fetch_assoc()['total'] ?? 0);

    if ($slotCount >= $slotCapacity) {
        header("Location: customerbookrepair.php?error=slot_full&date=" . urlencode((string)$preferred_date) . "&slot=" . urlencode($time_slot));
        exit();
    }

    $bikeStmt = $conn->prepare("
        SELECT warranty_id, ebike_model, purchase_date, warranty_period, warranty_status
        FROM warranty_records
        WHERE warranty_id = ? AND customer_id = ?
        LIMIT 1
    ");
    $bikeStmt->bind_param("ii", $warranty_id, $customer_id);
    $bikeStmt->execute();
    $bike = $bikeStmt->get_result()->fetch_assoc();

    if (!$bike) {
        header("Location: customerbookrepair.php?error=invalid_ebike");
        exit();
    }

    $model = $bike['ebike_model'];
    $warranty_status = warrantyCoverageStatus($bike);
    $amount = 0.00;
    $payment_status = 'Pending';
    $tracking = 'RS-' . date('YmdHis') . '-' . $customer_id;
    $proofFile = null;

    if (isset($_FILES['proof_file']) && ($_FILES['proof_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['proof_file']['error'] !== UPLOAD_ERR_OK || (int)($_FILES['proof_file']['size'] ?? 0) > 5 * 1024 * 1024) {
            header("Location: customerbookrepair.php?error=upload");
            exit();
        }

        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx'];
        $originalName = (string)($_FILES['proof_file']['name'] ?? '');
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedExtensions, true)) {
            header("Location: customerbookrepair.php?error=upload");
            exit();
        }

        $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'repair_proofs';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        $filename = 'repair-proof-' . $customer_id . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $extension;
        $target = $uploadDir . DIRECTORY_SEPARATOR . $filename;
        if (!move_uploaded_file($_FILES['proof_file']['tmp_name'], $target)) {
            header("Location: customerbookrepair.php?error=upload");
            exit();
        }
        $proofFile = 'uploads/repair_proofs/' . $filename;
    }

    $description = trim(
        "E-bike: " . $model .
        "\nIssue: " . $issue .
        "\nPreferred time: " . $time_slot .
        "\nWarranty: " . $warranty_status .
        "\nRepair cost: Pending technician assessment"
    );

    $customerStmt = $conn->prepare("SELECT name, email FROM users WHERE user_id = ? LIMIT 1");
    $customerStmt->bind_param("i", $customer_id);
    $customerStmt->execute();
    $customer = $customerStmt->get_result()->fetch_assoc() ?: [];

    $stmt = $conn->prepare("
        INSERT INTO repair_bookings
        (customer_id, warranty_id, service_type, preferred_date, preferred_time, description, proof_file, booking_status, tracking_number, warranty_status, estimated_amount, payment_status)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending', ?, ?, ?, ?)
    ");
    $stmt->bind_param("iisssssssds", $customer_id, $warranty_id, $service, $preferred_date, $time_slot, $description, $proofFile, $tracking, $warranty_status, $amount, $payment_status);
    $stmt->execute();
    $booking_id = $conn->insert_id;

    $repairStmt = $conn->prepare("
        INSERT INTO repairs (booking_id, customer_id, ebike_model, issue_description, proof_file, repair_status, warranty_status, amount)
        VALUES (?, ?, ?, ?, ?, 'Pending', ?, ?)
    ");
    $repairStmt->bind_param("iissssd", $booking_id, $customer_id, $model, $issue, $proofFile, $warranty_status, $amount);
    $repairStmt->execute();
    logActivity($conn, $customer_id, 'Customer', 'Repair booked', 'Created repair booking #' . $booking_id . ' for ' . $model . '.', 'repair', (int)$conn->insert_id);

    if ($warranty_status === 'Invalid' && $bike['warranty_status'] === 'Active') {
        $expireStmt = $conn->prepare("UPDATE warranty_records SET warranty_status = 'Expired' WHERE warranty_id = ?");
        $expireStmt->bind_param("i", $warranty_id);
        $expireStmt->execute();
    }

    if (filter_var($customer['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
        $safeName = htmlspecialchars($customer['name'] ?? 'Customer', ENT_QUOTES, 'UTF-8');
        $safeModel = htmlspecialchars($model, ENT_QUOTES, 'UTF-8');
        $safeIssue = htmlspecialchars($issue, ENT_QUOTES, 'UTF-8');
        $safeDate = htmlspecialchars((string)$preferred_date, ENT_QUOTES, 'UTF-8');
        $safeTime = htmlspecialchars($time_slot, ENT_QUOTES, 'UTF-8');
        $safeWarranty = htmlspecialchars($warranty_status, ENT_QUOTES, 'UTF-8');
        $emailSent = sendFixTrackEmail(
            $customer['email'],
            (string)($customer['name'] ?? 'Customer'),
            'FixTrack booking confirmation #' . $booking_id,
            "<h2>Repair booking received</h2><p>Hello {$safeName},</p><p>Your FixTrack repair booking has been received.</p><table cellpadding='6'><tr><td><strong>Booking</strong></td><td>#{$booking_id}</td></tr><tr><td><strong>Tracking</strong></td><td>{$tracking}</td></tr><tr><td><strong>E-bike</strong></td><td>{$safeModel}</td></tr><tr><td><strong>Issue</strong></td><td>{$safeIssue}</td></tr><tr><td><strong>Preferred schedule</strong></td><td>{$safeDate} - {$safeTime}</td></tr><tr><td><strong>Warranty check</strong></td><td>{$safeWarranty}</td></tr><tr><td><strong>Payment</strong></td><td>Pending technician assessment</td></tr></table><p>We will notify you when the technician sets the repair price.</p>",
            "Repair booking #{$booking_id}\nTracking: {$tracking}\nE-bike: {$model}\nIssue: {$issue}\nSchedule: {$preferred_date} - {$time_slot}\nWarranty: {$warranty_status}\nPayment: Pending technician assessment"
        );
        if ($emailSent) {
            $emailStmt = $conn->prepare("UPDATE repair_bookings SET booking_email_sent_at = NOW() WHERE booking_id = ?");
            $emailStmt->bind_param("i", $booking_id);
            $emailStmt->execute();
        }
    }

    header("Location: customerdashboard.php?booking=success");
    exit();
}

header("Location: customerbookrepair.php");
exit();
?>
