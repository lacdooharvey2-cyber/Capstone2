<?php
session_start();
include("db.php");

// Guard: only allow Admins
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

// Fetch all repair bookings
$sql = "SELECT repair_id, customer_id, ebike_model, issue_description, repair_status, created_at 
        FROM repairs ORDER BY created_at DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manage Repairs - Red Star Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
  <?php include("navbaradmin.php"); ?> <!-- make sure file exists -->

  <div class="container mt-4">
    <h3 class="mb-4">Manage Repair Bookings</h3>

    <table class="table table-hover table-bordered bg-white shadow-sm">
      <thead class="table-danger">
        <tr>
          <th>Repair ID</th>
          <th>Customer ID</th>
          <th>E-Bike Model</th>
          <th>Issue</th>
          <th>Status</th>
          <th>Date Created</th>
          <th style="width:150px;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($result && $result->num_rows > 0): ?>
          <?php while($row = $result->fetch_assoc()): ?>
            <tr>
              <td><?= htmlspecialchars($row['repair_id']) ?></td>
              <td><?= htmlspecialchars($row['customer_id']) ?></td>
              <td><?= htmlspecialchars($row['ebike_model']) ?></td>
              <td><?= htmlspecialchars($row['issue_description']) ?></td>
              <td>
                <?php if ($row['repair_status'] === 'Pending'): ?>
                  <span class="badge bg-warning">Pending</span>
                <?php elseif ($row['repair_status'] === 'In Progress'): ?>
                  <span class="badge bg-primary">In Progress</span>
                <?php elseif ($row['repair_status'] === 'Completed'): ?>
                  <span class="badge bg-success">Completed</span>
                <?php else: ?>
                  <span class="badge bg-danger"><?= htmlspecialchars($row['repair_status']) ?></span>
                <?php endif; ?>
              </td>
              <td><?= htmlspecialchars($row['created_at']) ?></td>
              <td>
                <a href="editrepair.php?id=<?= urlencode($row['repair_id']) ?>" class="btn btn-sm btn-primary">Edit</a>
                <a href="deleterepair.php?id=<?= urlencode($row['repair_id']) ?>" 
                   class="btn btn-sm btn-danger"
                   onclick="return confirm('Are you sure you want to delete this booking?');">Delete</a>
              </td>
            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr>
            <td colspan="7" class="text-center text-muted">No repair bookings found.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</body>
</html>
