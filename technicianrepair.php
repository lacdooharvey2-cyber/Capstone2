<?php
session_start();
include("db.php");
include_once("schema_helpers.php");

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['Technician', 'HeadTechnician'], true)) {
    header("Location: login.php");
    exit();
}

ensureRepairAutomationSchema($conn);

$user_id = intval($_SESSION['user_id']);
$isHeadTechnician = ($_SESSION['role'] ?? '') === 'HeadTechnician';
$repairScope = $isHeadTechnician ? '1=1' : "(r.technician_id = $user_id OR r.technician_id IS NULL)";
$repairs = $conn->query("
  SELECT r.repair_id, r.created_at, r.customer_id, u.name AS customer_name, u.contact_number, u.province_address,
         r.ebike_model, r.issue_description, r.proof_file, r.warranty_status, r.amount, r.repair_status,
         r.technician_id, tech.name AS technician_name,
         rb.preferred_date, rb.preferred_time
  FROM repairs r
  LEFT JOIN repair_bookings rb ON rb.booking_id = r.booking_id
  LEFT JOIN users u ON u.user_id = r.customer_id
  LEFT JOIN users tech ON tech.user_id = r.technician_id
  WHERE $repairScope
  ORDER BY r.created_at DESC
");

$scheduleStmt = $conn->prepare("
  SELECT r.repair_id, r.repair_status, r.ebike_model, u.name AS customer_name,
         rb.preferred_date, rb.preferred_time, r.issue_description
  FROM repairs r
  INNER JOIN repair_bookings rb ON rb.booking_id = r.booking_id
  LEFT JOIN users u ON u.user_id = r.customer_id
  WHERE " . ($isHeadTechnician ? "1=1" : "(r.technician_id = ? OR r.technician_id IS NULL)") . "
    AND rb.preferred_date IS NOT NULL
  ORDER BY rb.preferred_date, rb.preferred_time
");
if (!$isHeadTechnician) {
    $scheduleStmt->bind_param("i", $user_id);
}
$scheduleStmt->execute();
$scheduleResult = $scheduleStmt->get_result();
$calendarEvents = [];
while ($event = $scheduleResult->fetch_assoc()) {
    $status = $event['repair_status'] ?? 'Pending';
    $calendarEvents[] = [
        'id' => (string)$event['repair_id'],
        'title' => trim(($event['customer_name'] ?? 'Customer') . ' - ' . ($event['ebike_model'] ?? 'E-bike')),
        'start' => $event['preferred_date'],
        'url' => 'repairdetails.php?id=' . urlencode((string)$event['repair_id']),
        'className' => 'repair-event-' . strtolower(str_replace(' ', '-', $status)),
        'extendedProps' => [
            'status' => $status,
            'timeSlot' => $event['preferred_time'] ?: 'Unscheduled',
            'customer' => $event['customer_name'] ?: 'Customer',
            'model' => $event['ebike_model'] ?: 'E-bike',
            'issue' => $event['issue_description'] ?: '',
        ],
    ];
}

function shortDateTime(?string $value): string
{
    if (!$value) {
        return '';
    }

    $timestamp = strtotime($value);
    return $timestamp ? date('m/d H:i', $timestamp) : $value;
}

function shortSchedule(?string $date, ?string $time): string
{
    if (!$date && !$time) {
        return 'Not set';
    }

    $timestamp = $date ? strtotime($date) : false;
    $shortDate = $timestamp ? date('m/d', $timestamp) : (string)$date;
    return trim($shortDate . ' ' . (string)$time);
}

$warranties = $conn->query("
  SELECT w.warranty_id, w.customer_id, u.name AS customer_name, w.ebike_model,
         w.warranty_period, w.warranty_status, w.claim_date, w.created_at
  FROM warranty_records w
  LEFT JOIN users u ON u.user_id = w.customer_id
  ORDER BY w.created_at DESC
");
$technicianOptions = $conn->query("SELECT user_id, name FROM users WHERE role IN ('Technician','HeadTechnician') AND account_status='Active' ORDER BY role='HeadTechnician' DESC, name");
$assignableTechnicians = [];
if ($technicianOptions) {
    while ($technician = $technicianOptions->fetch_assoc()) {
        $assignableTechnicians[] = $technician;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Manage Repair & Warranty - FixTrack</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/app-theme.css?v=20261014" rel="stylesheet">
</head>
<body>
  <?php include("navbartechnician.php"); ?>

  <div class="technician-workspace">
    <div class="page-hero">
      <h3 class="mb-1">Repair & Warranty Management</h3>
      <p><?= $isHeadTechnician ? 'Deploy technicians, monitor repair progress, and review warranty records.' : 'Manage assigned repair tickets, set repair costs, and update warranty records.' ?></p>
    </div>

    <?php if (isset($_GET['updated'])): ?>
      <div class="alert alert-success">Repair updated.</div>
    <?php elseif (isset($_GET['error'])): ?>
      <div class="alert alert-danger">Unable to update repair.</div>
    <?php endif; ?>

    <ul class="nav nav-tabs mb-3" id="myTab" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link active" id="repairs-tab" data-bs-toggle="tab" data-bs-target="#repairs" type="button" role="tab">Repair Tickets</button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="schedule-tab" data-bs-toggle="tab" data-bs-target="#schedule" type="button" role="tab">Schedule Calendar</button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="warranty-tab" data-bs-toggle="tab" data-bs-target="#warranty" type="button" role="tab">Warranty Records</button>
      </li>
    </ul>

    <div class="tab-content">
      <div class="tab-pane fade show active" id="repairs" role="tabpanel">
        <div class="card shadow-sm"><div class="card-body p-2 p-lg-3">
        <div class="d-flex justify-content-end mb-3">
          <input type="search" class="form-control" id="repairSearch" style="max-width: 360px;" placeholder="Search repair tickets">
        </div>
        <div class="table-responsive repair-table-wrap">
        <table class="table table-hover table-bordered mb-0 repair-ticket-table <?= $isHeadTechnician ? 'is-head-technician' : '' ?>" id="repairTicketsTable">
          <thead class="table-danger">
            <tr>
              <th class="col-id">ID</th>
              <th class="col-date">Date</th>
              <th class="col-schedule">Schedule</th>
              <th class="col-customer">Customer</th>
              <th class="col-contact">Contact</th>
              <?php if ($isHeadTechnician): ?><th class="col-address">Address</th><?php endif; ?>
              <th class="col-bike">E-Bike</th>
              <th class="col-issue">Issue</th>
              <?php if ($isHeadTechnician): ?><th class="col-customer">Technician</th><?php endif; ?>
              <th class="col-warranty">Warranty</th>
              <th class="col-cost">Cost</th>
              <th class="col-status">Status</th>
              <th class="col-action">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($repairs && $repairs->num_rows > 0): ?>
              <?php while($r = $repairs->fetch_assoc()): ?>
                <tr>
                  <td><?= htmlspecialchars($r['repair_id']) ?></td>
                  <td class="text-truncate-cell" title="<?= htmlspecialchars($r['created_at']) ?>"><?= htmlspecialchars(shortDateTime($r['created_at'] ?? '')) ?></td>
                  <td class="text-truncate-cell" title="<?= htmlspecialchars(trim(($r['preferred_date'] ?? '') . ' ' . ($r['preferred_time'] ?? '')) ?: 'Not set') ?>"><?= htmlspecialchars(shortSchedule($r['preferred_date'] ?? null, $r['preferred_time'] ?? null)) ?></td>
                  <td class="text-truncate-cell" title="<?= htmlspecialchars($r['customer_name'] ?? 'Customer #'.$r['customer_id']) ?>"><?= htmlspecialchars($r['customer_name'] ?? 'Customer #'.$r['customer_id']) ?></td>
                  <td class="text-truncate-cell" title="<?= htmlspecialchars($r['contact_number'] ?? '') ?>"><?= htmlspecialchars($r['contact_number'] ?? '') ?></td>
                  <?php if ($isHeadTechnician): ?><td class="text-truncate-cell" title="<?= htmlspecialchars($r['province_address'] ?? 'Not set') ?>"><?= htmlspecialchars($r['province_address'] ?? 'Not set') ?></td><?php endif; ?>
                  <td class="text-truncate-cell" title="<?= htmlspecialchars($r['ebike_model']) ?>"><?= htmlspecialchars($r['ebike_model']) ?></td>
                  <td class="text-truncate-cell" title="<?= htmlspecialchars($r['issue_description']) ?>"><?= htmlspecialchars($r['issue_description']) ?></td>
                  <?php if ($isHeadTechnician): ?><td class="text-truncate-cell" title="<?= htmlspecialchars($r['technician_name'] ?? 'Unassigned') ?>"><?= htmlspecialchars($r['technician_name'] ?? 'Unassigned') ?></td><?php endif; ?>
                  <td><span class="badge bg-<?= $r['warranty_status'] === 'Valid' ? 'success' : 'secondary' ?>"><?= htmlspecialchars($r['warranty_status']) ?></span></td>
                  <td><?= (float)$r['amount'] > 0 ? 'PHP ' . number_format((float)$r['amount'], 2) : 'Pending' ?></td>
                  <td><span class="badge bg-<?php echo $r['repair_status']=='Completed'?'success':($r['repair_status']=='In Progress'?'primary':'warning'); ?>"><?= htmlspecialchars($r['repair_status']) ?></span></td>
                  <td>
                    <a href="repairdetails.php?id=<?= urlencode($r['repair_id']) ?>" class="btn btn-sm btn-outline-secondary w-100 mb-1">View</a>
                    <form method="post" action="technicianupdaterepair.php" class="repair-action-form">
                      <input type="hidden" name="repair_id" value="<?= htmlspecialchars($r['repair_id']) ?>">
                      <?php if ($isHeadTechnician): ?>
                        <select name="technician_id" class="form-select form-select-sm" aria-label="Assign technician">
                          <option value="">Unassigned</option>
                          <?php foreach ($assignableTechnicians as $tech): ?>
                            <option value="<?= htmlspecialchars((string)$tech['user_id']) ?>" <?= (int)($r['technician_id'] ?? 0) === (int)$tech['user_id'] ? 'selected' : '' ?>><?= htmlspecialchars($tech['name']) ?></option>
                          <?php endforeach; ?>
                        </select>
                      <?php endif; ?>
                      <?php if (!$isHeadTechnician): ?>
                        <select name="status" class="form-select form-select-sm">
                          <option <?= $r['repair_status'] === 'Pending' ? 'selected' : '' ?>>Pending</option>
                          <option <?= $r['repair_status'] === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                          <option <?= $r['repair_status'] === 'Completed' ? 'selected' : '' ?>>Completed</option>
                          <option <?= $r['repair_status'] === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        </select>
                      <?php endif; ?>
                      <button class="btn btn-sm btn-success">Save</button>
                    </form>
                  </td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr><td colspan="<?= $isHeadTechnician ? 13 : 11 ?>" class="text-center text-muted">No repair tickets found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
        </div></div></div>
      </div>

      <div class="tab-pane fade" id="schedule" role="tabpanel">
        <div class="card schedule-panel shadow-sm">
          <div class="schedule-header">
            <div>
              <div class="schedule-title">Technician Schedule</div>
              <p class="schedule-subtitle">Assigned and unassigned repairs by preferred service date.</p>
            </div>
            <div class="schedule-legend" aria-label="Calendar status legend">
              <span class="legend-pill"><span class="legend-dot pending"></span>Pending</span>
              <span class="legend-pill"><span class="legend-dot progress"></span>In Progress</span>
              <span class="legend-pill"><span class="legend-dot completed"></span>Completed</span>
              <span class="legend-pill"><span class="legend-dot cancelled"></span>Cancelled</span>
            </div>
          </div>
          <div class="calendar-shell">
            <div id="repairCalendar"></div>
          </div>
        </div>
      </div>

      <div class="tab-pane fade" id="warranty" role="tabpanel">
        <div class="card shadow-sm"><div class="card-body">
        <div class="d-flex justify-content-end mb-3">
          <input type="search" class="form-control" id="warrantySearch" style="max-width: 360px;" placeholder="Search warranty records">
        </div>
        <div class="table-responsive">
        <table class="table table-hover table-bordered mb-0 warranty-table" id="warrantyRecordsTable">
          <thead class="table-danger">
            <tr>
              <th>ID</th><th>Customer</th><th>E-Bike</th><th>Coverage</th><th>Status</th><th>Claim Date</th><th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($warranties && $warranties->num_rows > 0): ?>
              <?php while($w = $warranties->fetch_assoc()): ?>
                <tr>
                  <td><?= htmlspecialchars($w['warranty_id']) ?></td>
                  <td><?= htmlspecialchars($w['customer_name'] ?? 'Customer #'.$w['customer_id']) ?></td>
                  <td><?= htmlspecialchars($w['ebike_model']) ?></td>
                  <td><?= htmlspecialchars($w['warranty_period']) ?> months</td>
                  <td><span class="badge bg-<?php echo $w['warranty_status']=='Active'?'success':'secondary'; ?>"><?= htmlspecialchars($w['warranty_status']) ?></span></td>
                  <td><?= htmlspecialchars($w['claim_date'] ?? '') ?></td>
                  <td><a class="btn btn-sm btn-outline-danger" href="technicianwarrantyverify.php?id=<?= urlencode($w['warranty_id']) ?>">Verify</a></td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr><td colspan="7" class="text-center text-muted">No warranty records found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
        </div></div></div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
  <script>
    const repairEvents = <?= json_encode($calendarEvents, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    let repairCalendar;

    function escapeHtml(value) {
      return String(value ?? '').replace(/[&<>"']/g, (char) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
      }[char]));
    }

    document.addEventListener('DOMContentLoaded', () => {
      const calendarEl = document.getElementById('repairCalendar');
      repairCalendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        height: 'auto',
        events: repairEvents,
        eventDisplay: 'block',
        dayMaxEvents: 3,
        headerToolbar: {
          left: 'prev,next today',
          center: 'title',
          right: 'dayGridMonth,listWeek'
        },
        eventContent: (info) => {
          const props = info.event.extendedProps;
          return {
            html: `
              <div class="calendar-event-card">
                <div class="calendar-event-time">${escapeHtml(props.timeSlot)}</div>
                <div class="calendar-event-title">${escapeHtml(props.customer)} - ${escapeHtml(props.model)}</div>
                <div class="calendar-event-status">${escapeHtml(props.status)}</div>
              </div>
            `
          };
        },
        eventDidMount: (info) => {
          const props = info.event.extendedProps;
          info.el.title = `${props.timeSlot} | ${props.customer} | ${props.model}${props.issue ? ' | ' + props.issue : ''}`;
        },
        eventClick: (info) => {
          if (info.event.url) {
            info.jsEvent.preventDefault();
            window.location.href = info.event.url;
          }
        },
      });
      repairCalendar.render();

      document.getElementById('schedule-tab')?.addEventListener('shown.bs.tab', () => {
        repairCalendar.updateSize();
      });

      function bindTableSearch(inputId, tableId) {
        const input = document.getElementById(inputId);
        const table = document.getElementById(tableId);
        if (!input || !table) return;
        input.addEventListener('input', () => {
          const query = input.value.trim().toLowerCase();
          table.querySelectorAll('tbody tr').forEach((row) => {
            if (row.cells.length <= 1) return;
            row.style.display = row.textContent.toLowerCase().includes(query) ? '' : 'none';
          });
        });
      }

      bindTableSearch('repairSearch', 'repairTicketsTable');
      bindTableSearch('warrantySearch', 'warrantyRecordsTable');
    });
  </script>
</body>
</html>
