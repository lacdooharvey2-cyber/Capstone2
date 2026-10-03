<?php
session_start();
include("db.php");

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['Admin', 'SuperAdmin'], true)) {
    header("Location: login.php");
    exit();
}

$brandCounts = $conn->query("SELECT brand, COUNT(*) AS total FROM ebike_synthetic_dataset GROUP BY brand ORDER BY brand");
$total = $conn->query("SELECT COUNT(*) AS total FROM ebike_synthetic_dataset")->fetch_assoc()['total'] ?? 0;
$rows = $conn->query("
  SELECT dataset_user_id, brand, model, name, age, address, number_of_issues, issue_1, issue_2, issue_3
  FROM ebike_synthetic_dataset
  ORDER BY dataset_user_id
  LIMIT 100
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>E-Bike Dataset - FixTrack Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
  <?php include("navbaradmin.php"); ?>
  <div class="container mt-4">
    <h3 class="mb-4">NWOW / KUDA Dataset</h3>
    <div class="row g-3 mb-4">
      <div class="col-md-4">
        <div class="card shadow-sm"><div class="card-body text-center"><h6>Total Dataset Rows</h6><p class="display-6 text-danger"><?= htmlspecialchars($total) ?></p></div></div>
      </div>
      <?php if ($brandCounts): while($brand = $brandCounts->fetch_assoc()): ?>
        <div class="col-md-4">
          <div class="card shadow-sm"><div class="card-body text-center"><h6><?= htmlspecialchars($brand['brand']) ?> Users</h6><p class="display-6 text-primary"><?= htmlspecialchars($brand['total']) ?></p></div></div>
        </div>
      <?php endwhile; endif; ?>
    </div>

    <div class="card shadow-sm">
      <div class="card-body">
        <h5 class="mb-3">Preview First 100 Rows</h5>
        <div class="table-responsive">
          <table class="table table-hover table-bordered">
            <thead class="table-danger">
              <tr><th>User ID</th><th>Brand</th><th>Model</th><th>Name</th><th>Age</th><th>Address</th><th>Issues</th></tr>
            </thead>
            <tbody>
              <?php if ($rows && $rows->num_rows > 0): while($row = $rows->fetch_assoc()): ?>
                <tr>
                  <td><?= htmlspecialchars($row['dataset_user_id']) ?></td>
                  <td><?= htmlspecialchars($row['brand']) ?></td>
                  <td><?= htmlspecialchars($row['model']) ?></td>
                  <td><?= htmlspecialchars($row['name']) ?></td>
                  <td><?= htmlspecialchars($row['age']) ?></td>
                  <td><?= htmlspecialchars($row['address']) ?></td>
                  <td><?= htmlspecialchars(implode(', ', array_filter([$row['issue_1'], $row['issue_2'], $row['issue_3']]))) ?></td>
                </tr>
              <?php endwhile; else: ?>
                <tr><td colspan="7" class="text-center text-muted">No dataset rows found. Run import_ebike_dataset.php first.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</body>
</html>


