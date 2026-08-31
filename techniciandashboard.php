<?php
session_start();
include("db.php");

// Guard: only allow Technician
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Technician') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Sample metrics (replace with actual queries per technician)
$completedRepairs = $conn->query("SELECT COUNT(*) AS cnt FROM repairs WHERE repair_status='Completed'")->fetch_assoc()['cnt'];
$inProgressRepairs = $conn->query("SELECT COUNT(*) AS cnt FROM repairs WHERE technician_id=$user_id AND repair_status='In Progress'")->fetch_assoc()['cnt'];
$pendingRepairs   = $conn->query("SELECT COUNT(*) AS cnt FROM repairs WHERE technician_id=$user_id AND repair_status='Pending'")->fetch_assoc()['cnt'];

$rating = 4.8; // placeholder

// Profile info
$profileStatus = "Approved";
$onlineStatus  = "Available";
$specializations = ["Motor & Drivetrain","Battery & Charging","Electronics & Display"];

// Upcoming appointments (mock data)
$appointments = [
  ["date"=>"2026-09-02 10:00:00","customer"=>"Maria Santos","duration"=>"120 min","status"=>"Pending"]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Technician Dashboard - Red Star</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background: linear-gradient(270deg, #f8f9fa, #ffe5e5, #f8f9fa);
      background-size: 600% 600%;
      animation: gradientMove 12s ease infinite;
      font-family: 'Segoe UI', sans-serif;
    }
    @keyframes gradientMove {
      0% { background-position: 0% 50%; }
      50% { background-position: 100% 50%; }
      100% { background-position: 0% 50%; }
    }
    .container { animation: fadeInUp 1s ease; }
    @keyframes fadeInUp {
      from { opacity: 0; transform: translateY(30px); }
      to { opacity: 1; transform: translateY(0); }
    }
    .card { border-radius: 12px; }
    .card-title { font-weight: 600; }
    .btn-danger:hover {
      transform: translateY(-2px);
      box-shadow: 0px 4px 12px rgba(220,53,69,0.4);
    }
  </style>
</head>
<body>
  <?php include("navbartechnician.php"); ?>

  <div class="container mt-4">
    <h3 class="mb-4">Welcome, Technician</h3>

    <!-- Performance Metrics -->
    <div class="row mb-4">
      <div class="col-md-3">
        <div class="card text-center shadow-sm">
          <div class="card-body">
            <h6>Completed Repairs</h6>
            <p class="display-6 text-success"><?= $completedRepairs ?></p>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card text-center shadow-sm">
          <div class="card-body">
            <h6>In Progress</h6>
            <p class="display-6 text-info"><?= $inProgressRepairs ?></p>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card text-center shadow-sm">
          <div class="card-body">
            <h6>Pending</h6>
            <p class="display-6 text-warning"><?= $pendingRepairs ?></p>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card text-center shadow-sm">
          <div class="card-body">
            <h6>Rating</h6>
            <p class="display-6 text-danger"><?= $rating ?> / 5</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Profile Section -->
    <div class="card shadow-sm mb-4">
      <div class="card-body">
        <h5 class="card-title">My Profile</h5>
        <p>Status: <span class="badge bg-success"><?= $profileStatus ?></span></p>
        <p>Online: <span class="badge bg-info"><?= $onlineStatus ?></span></p>
        <p>Specializations:</p>
        <ul>
          <?php foreach($specializations as $spec): ?>
            <li><?= $spec ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>

    <!-- Active Work Queue -->
    <div class="card shadow-sm mb-4">
      <div class="card-body">
        <h5 class="card-title">Active Work Queue</h5>
        <button class="btn btn-danger">View Full Queue</button>
      </div>
    </div>

    <!-- Upcoming Appointments -->
    <div class="card shadow-sm mb-4">
      <div class="card-body">
        <h5 class="card-title">Upcoming Appointments</h5>
        <table class="table table-hover table-bordered">
          <thead class="table-danger">
            <tr>
              <th>Date & Time</th>
              <th>Customer</th>
              <th>Duration</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($appointments as $appt): ?>
              <tr>
                <td><?= $appt['date'] ?></td>
                <td><?= $appt['customer'] ?></td>
                <td><?= $appt['duration'] ?></td>
                <td><span class="badge bg-warning"><?= $appt['status'] ?></span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <button class="btn btn-danger">View Full Schedule</button>
      </div>
    </div>
  </div>
</body>
</html>
