<?php
session_start();
include("db.php");

// Guard: only allow Customers
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Customer') {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Repair Request Form - Red Star</title>
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
    <p class="text-muted">Admin and technicians will use the information below to review your request.</p>

    <form action="process_repair.php" method="POST" enctype="multipart/form-data">
      <!-- Customer Info -->
      <div class="row mb-3">
        <div class="col-md-6">
          <label class="form-label">Customer Name *</label>
          <input type="text" class="form-control" name="customer_name" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Phone Number *</label>
          <input type="text" class="form-control" name="phone" required>
        </div>
      </div>
      <div class="mb-3">
        <label class="form-label">Address *</label>
        <input type="text" class="form-control" name="address" required>
      </div>

      <!-- Upload & Preferences -->
      <div class="row mb-3">
        <div class="col-md-6">
          <label class="form-label">Upload Customer Valid ID *</label>
          <input type="file" class="form-control" name="valid_id" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Preferred Service Date *</label>
          <input type="date" class="form-control" name="preferred_date" required>
        </div>
      </div>

      <!-- E-Bike Details -->
      <div class="mb-3">
        <label class="form-label">Select E-Bike Type *</label>
        <select class="form-select" name="ebike_type" required>
          <option value="">Choose...</option>
          <option value="Mountain">Mountain</option>
          <option value="City">City</option>
          <option value="Folding">Folding</option>
        </select>
      </div>
      <div class="row mb-3">
        <div class="col-md-4">
          <label class="form-label">Brand *</label>
          <input type="text" class="form-control" name="brand" required>
        </div>
        <div class="col-md-4">
          <label class="form-label">Model *</label>
          <input type="text" class="form-control" name="model" required>
        </div>
        <div class="col-md-4">
          <label class="form-label">Serial Number *</label>
          <input type="text" class="form-control" name="serial_number" required>
        </div>
      </div>

      <!-- Issue & Service Details -->
      <div class="mb-3">
        <label class="form-label">Issue Description *</label>
        <textarea class="form-control" name="issue_description" rows="3" placeholder="Describe the E-Bike issue..." required></textarea>
      </div>
      <div class="row mb-3">
        <div class="col-md-4">
          <label class="form-label">Service Type *</label>
          <select class="form-select" name="service_type" required>
            <option value="Maintenance">Maintenance</option>
            <option value="Repair">Repair</option>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">Warranty Request *</label>
          <select class="form-select" name="warranty_request" required>
            <option value="No Warranty">No Warranty</option>
            <option value="Under Warranty">Under Warranty</option>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">Available Technician *</label>
          <select class="form-select" name="technician" required>
            <option value="">Choose technician by expertise</option>
            <option value="Tech1">Tech1</option>
            <option value="Tech2">Tech2</option>
          </select>
        </div>
      </div>
      <div class="mb-3">
        <label class="form-label">Time Slot *</label>
        <select class="form-select" name="time_slot" required>
          <option value="">Choose a time...</option>
          <option value="Morning">Morning</option>
          <option value="Afternoon">Afternoon</option>
          <option value="Evening">Evening</option>
        </select>
      </div>

      <!-- Proof Documents -->
      <div class="row mb-3">
        <div class="col-md-6">
          <label class="form-label">Proof of Receipt</label>
          <input type="file" class="form-control" name="receipt">
        </div>
        <div class="col-md-6">
          <label class="form-label">Picture of E-Bike / Damage</label>
          <input type="file" class="form-control" name="damage_photo">
        </div>
      </div>

      <!-- Agreement -->
      <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" name="consent" id="consent" required>
        <label class="form-check-label" for="consent">
          I agree that Red Star E-bike and Parts may collect and process my personal information, valid ID, receipt proof, and E-Bike photos for repair booking, warranty verification, service updates, and customer support in accordance with the Data Privacy Act of 2012.
        </label>
      </div>

      <div class="d-flex justify-content-between">
        <button type="submit" class="btn btn-danger">Submit Repair Request</button>
        <a href="customerdashboard.php" class="btn btn-secondary">Cancel</a>
      </div>
    </form>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Optional: disable submit until consent is checked
    const consent = document.getElementById('consent');
    const submitBtn = document.querySelector('button[type="submit"]');
    consent.addEventListener('change', () => {
      submitBtn.disabled = !consent.checked;
    });
    submitBtn.disabled = true;
  </script>
</body>
</html>
