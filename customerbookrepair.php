<?php
session_start();
include("db.php");
include_once("schema_helpers.php");

// Guard: only allow Customers
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Customer') {
    header("Location: login.php");
    exit();
}

ensureRepairAutomationSchema($conn);
syncWarrantyStatuses($conn);
syncRepairWarrantyCoverage($conn);

$customer_id = intval($_SESSION['user_id']);

$customerStmt = $conn->prepare("SELECT name, contact_number, province_address FROM users WHERE user_id = ? LIMIT 1");
$customerStmt->bind_param("i", $customer_id);
$customerStmt->execute();
$customer = $customerStmt->get_result()->fetch_assoc();

$bikeStmt = $conn->prepare("
  SELECT warranty_id, ebike_model, purchase_date, warranty_period, warranty_status
  FROM warranty_records
  WHERE customer_id = ?
  ORDER BY created_at DESC
");
$bikeStmt->bind_param("i", $customer_id);
$bikeStmt->execute();
$bikeResult = $bikeStmt->get_result();
$ownedEbikes = [];
while ($bike = $bikeResult->fetch_assoc()) {
    $bike['computed_warranty_status'] = warrantyCoverageStatus($bike);
    $ownedEbikes[] = $bike;
}

$slotCapacity = 3;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Repair Request Form - FixTrack</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background: #f8f9fa; font-family: 'Segoe UI', sans-serif; }
    .form-container {
      max-width: 800px;
      margin: 40px auto;
      padding: 30px;
      background: #fff;
      border-radius: 12px;
      box-shadow: 0px 6px 20px rgba(0,0,0,0.15);
      animation: fadeInUp 1s ease;
    }
    @keyframes fadeInUp {
      from { opacity: 0; transform: translateY(30px); }
      to { opacity: 1; transform: translateY(0); }
    }
    h3 { color: #198754; font-weight: bold; margin-bottom: 20px; }
    .form-label { font-weight: 500; margin-bottom: 4px; }
    .summary-box { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 16px; }
    .summary-label { color: #6c757d; font-size: 14px; margin-bottom: 4px; }
    .summary-value { font-weight: 700; margin-bottom: 0; }
    .btn-success { transition: transform 0.2s ease, box-shadow 0.2s ease; }
    .btn-success:hover {
      transform: translateY(-2px);
      box-shadow: 0px 4px 12px rgba(25,135,84,0.4);
    }
    input:focus, select:focus, textarea:focus {
      box-shadow: 0 0 6px rgba(25,135,84,0.5);
      border-color: #198754;
    }
    .availability-calendar { border: 1px solid #dee2e6; border-radius: 8px; overflow: hidden; }
    .calendar-grid { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); }
    .calendar-cell { align-items: center; background: #fff; border-right: 1px solid #edf0f2; border-top: 1px solid #edf0f2; display: flex; font-size: .82rem; font-weight: 700; justify-content: center; min-height: 42px; }
    .calendar-cell:nth-child(7n) { border-right: 0; }
    .calendar-head { background: #f3f5f3; color: #374151; font-size: .72rem; min-height: 34px; text-transform: uppercase; }
    .calendar-muted { color: #adb5bd; }
    .calendar-full { background: #198754; color: #fff; }
    .calendar-selected { outline: 3px solid rgba(25,135,84,.35); outline-offset: -3px; }
  </style>
</head>
<body>
  <?php include("navbarcustomer.php"); ?>

  <div class="form-container">
    <h3>Repair Request Form</h3>
    <p class="text-muted">Your customer details and warranty will be checked automatically from your account records. The technician will set the repair cost after assessment.</p>

    <?php if (($_GET['error'] ?? '') === 'slot_full'): ?>
      <div class="alert alert-warning d-flex align-items-center gap-2" role="alert">
        <span class="fw-bold">Schedule full.</span>
        <span><?= htmlspecialchars($_GET['slot'] ?? 'Selected slot') ?> on <?= htmlspecialchars($_GET['date'] ?? 'that date') ?> already reached the 3-booking limit. Please choose another schedule.</span>
      </div>
    <?php elseif (($_GET['error'] ?? '') === 'incomplete'): ?>
      <div class="alert alert-danger">Please complete all required repair booking fields.</div>
    <?php elseif (($_GET['error'] ?? '') === 'past_date'): ?>
      <div class="alert alert-danger">Please choose today or a future service date.</div>
    <?php elseif (($_GET['error'] ?? '') === 'upload'): ?>
      <div class="alert alert-danger">The proof file could not be uploaded. Please use an image, PDF, DOC, or DOCX file up to 5MB.</div>
    <?php elseif (($_GET['error'] ?? '') === 'invalid_ebike'): ?>
      <div class="alert alert-danger">Please choose an e-bike linked to your account.</div>
    <?php endif; ?>

    <?php if (!$ownedEbikes): ?>
      <div class="alert alert-warning">
        No purchased e-bike or warranty record is linked to your account yet. Please contact support staff before booking a repair.
      </div>
      <a href="customerdashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    <?php else: ?>
    <form action="process_repair.php" method="POST" enctype="multipart/form-data">
      <div class="summary-box mb-3">
        <div class="row g-3">
          <div class="col-md-4">
            <div class="summary-label">Customer</div>
            <p class="summary-value"><?= htmlspecialchars($customer['name'] ?? 'Customer') ?></p>
          </div>
          <div class="col-md-4">
            <div class="summary-label">Phone</div>
            <p class="summary-value"><?= htmlspecialchars($customer['contact_number'] ?? 'Not set') ?></p>
          </div>
          <div class="col-md-4">
            <div class="summary-label">Address</div>
            <p class="summary-value"><?= htmlspecialchars($customer['province_address'] ?? 'Not set') ?></p>
          </div>
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label">Select Your E-Bike *</label>
        <select class="form-select" name="warranty_id" id="warranty_id" required>
          <option value="">Choose your e-bike...</option>
          <?php foreach ($ownedEbikes as $bike): ?>
            <option value="<?= htmlspecialchars($bike['warranty_id']) ?>">
              <?= htmlspecialchars($bike['ebike_model']) ?> - Warranty <?= htmlspecialchars($bike['computed_warranty_status']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="mb-3">
        <div class="summary-box">
          <div class="summary-label">Automatic Warranty Verification</div>
          <p class="summary-value" id="warrantyStatus">Select an e-bike</p>
        </div>
      </div>

      <!-- Service Details -->
      <div class="mb-3">
        <label class="form-label">Issue Description *</label>
        <textarea class="form-control" name="issue_description" id="issue_description" rows="4" placeholder="Describe the E-Bike issue..." required></textarea>
      </div>
      <div class="mb-3">
        <label class="form-label">Proof Image or File</label>
        <input type="file" class="form-control" name="proof_file" accept="image/*,.pdf,.doc,.docx">
        <div class="form-text">Upload a photo or document that shows the issue. Accepted: image, PDF, DOC, DOCX.</div>
      </div>
      <div class="row mb-3">
        <div class="col-md-6">
          <label class="form-label">Service Type *</label>
          <select class="form-select" name="service_type" id="service_type" required>
            <option value="Repair">Repair</option>
            <option value="Maintenance">Maintenance</option>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">Preferred Service Date *</label>
          <input type="date" class="form-control" name="preferred_date" id="preferred_date" min="<?= htmlspecialchars(date('Y-m-d')) ?>" required>
        </div>
      </div>
      <div class="row mb-3">
        <div class="col-md-6">
          <label class="form-label">Time Slot *</label>
          <select class="form-select" name="time_slot" id="time_slot" required>
            <option value="">Choose a time...</option>
            <option value="Morning">Morning</option>
            <option value="Afternoon">Afternoon</option>
            <option value="Evening">Evening</option>
          </select>
        </div>
      </div>
      <div class="mb-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <label class="form-label mb-0">Date Availability</label>
          <span class="small"><span class="badge bg-danger">Red</span> Not available</span>
        </div>
        <div class="availability-calendar">
          <div class="calendar-grid" id="availabilityCalendar"></div>
        </div>
        <div class="form-text">Blank dates are available. A date turns red only when Morning, Afternoon, and Evening are all fully booked. Full time slots are disabled after you pick a date.</div>
      </div>

      <!-- Agreement -->
      <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" name="consent" id="consent" required>
        <label class="form-check-label" for="consent">
          I agree that FixTrack E-bike Service may collect and process my personal information, valid ID, receipt proof, and E-Bike photos for repair booking, warranty verification, service updates, and customer support in accordance with the Data Privacy Act of 2012.
        </label>
      </div>

      <div class="d-flex justify-content-between">
        <button type="submit" class="btn btn-success">Submit Repair Request</button>
        <a href="customerdashboard.php" class="btn btn-secondary">Cancel</a>
      </div>
    </form>
    <?php endif; ?>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    const ownedEbikes = <?= json_encode($ownedEbikes, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    const warrantySelect = document.getElementById('warranty_id');
    const warrantyStatus = document.getElementById('warrantyStatus');
    const preferredDate = document.getElementById('preferred_date');
    const timeSlot = document.getElementById('time_slot');
    const availabilityCalendar = document.getElementById('availabilityCalendar');
    let availabilityPayload = { full_dates: [], full_slots: {}, slot_counts: {}, slot_capacity: <?= (int)$slotCapacity ?> };

    function refreshWarrantyStatus() {
      if (!warrantySelect) {
        return;
      }

      const selected = ownedEbikes.find((bike) => String(bike.warranty_id) === warrantySelect.value);
      const state = selected ? selected.computed_warranty_status : 'Select an e-bike';

      warrantyStatus.textContent = state;
      warrantyStatus.className = 'summary-value ' + (state === 'Valid' ? 'text-success' : (state === 'Invalid' ? 'text-danger' : ''));
    }

    [warrantySelect].forEach((element) => {
      if (element) {
        element.addEventListener('change', refreshWarrantyStatus);
      }
    });
    refreshWarrantyStatus();

    // Optional: disable submit until consent is checked
    const consent = document.getElementById('consent');
    const submitBtn = document.querySelector('button[type="submit"]');
    if (consent && submitBtn) {
      consent.addEventListener('change', () => {
        submitBtn.disabled = !consent.checked;
      });
      submitBtn.disabled = true;
    }

    async function loadAvailability() {
      if (!availabilityCalendar) return;
      const visibleDate = preferredDate?.value ? new Date(preferredDate.value + 'T00:00:00') : new Date();
      const year = visibleDate.getFullYear();
      const month = visibleDate.getMonth() + 1;
      const response = await fetch(`repair_availability_api.php?year=${year}&month=${month}`, { cache: 'no-store' });
      if (!response.ok) return;
      availabilityPayload = await response.json();
      renderAvailability(year, month, availabilityPayload.full_dates || []);
      refreshTimeSlotAvailability();
    }

    function renderAvailability(year, month, fullDates) {
      const fullSet = new Set(fullDates);
      const selected = preferredDate?.value || '';
      const first = new Date(year, month - 1, 1);
      const days = new Date(year, month, 0).getDate();
      const leading = first.getDay();
      const labels = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
      availabilityCalendar.innerHTML = labels.map(label => `<div class="calendar-cell calendar-head">${label}</div>`).join('');
      for (let i = 0; i < leading; i++) {
        availabilityCalendar.insertAdjacentHTML('beforeend', '<div class="calendar-cell calendar-muted"></div>');
      }
      for (let day = 1; day <= days; day++) {
        const value = `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        const classes = ['calendar-cell'];
        if (fullSet.has(value)) classes.push('calendar-full');
        if (selected === value) classes.push('calendar-selected');
        availabilityCalendar.insertAdjacentHTML('beforeend', `<button type="button" class="${classes.join(' ')}" data-date="${value}" ${fullSet.has(value) ? 'disabled title="Not available"' : ''}>${day}</button>`);
      }
      availabilityCalendar.querySelectorAll('[data-date]').forEach((button) => {
        button.addEventListener('click', () => {
          preferredDate.value = button.dataset.date;
          renderAvailability(year, month, fullDates);
          refreshTimeSlotAvailability();
        });
      });
    }

    function refreshTimeSlotAvailability() {
      if (!timeSlot || !preferredDate?.value) return;
      const fullSlots = new Set(availabilityPayload.full_slots?.[preferredDate.value] || []);
      const slotCounts = availabilityPayload.slot_counts?.[preferredDate.value] || {};
      const capacity = Number(availabilityPayload.slot_capacity || <?= (int)$slotCapacity ?>);

      [...timeSlot.options].forEach((option) => {
        if (!option.value) return;
        const isFull = fullSlots.has(option.value);
        const count = Number(slotCounts[option.value] || 0);
        option.disabled = isFull;
        option.textContent = isFull ? `${option.value} - Full` : `${option.value} (${Math.max(0, capacity - count)} left)`;
      });

      if (timeSlot.selectedOptions[0]?.disabled) {
        timeSlot.value = '';
      }
    }

    preferredDate?.addEventListener('change', loadAvailability);
    loadAvailability();
  </script>
</body>
</html>
