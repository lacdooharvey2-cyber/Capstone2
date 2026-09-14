<?php
session_start();
include("db.php");
include_once("activity_log_helper.php");

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['Admin', 'SuperAdmin'], true)) {
    header("Location: login.php");
    exit();
}

$user_id = intval($_GET['id'] ?? $_POST['user_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $contact = trim($_POST['contact_number'] ?? '');
    $role = $_POST['role'] ?? 'Customer';
    $account_status = $_POST['account_status'] ?? 'Active';
    $allowedRoles = ['Customer', 'Staff', 'Technician', 'Cashier', 'Admin', 'SuperAdmin'];
    $allowedStatuses = ['Active', 'Inactive'];

    if ($user_id > 0 && $name !== '' && $email !== '' && in_array($role, $allowedRoles, true) && in_array($account_status, $allowedStatuses, true)) {
        $stmt = $conn->prepare("UPDATE users SET name=?, email=?, contact_number=?, role=?, account_status=? WHERE user_id=?");
        $stmt->bind_param("sssssi", $name, $email, $contact, $role, $account_status, $user_id);
        $stmt->execute();
        logActivity($conn, (int)$_SESSION['user_id'], (string)$_SESSION['role'], 'User updated', 'Updated user #' . $user_id . ' role/status.', 'user', $user_id);
        header("Location: adminmanageusers.php?updated=1");
        exit();
    }
}

$stmt = $conn->prepare("SELECT user_id, name, email, contact_number, role, account_status FROM users WHERE user_id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Edit User - FixTrack</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
  <?php include("navbaradmin.php"); ?>
  <div class="container mt-4">
    <div class="card shadow-sm"><div class="card-body">
      <h3 class="mb-3">Edit User</h3>
      <?php if ($user): ?>
        <form method="post">
          <input type="hidden" name="user_id" value="<?= htmlspecialchars($user['user_id']) ?>">
          <div class="mb-3"><label class="form-label">Name</label><input class="form-control" name="name" value="<?= htmlspecialchars($user['name']) ?>" required></div>
          <div class="mb-3"><label class="form-label">Email</label><input class="form-control" type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required></div>
          <div class="mb-3"><label class="form-label">Contact Number</label><input class="form-control" name="contact_number" value="<?= htmlspecialchars($user['contact_number'] ?? '') ?>"></div>
          <div class="mb-3"><label class="form-label">Role</label><select class="form-select" name="role"><?php foreach(['Customer','Staff','Technician','Cashier','Admin','SuperAdmin'] as $role): ?><option <?= $user['role']===$role?'selected':'' ?>><?= $role ?></option><?php endforeach; ?></select></div>
          <div class="mb-3"><label class="form-label">Status</label><select class="form-select" name="account_status"><?php foreach(['Active','Inactive'] as $status): ?><option <?= $user['account_status']===$status?'selected':'' ?>><?= $status ?></option><?php endforeach; ?></select></div>
          <button class="btn btn-danger">Save</button>
          <a href="adminmanageusers.php" class="btn btn-secondary">Cancel</a>
        </form>
      <?php else: ?>
        <p class="text-muted">User not found.</p><a href="adminmanageusers.php" class="btn btn-secondary">Back</a>
      <?php endif; ?>
    </div></div>
  </div>
</body>
</html>
