<?php
session_start();
include("db.php");
include_once("schema_helpers.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Technician') {
    header("Location: login.php");
    exit();
}

ensureRepairAutomationSchema($conn);

$user_id = intval($_SESSION['user_id']);
$repairs = $conn->query("
  SELECT r.repair_id, r.created_at, r.customer_id, u.name AS customer_name, u.contact_number,
         r.ebike_model, r.issue_description, r.warranty_status, r.amount, r.repair_status,
         rb.preferred_date, rb.preferred_time
  FROM repairs r
  LEFT JOIN repair_bookings rb ON rb.booking_id = r.booking_id
  LEFT JOIN users u ON u.user_id = r.customer_id
  WHERE r.technician_id = $user_id OR r.technician_id IS NULL
  ORDER BY r.created_at DESC
");

$scheduleStmt = $conn->prepare("
  SELECT r.repair_id, r.repair_status, r.ebike_model, u.name AS customer_name,
         rb.preferred_date, rb.preferred_time, r.issue_description
  FROM repairs r
  INNER JOIN repair_bookings rb ON rb.booking_id = r.booking_id
  LEFT JOIN users u ON u.user_id = r.customer_id
  WHERE (r.technician_id = ? OR r.technician_id IS NULL)
    AND rb.preferred_date IS NOT NULL
  ORDER BY rb.preferred_date, rb.preferred_time
");
$scheduleStmt->bind_param("i", $user_id);
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Manage Repair & Warranty - FixTrack</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background: #f8f9fa; font-family: 'Segoe UI', sans-serif; }
    .technician-workspace { margin: 30px auto 0; max-width: min(1240px, calc(100% - 24px)); animation: fadeInUp 0.6s ease; }
    @keyframes fadeInUp { from { opacity:0; transform:translateY(20px);} to { opacity:1; transform:translateY(0);} }
    .card { border-radius: 8px; }
    .badge { font-size: 0.9em; }
    #repairCalendar { min-height: 620px; }
    .schedule-panel { border: 0; overflow: hidden; }
    .schedule-header {
      align-items: center;
      background: linear-gradient(135deg, #842029, #dc3545);
      color: #fff;
      display: flex;
      flex-wrap: wrap;
      gap: 16px;
      justify-content: space-between;
      padding: 20px 22px;
    }
    .schedule-title { font-size: 1.1rem; font-weight: 800; margin-bottom: 2px; }
    .schedule-subtitle { color: rgba(255,255,255,.78); font-size: .86rem; margin-bottom: 0; }
    .schedule-legend { display: flex; flex-wrap: wrap; gap: 8px; }
    .legend-pill {
      align-items: center;
      background: rgba(255,255,255,.16);
      border: 1px solid rgba(255,255,255,.2);
      border-radius: 999px;
      display: inline-flex;
      font-size: .78rem;
      font-weight: 700;
      gap: 7px;
      padding: 7px 10px;
    }
    .legend-dot { border-radius: 999px; display: inline-block; height: 9px; width: 9px; }
    .legend-dot.pending { background: #f59f00; }
    .legend-dot.progress { background: #0d6efd; }
    .legend-dot.completed { background: #198754; }
    .legend-dot.cancelled { background: #6c757d; }
    .calendar-shell { background: #fff; padding: 18px; }
    .fc { color: #1f2937; }
    .fc .fc-toolbar { align-items: center; gap: 12px; margin-bottom: 18px; }
    .fc .fc-toolbar-title { font-size: 1.25rem; font-weight: 800; }
    .fc .fc-button {
      border-radius: 8px !important;
      box-shadow: none !important;
      font-size: .82rem;
      font-weight: 700;
      padding: .42rem .7rem;
      text-transform: capitalize;
    }
    .fc .fc-button-primary { background: #dc3545; border-color: #dc3545; }
    .fc .fc-button-primary:hover, .fc .fc-button-primary:focus { background: #a61e2e; border-color: #a61e2e; }
    .fc .fc-button-primary:disabled { background: #f1aeb5; border-color: #f1aeb5; }
    .fc-theme-standard .fc-scrollgrid,
    .fc-theme-standard td,
    .fc-theme-standard th { border-color: #edf0f2; }
    .fc .fc-col-header-cell {
      background: #fff1f2;
      color: #842029;
      font-size: .78rem;
      padding: 8px 0;
      text-transform: uppercase;
    }
    .fc .fc-daygrid-day { background: #fff; }
    .fc .fc-daygrid-day-frame { min-height: 112px; padding: 5px; }
    .fc .fc-day-today { background: #fff8e6 !important; }
    .fc .fc-daygrid-day-number {
      color: #495057;
      font-size: .82rem;
      font-weight: 800;
      padding: 5px 7px;
    }
    .fc .fc-day-other .fc-daygrid-day-number { color: #adb5bd; }
    .fc .fc-event {
      border: 0;
      border-radius: 8px;
      box-shadow: 0 8px 16px rgba(31, 41, 55, .08);
      margin: 2px 4px;
      overflow: hidden;
    }
    .calendar-event-card {
      border-left: 4px solid currentColor;
      display: grid;
      gap: 2px;
      line-height: 1.25;
      padding: 6px 7px;
    }
    .calendar-event-time { font-size: .68rem; font-weight: 800; text-transform: uppercase; }
    .calendar-event-title {
      color: #1f2937;
      font-size: .75rem;
      font-weight: 800;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
    .calendar-event-status { color: #6c757d; font-size: .68rem; font-weight: 700; }
    .repair-event-pending { background: #fff8e1 !important; color: #f59f00 !important; }
    .repair-event-in-progress { background: #e7f1ff !important; color: #0d6efd !important; }
    .repair-event-completed { background: #eaf7ef !important; color: #198754 !important; }
    .repair-event-cancelled { background: #f1f3f5 !important; color: #6c757d !important; }
    .repair-ticket-table { table-layout: fixed; font-size: .76rem; width: 100%; }
    .repair-ticket-table th,
    .repair-ticket-table td { padding: .45rem .35rem; vertical-align: middle; }
    .repair-ticket-table .col-id { width: 42px; }
    .repair-ticket-table .col-date { width: 78px; }
    .repair-ticket-table .col-schedule { width: 82px; }
    .repair-ticket-table .col-customer { width: 106px; }
    .repair-ticket-table .col-contact { width: 92px; }
    .repair-ticket-table .col-bike { width: 108px; }
    .repair-ticket-table .col-issue { width: 160px; }
    .repair-ticket-table .col-warranty { width: 78px; }
    .repair-ticket-table .col-cost { width: 88px; }
    .repair-ticket-table .col-status { width: 92px; }
    .repair-ticket-table .col-action { width: 132px; }
    .repair-ticket-table .text-truncate-cell {
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
    .repair-action-form {
      display: grid;
      gap: 4px;
    }
    .repair-action-form .form-control,
    .repair-action-form .form-select,
    .repair-action-form .btn { min-height: 32px; }
    .repair-action-form .form-control,
    .repair-action-form .form-select { font-size: .74rem; padding: .2rem .35rem; }
    .repair-action-form .btn,
    .repair-ticket-table .btn { font-size: .74rem; padding: .2rem .4rem; }
    .table-responsive.repair-table-wrap { overflow-x: auto; }
    .warranty-table { table-layout: fixed; font-size: .86rem; width: 100%; }
    .warranty-table th,
    .warranty-table td { padding: .55rem .5rem; vertical-align: middle; }
  </style>
</head>
<body>
  <?php include("navbartechnician.php"); ?>

  <div class="technician-workspace">
    <div class="page-hero">
      <h3 class="mb-1">Repair & Warranty Management</h3>
      <p>Manage assigned repair tickets, set repair costs, and update warranty records.</p>
    </div>

    <?php if (isset($_GET['updated'])): ?>
      <div class="alert alert-success">Repair status updated.</div>
    <?php elseif (isset($_GET['error'])): ?>
      <div class="alert alert-danger">Unable to update repair status.</div>
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
        <div class="card shadow-sm"><div class="card-body p-2 p-lg-3"><div class="table-responsive repair-table-wrap">
        <table class="table table-hover table-bordered mb-0 repair-ticket-table">
          <thead class="table-danger">
            <tr>
              <th class="col-id">ID</th>
              <th class="col-date">Date</th>
              <th class="col-schedule">Schedule</th>
              <th class="col-customer">Customer</th>
              <th class="col-contact">Contact</th>
              <th class="col-bike">E-Bike</th>
              <th class="col-issue">Issue</th>
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
                  <td class="text-truncate-cell" title="<?= htmlspecialchars($r['ebike_model']) ?>"><?= htmlspecialchars($r['ebike_model']) ?></td>
                  <td class="text-truncate-cell" title="<?= htmlspecialchars($r['issue_description']) ?>"><?= htmlspecialchars($r['issue_description']) ?></td>
                  <td><span class="badge bg-<?= $r['warranty_status'] === 'Valid' ? 'success' : 'secondary' ?>"><?= htmlspecialchars($r['warranty_status']) ?></span></td>
                  <td><?= (float)$r['amount'] > 0 ? 'PHP ' . number_format((float)$r['amount'], 2) : 'Pending' ?></td>
                  <td><span class="badge bg-<?php echo $r['repair_status']=='Completed'?'success':($r['repair_status']=='In Progress'?'primary':'warning'); ?>"><?= htmlspecialchars($r['repair_status']) ?></span></td>
                  <td>
                    <a href="repairdetails.php?id=<?= urlencode($r['repair_id']) ?>" class="btn btn-sm btn-outline-secondary w-100 mb-1">View</a>
                    <form method="post" action="technicianupdaterepair.php" class="repair-action-form">
                      <input type="hidden" name="repair_id" value="<?= htmlspecialchars($r['repair_id']) ?>">
                      <input type="number" class="form-control form-control-sm" name="amount" min="0" step="0.01" value="<?= htmlspecialchars($r['amount']) ?>" aria-label="Repair cost">
                      <select name="status" class="form-select form-select-sm">
                        <option <?= $r['repair_status'] === 'Pending' ? 'selected' : '' ?>>Pending</option>
                        <option <?= $r['repair_status'] === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                        <option <?= $r['repair_status'] === 'Completed' ? 'selected' : '' ?>>Completed</option>
                        <option <?= $r['repair_status'] === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                      </select>
                      <button class="btn btn-sm btn-danger">Save</button>
                    </form>
                  </td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr><td colspan="11" class="text-center text-muted">No repair tickets found.</td></tr>
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
        <div class="card shadow-sm"><div class="card-body"><div class="table-responsive">
        <table class="table table-hover table-bordered mb-0 warranty-table">
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
    });
  </script>
</body>
</html>
