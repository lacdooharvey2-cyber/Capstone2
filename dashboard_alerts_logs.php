<?php
declare(strict_types=1);

require_once __DIR__ . '/activity_log_helper.php';

function renderDashboardAlerts(mysqli $conn, string $role, int $userId): void
{
    ensureActivityLogSchema($conn);

    if (!isset($_SESSION['dashboard_log_' . $role])) {
        logActivity($conn, $userId, $role, 'Dashboard viewed', 'Opened the ' . $role . ' dashboard.');
        $_SESSION['dashboard_log_' . $role] = true;
    }

    $alerts = [];
    if (in_array($role, ['Admin', 'SuperAdmin'], true)) {
        $open = dashboardWidgetCount($conn, "SELECT COUNT(*) AS total FROM repairs WHERE repair_status IN ('Pending','In Progress')");
        $pendingPayments = dashboardWidgetCount($conn, "SELECT COUNT(*) AS total FROM repair_bookings rb LEFT JOIN repairs r ON r.booking_id = rb.booking_id WHERE rb.payment_status='Pending' AND COALESCE(NULLIF(rb.estimated_amount,0), r.amount, 0) > 0");
        $expired = dashboardWidgetCount($conn, "SELECT COUNT(*) AS total FROM warranty_records WHERE warranty_status='Expired'");
        $alerts[] = ['danger', 'Open repair queue', $open . ' repair ticket(s) still need attention.', 'adminrepairs.php'];
        $alerts[] = ['warning', 'Pending payments', $pendingPayments . ' priced booking(s) are waiting for payment.', 'adminanalytics.php'];
        $alerts[] = ['secondary', 'Expired warranties', $expired . ' warranty record(s) need review.', 'adminwarranties.php?status=Expired'];
    } elseif ($role === 'Technician') {
        $pending = dashboardWidgetCount($conn, "SELECT COUNT(*) AS total FROM repairs WHERE technician_id={$userId} AND repair_status='Pending'");
        $overdue = dashboardWidgetCount($conn, "SELECT COUNT(*) AS total FROM repairs WHERE technician_id={$userId} AND repair_status IN ('Pending','In Progress') AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $alerts[] = ['warning', 'Pending assigned repairs', $pending . ' assigned repair(s) are waiting to start.', 'technicianrepair.php'];
        $alerts[] = ['danger', 'Overdue jobs', $overdue . ' open assigned job(s) are older than 7 days.', 'technicianrepair.php'];
    } elseif ($role === 'Cashier') {
        $pending = dashboardWidgetCount($conn, "SELECT COUNT(*) AS total FROM repair_bookings rb LEFT JOIN repairs r ON r.booking_id = rb.booking_id WHERE rb.payment_status='Pending' AND COALESCE(NULLIF(rb.estimated_amount,0), r.amount, 0) > 0");
        $overdue = dashboardWidgetCount($conn, "SELECT COUNT(*) AS total FROM repair_bookings rb LEFT JOIN repairs r ON r.booking_id = rb.booking_id WHERE rb.payment_status='Pending' AND COALESCE(NULLIF(rb.estimated_amount,0), r.amount, 0) > 0 AND rb.created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $alerts[] = ['warning', 'Payments awaiting collection', $pending . ' booking(s) have an unpaid repair balance.', 'cashiertransactions.php'];
        $alerts[] = ['danger', 'Overdue unpaid bookings', $overdue . ' unpaid booking(s) are older than 7 days.', 'cashiertransactions.php'];
    } elseif ($role === 'Customer') {
        $active = dashboardWidgetCount($conn, "SELECT COUNT(*) AS total FROM repairs WHERE customer_id={$userId} AND repair_status IN ('Pending','In Progress')");
        $unpaid = dashboardWidgetCount($conn, "SELECT COUNT(*) AS total FROM repair_bookings rb LEFT JOIN repairs r ON r.booking_id = rb.booking_id WHERE rb.customer_id={$userId} AND rb.payment_status='Pending' AND COALESCE(NULLIF(rb.estimated_amount,0), r.amount, 0) > 0");
        $expiring = dashboardWidgetCount($conn, "SELECT COUNT(*) AS total FROM warranty_records WHERE customer_id={$userId} AND warranty_status='Active' AND DATE_ADD(purchase_date, INTERVAL warranty_period MONTH) BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)");
        $alerts[] = ['info', 'Active repairs', $active . ' repair(s) are currently pending or in progress.', 'customerdashboard.php'];
        $alerts[] = ['warning', 'Payment reminder', $unpaid . ' repair payment(s) are waiting for payment.', 'customerdashboard.php'];
        $alerts[] = ['danger', 'Warranties expiring soon', $expiring . ' warranty(s) expire within 30 days.', 'customerdashboard.php'];
    }

    ?>
    <style>
      .dashboard-alerts-logs { margin-bottom: 1.25rem; }
      .dashboard-alerts-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
      .dashboard-alert-item { min-height: 88px; border-left: 4px solid currentColor; border-radius: 8px; padding: 12px 14px; background: #fff; box-shadow: 0 4px 14px rgba(31,41,55,.06); }
      .dashboard-alert-item h6 { font-size: .86rem; margin-bottom: 4px; }
      .dashboard-alert-item p { color: #6c757d; font-size: .78rem; line-height: 1.35; margin: 0; }
      .dashboard-alert-item a { color: inherit; text-decoration: none; }
      @media (max-width: 900px) { .dashboard-alerts-grid { grid-template-columns: 1fr; } }
    </style>
    <section class="dashboard-alerts-logs" aria-label="Reminders and alerts">
      <div class="dashboard-alerts-grid">
        <?php foreach ($alerts as [$color, $title, $text, $link]): ?>
          <div class="dashboard-alert-item text-<?= htmlspecialchars($color) ?>">
            <a href="<?= htmlspecialchars($link) ?>"><h6><?= htmlspecialchars($title) ?></h6><p><?= htmlspecialchars($text) ?></p></a>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
    <?php
}

function dashboardWidgetCount(mysqli $conn, string $sql): int
{
    $result = $conn->query($sql);
    if (!$result) {
        return 0;
    }
    return (int)($result->fetch_assoc()['total'] ?? 0);
}
