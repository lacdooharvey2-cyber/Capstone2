<?php
session_start();
include("db.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

// Summary counts
$totalTransactions = $conn->query("SELECT COUNT(*) AS cnt FROM repairs")->fetch_assoc()['cnt'];
$completedRepairs = $conn->query("SELECT COUNT(*) AS cnt FROM repairs WHERE repair_status='Completed'")->fetch_assoc()['cnt'];
$activeWarranties = $conn->query("SELECT COUNT(*) AS cnt FROM warranty_records WHERE warranty_status='Active'")->fetch_assoc()['cnt'];
$claims = $conn->query("SELECT COUNT(*) AS cnt FROM warranty_records WHERE warranty_status='Claimed'")->fetch_assoc()['cnt'];

// Revenue (make sure repairs table has 'amount' column)
$revenueRow = $conn->query("SELECT SUM(amount) AS total FROM repairs WHERE repair_status='Completed'");
$revenue = $revenueRow ? $revenueRow->fetch_assoc()['total'] : 0;

// Monthly repairs (last 6 months)
$monthlyRepairs = $conn->query("
  SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, COUNT(*) AS cnt
  FROM repairs
  GROUP BY month
  ORDER BY month ASC
  LIMIT 6
");

// Warranty status distribution
$warrantyStatus = $conn->query("
  SELECT warranty_status, COUNT(*) AS cnt
  FROM warranty_records
  GROUP BY warranty_status
");

// Top 5 E-bike models repaired
$topModels = $conn->query("
  SELECT ebike_model, COUNT(*) AS cnt
  FROM repairs
  GROUP BY ebike_model
  ORDER BY cnt DESC
  LIMIT 5
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Analytics - Red Star</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
  <?php include("navbaradmin.php"); ?>

  <div class="container mt-4">
    <h3 class="mb-4">Business Analytics & Reports</h3>

<div class="row mb-4">
  <div class="col-md">
    <div class="card text-center shadow-sm">
      <div class="card-body">
        <h6>Total Transactions</h6>
        <p class="display-6 text-danger"><?= $totalTransactions ?></p>
      </div>
    </div>
  </div>
  <div class="col-md">
    <div class="card text-center shadow-sm">
      <div class="card-body">
        <h6>Completed Repairs</h6>
        <p class="display-6 text-success"><?= $completedRepairs ?></p>
      </div>
    </div>
  </div>
  <div class="col-md">
    <div class="card text-center shadow-sm">
      <div class="card-body">
        <h6>Active Warranties</h6>
        <p class="display-6 text-primary"><?= $activeWarranties ?></p>
      </div>
    </div>
  </div>
  <div class="col-md">
    <div class="card text-center shadow-sm">
      <div class="card-body">
        <h6>Claims Filed</h6>
        <p class="display-6 text-warning"><?= $claims ?></p>
      </div>
    </div>
  </div>
  <div class="col-md">
    <div class="card text-center shadow-sm">
      <div class="card-body">
        <h6>Total Revenue</h6>
        <p class="display-6 text-success">₱<?= number_format($revenue, 2) ?></p>
      </div>
    </div>
  </div>
</div>



    <!-- Charts -->
    <div class="row mb-4">
      <div class="col-md-6">
        <div class="card p-3 shadow-sm">
          <h6 class="text-center">Monthly Repairs</h6>
          <canvas id="repairsChart"></canvas>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card p-3 shadow-sm">
          <h6 class="text-center">Warranty Status</h6>
          <canvas id="warrantyChart"></canvas>
        </div>
      </div>
    </div>

    <div class="row mb-4">
      <div class="col-md-12">
        <div class="card p-3 shadow-sm">
          <h6 class="text-center">Top 5 E-bike Models Repaired</h6>
          <canvas id="modelsChart"></canvas>
        </div>
      </div>
    </div>
<script>
  // Monthly Repairs Line Chart
  new Chart(document.getElementById('repairsChart'), {
    type: 'line',
    data: {
      labels: [<?php while($r=$monthlyRepairs->fetch_assoc()){ echo "'".$r['month']."',"; } ?>],
      datasets: [{
        label: 'Repairs',
        data: [<?php $monthlyRepairs->data_seek(0); while($r=$monthlyRepairs->fetch_assoc()){ echo $r['cnt'].","; } ?>],
        borderColor: '#dc3545',
        backgroundColor: 'rgba(220,53,69,0.3)',
        fill: true
      }]
    }
  });

  // Warranty Status Pie Chart
  new Chart(document.getElementById('warrantyChart'), {
    type: 'pie',
    data: {
      labels: [<?php while($w=$warrantyStatus->fetch_assoc()){ echo "'".$w['warranty_status']."',"; } ?>],
      datasets: [{
        data: [<?php $warrantyStatus->data_seek(0); while($w=$warrantyStatus->fetch_assoc()){ echo $w['cnt'].","; } ?>],
        backgroundColor: ['#198754','#ffc107','#dc3545','#6c757d']
      }]
    }
  });

  // Top Models Bar Chart
  new Chart(document.getElementById('modelsChart'), {
    type: 'bar',
    data: {
      labels: [<?php while($m=$topModels->fetch_assoc()){ echo "'".$m['ebike_model']."',"; } ?>],
      datasets: [{
        label: 'Service Count',
        data: [<?php $topModels->data_seek(0); while($m=$topModels->fetch_assoc()){ echo $m['cnt'].","; } ?>],
        backgroundColor: 'rgba(13,110,253,0.7)'
      }]
    }
  });
</script>
</body>
</html>
