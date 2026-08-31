<?php
session_start();
include("db.php");

// Guard: only allow Customers
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Customer') {
    header("Location: login.php");
    exit();
}

$customer_id = $_SESSION['user_id'];

// Example data (replace with queries from your DB)
$ebikesOwned = 0;
$activeRepairs = 0;
$completedRepairs = 0;
$loyaltyPoints = 150;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Customer Dashboard - Red Star</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background: #f8f9fa; font-family: 'Segoe UI', sans-serif; }
    .dashboard-container { margin: 30px auto; max-width: 1100px; }
    .card-summary { text-align: center; padding: 20px; border-radius: 12px; }
    .card-summary h3 { margin: 0; font-size: 28px; font-weight: bold; }
    .card-summary p { margin: 0; color: #666; }
    .section-title { margin-top: 30px; margin-bottom: 15px; font-weight: bold; }
    .map-container { height: 300px; border-radius: 12px; overflow: hidden; }
  </style>
</head>
<body>
  <?php include("navbarcustomer.php"); ?>

  <div class="dashboard-container">
    <h2 class="mb-4">Welcome, Red Star Customer</h2>

    <!-- Summary Cards -->
    <div class="row g-3">
      <div class="col-md-3">
        <div class="bg-light card-summary shadow-sm">
          <h3><?= $ebikesOwned ?></h3>
          <p>E-bikes Owned</p>
        </div>
      </div>
      <div class="col-md-3">
        <div class="bg-light card-summary shadow-sm">
          <h3><?= $activeRepairs ?></h3>
          <p>Active Repairs</p>
        </div>
      </div>
      <div class="col-md-3">
        <div class="bg-light card-summary shadow-sm">
          <h3><?= $completedRepairs ?></h3>
          <p>Completed Repairs</p>
        </div>
      </div>
      <div class="col-md-3">
        <div class="bg-light card-summary shadow-sm">
          <h3><?= $loyaltyPoints ?></h3>
          <p>Loyalty Points</p>
        </div>
      </div>
    </div>

    <!-- My E-bikes -->
    <h4 class="section-title">My E-bikes</h4>
    <div class="bg-white p-4 rounded shadow-sm mb-4">
      <p class="text-muted">E-bikes registered to your account</p>
      <p>No e-bikes registered yet.</p>
      <a href="customerbookrepair.php" class="btn btn-danger">Book Repair</a>
    </div>

    <!-- Warranty Coverage -->
    <h4 class="section-title">Warranty Coverage</h4>
    <div class="bg-white p-4 rounded shadow-sm mb-4">
      <p class="text-muted">Current warranty status</p>
      <p>No warranty coverage found.</p>
    </div>

    <!-- Visit Our Shop -->
    <h4 class="section-title">Visit Our Shop</h4>
    <div class="bg-white p-4 rounded shadow-sm mb-4">
      <p class="text-muted">Find us at our service center location.</p>
      <div class="map-container mb-3">
        <iframe 
          src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3876.123456789!2d121.123456!3d14.123456!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3397d123456789%3A0xabcdef123456789!2sSanta%20Rosa%20Philippines!5e0!3m2!1sen!2sph!4v1661234567890"
          width="100%" height="100%" style="border:0;" allowfullscreen="" loading="lazy">
        </iframe>
      </div>
      <h5>Red Star E-bike and Parts</h5>
      <p><strong>Address:</strong> </p>
      <p><strong>Phone:</strong> </p>
      <p><strong>Email:</strong> </p>
      <p><strong>Business Hours:</strong> </p>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
