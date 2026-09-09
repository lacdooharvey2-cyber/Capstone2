<?php
session_start();
include("db.php");

// Guard: only allow Admins
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

// Adjust query to match your actual table structure
$sql = "SELECT user_id, custom_id, name, email, contact_number, role, account_status 
        FROM users ORDER BY role, name";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Manage Users - FixTrack</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
  <?php include("navbaradmin.php"); ?>

  <div class="container mt-4">
    <div class="page-hero">
      <h3 class="mb-1">Manage Users</h3>
      <p>Review customer, technician, cashier, and admin accounts.</p>
    </div>

    <div class="card shadow-sm">
      <div class="card-body">
        <div class="table-responsive">
    <table class="table table-hover table-bordered mb-0">
      <thead class="table-danger">
        <tr>
          <th>User ID</th>
          <th>Custom ID</th>
          <th>Name</th>
          <th>Email</th>
          <th>Contact Number</th>
          <th>Role</th>
          <th>Status</th>
          <th style="width:150px;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($result && $result->num_rows > 0): ?>
          <?php while($row = $result->fetch_assoc()): ?>
            <tr>
              <td><?= htmlspecialchars($row['user_id']) ?></td>
              <td><?= htmlspecialchars($row['custom_id']) ?></td>
              <td><?= htmlspecialchars($row['name']) ?></td>
              <td><?= htmlspecialchars($row['email']) ?></td>
              <td><?= htmlspecialchars($row['contact_number']) ?></td>
              <td><span class="badge bg-secondary"><?= htmlspecialchars($row['role']) ?></span></td>
              <td>
                <?php if ($row['account_status'] === 'Active'): ?>
                  <span class="badge bg-success">Active</span>
                <?php else: ?>
                  <span class="badge bg-danger">Inactive</span>
                <?php endif; ?>
              </td>
              <td>
                <a href="edituser.php?id=<?= urlencode($row['user_id']) ?>" class="btn btn-sm btn-primary">Edit</a>
                <a href="deleteuser.php?id=<?= urlencode($row['user_id']) ?>" 
                   class="btn btn-sm btn-danger"
                   onclick="return confirm('Are you sure you want to delete this user?');">Delete</a>
              </td>
            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr>
            <td colspan="8" class="text-center text-muted">No users found.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
