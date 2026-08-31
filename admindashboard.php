<?php
session_start();
include("db.php");

// Guard: only allow Admins
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

// Stats
$new_bookings = $conn->query("SELECT COUNT(*) AS total FROM repair_bookings WHERE booking_status='Pending'")->fetch_assoc()['total'];
$active_repairs = $conn->query("SELECT COUNT(*) AS total FROM repair_bookings WHERE booking_status='In Progress'")->fetch_assoc()['total'];
$warranty_claims = $conn->query("SELECT COUNT(*) AS total FROM warranty_records")->fetch_assoc()['total'];
$total_customers = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role='Customer'")->fetch_assoc()['total'];

// For charts
$pending = $conn->query("SELECT COUNT(*) AS total FROM repair_bookings WHERE booking_status='Pending'")->fetch_assoc()['total'];
$inprogress = $conn->query("SELECT COUNT(*) AS total FROM repair_bookings WHERE booking_status='In Progress'")->fetch_assoc()['total'];
$completed = $conn->query("SELECT COUNT(*) AS total FROM repair_bookings WHERE booking_status='Completed'")->fetch_assoc()['total'];

$valid = $conn->query("SELECT COUNT(*) AS total FROM warranty_records WHERE warranty_status='Valid'")->fetch_assoc()['total'];
$expired = $conn->query("SELECT COUNT(*) AS total FROM warranty_records WHERE warranty_status='Expired'")->fetch_assoc()['total'];
$claimed = $conn->query("SELECT COUNT(*) AS total FROM warranty_records WHERE warranty_status='Claimed'")->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Dashboard - Red Star</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
  <?php include("navbaradmin.php"); ?>

  <div class="container mt-4">
    <h3 class="mb-4">Admin Dashboard</h3>

    <!-- Summary Cards -->
    <div class="row mb-4">
      <div class="col-md-3">
        <div class="card text-center shadow-sm">
          <div class="card-body">
            <h6 class="card-title">New Bookings</h6>
            <p class="display-6 text-danger"><?= $new_bookings ?></p>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card text-center shadow-sm">
          <div class="card-body">
            <h6 class="card-title">Active Repairs</h6>
            <p class="display-6 text-primary"><?= $active_repairs ?></p>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card text-center shadow-sm">
          <div class="card-body">
            <h6 class="card-title">Warranty Claims</h6>
            <p class="display-6 text-warning"><?= $warranty_claims ?></p>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card text-center shadow-sm">
          <div class="card-body">
            <h6 class="card-title">Total Customers</h6>
            <p class="display-6 text-success"><?= $total_customers ?></p>
          </div>
        </div>
      </div>
    </div>

    <!-- Charts -->
    <div class="row">
      <div class="col-md-6">
        <div class="card p-3 shadow-sm">
          <h6 class="text-center">Repair Status Overview</h6>
          <canvas id="repairChart"></canvas>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card p-3 shadow-sm">
          <h6 class="text-center">Warranty Status</h6>
          <canvas id="warrantyChart"></canvas>
        </div>
      </div>
    </div>
  </div>

  <script>
    // Repair Status Chart
    new Chart(document.getElementById('repairChart'), {
      type: 'bar',
      data: {
        labels: ['Pending', 'In Progress', 'Completed'],
        datasets: [{
          label: 'Repairs',
          data: [<?= $pending ?>, <?= $inprogress ?>, <?= $completed ?>],
          backgroundColor: ['#ffc107', '#0d6efd', '#198754']
        }]
      }
    });

    // Warranty Status Chart
    new Chart(document.getElementById('warrantyChart'), {
      type: 'pie',
      data: {
        labels: ['Valid', 'Expired', 'Claimed'],
        datasets: [{
          data: [<?= $valid ?>, <?= $expired ?>, <?= $claimed ?>],
          backgroundColor: ['#198754', '#dc3545', '#ffc107']
        }]
      }
    });
  </script>
</body>
</html>
