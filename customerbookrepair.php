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

$customer_id = intval($_SESSION['user_id']);

function resolveWarrantyStatus(array $bike): string
{
    if ($bike['warranty_status'] !== 'Active') {
        return 'Invalid';
    }

    $expiresAt = strtotime($bike['purchase_date'] . ' +' . intval($bike['warranty_period']) . ' months');
    return $expiresAt !== false && $expiresAt >= strtotime(date('Y-m-d')) ? 'Valid' : 'Invalid';
}

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
    $bike['computed_warranty_status'] = resolveWarrantyStatus($bike);
    $ownedEbikes[] = $bike;
}

$technicians = $conn->query("SELECT user_id, name FROM users WHERE role = 'Technician' ORDER BY name");
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
    h3 { color: #dc3545; font-weight: bold; margin-bottom: 20px; }
    .form-label { font-weight: 500; margin-bottom: 4px; }
    .summary-box { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 16px; }
    .summary-label { color: #6c757d; font-size: 14px; margin-bottom: 4px; }
    .summary-value { font-weight: 700; margin-bottom: 0; }
    .btn-danger { transition: transform 0.2s ease, box-shadow 0.2s ease; }
    .btn-danger:hover {
      transform: translateY(-2px);
      box-shadow: 0px 4px 12px rgba(220,53,69,0.4);
    }
    input:focus, select:focus, textarea:focus {
      box-shadow: 0 0 6px rgba(220,53,69,0.5);
      border-color: #dc3545;
    }
  </style>
</head>
<body>
  <?php include("navbarcustomer.php"); ?>

  <div class="form-container">
    <h3>Repair Request Form</h3>
    <p class="text-muted">Your customer details and warranty will be checked automatically from your account records. The technician will set the repair cost after assessment.</p>

    <?php if (!$ownedEbikes): ?>
      <div class="alert alert-warning">
        No purchased e-bike or warranty record is linked to your account yet. Please contact support staff before booking a repair.
      </div>
      <a href="customerdashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    <?php else: ?>
    <form action="process_repair.php" method="POST">
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
          <input type="date" class="form-control" name="preferred_date" required>
        </div>
      </div>
      <div class="row mb-3">
        <div class="col-md-6">
          <label class="form-label">Available Technician *</label>
          <select class="form-select" name="technician_id" required>
            <option value="">Choose a technician</option>
            <?php if ($technicians): while ($technician = $technicians->fetch_assoc()): ?>
              <option value="<?= htmlspecialchars((string)$technician['user_id']) ?>"><?= htmlspecialchars($technician['name']) ?></option>
            <?php endwhile; endif; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">Time Slot *</label>
          <select class="form-select" name="time_slot" required>
            <option value="">Choose a time...</option>
            <option value="Morning">Morning</option>
            <option value="Afternoon">Afternoon</option>
            <option value="Evening">Evening</option>
          </select>
        </div>
      </div>

      <!-- Agreement -->
      <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" name="consent" id="consent" required>
        <label class="form-check-label" for="consent">
          I agree that FixTrack E-bike Service may collect and process my personal information, valid ID, receipt proof, and E-Bike photos for repair booking, warranty verification, service updates, and customer support in accordance with the Data Privacy Act of 2012.
        </label>
      </div>

      <div class="d-flex justify-content-between">
        <button type="submit" class="btn btn-danger">Submit Repair Request</button>
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
  </script>
</body>
</html>
