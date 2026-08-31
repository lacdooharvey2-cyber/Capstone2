<?php
session_start();
include("db.php");

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $userid   = trim($_POST['userid']);
    $email    = trim($_POST['email']);
    $firstname= trim($_POST['firstname']);
    $middlename= trim($_POST['middlename']);
    $lastname = trim($_POST['lastname']);
    $gender   = $_POST['gender'];
    $dob      = $_POST['dob'];
    $age      = $_POST['age'];
    $phone    = trim($_POST['phone']);
    $religion = trim($_POST['religion']);
    $civil    = $_POST['civil_status'];
    $occupation = trim($_POST['occupation']);
    $province   = trim($_POST['province']);
    $password = trim($_POST['password']);
    $confirm  = trim($_POST['confirm_password']);
    $role     = $_POST['role'];

    if ($password !== $confirm) {
        $error = "Passwords do not match.";
    } else {
        // check if User ID or Email already exists
        $check = $conn->prepare("SELECT user_id FROM users WHERE custom_id=? OR email=?");
        $check->bind_param("ss", $userid, $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $error = "User ID or Email already registered.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $fullname = trim($firstname . " " . $middlename . " " . $lastname);

            $sql = "INSERT INTO users 
            (custom_id, name, gender, dob, age, contact_number, email, religion, civil_status, occupation, province_address, password_hash, role) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param(
                "ssssissssssss",   // 13 characters
                $userid,           // s
                $fullname,         // s
                $gender,           // s
                $dob,              // s
                $age,              // i
                $phone,            // s
                $email,            // s
                $religion,         // s
                $civil,            // s
                $occupation,       // s
                $province,         // s
                $hash,             // s
                $role              // s
            );


            if ($stmt->execute()) {
                $_SESSION['success'] = "Registration successful! Your User ID: $userid";
                header("Location: login.php");
                exit();
            } else {
                $error = "Error: " . $stmt->error;
            }
        }
        $check->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Red Star - Register</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
  <div class="container mt-5">
    <div class="col-md-8 mx-auto bg-white p-4 rounded shadow">
      <h3 class="text-danger mb-3">Personal Information</h3>

      <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST">
        <div class="mb-2">
          <label class="form-label">User ID *</label>
          <input type="text" name="userid" class="form-control" required>
        </div>
        <div class="mb-2">
          <label class="form-label">Email *</label>
          <input type="email" name="email" class="form-control" required>
        </div>
        <div class="row mb-2">
          <div class="col-md-4">
            <label class="form-label">First Name *</label>
            <input type="text" name="firstname" class="form-control" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">M.I.</label>
            <input type="text" name="middlename" class="form-control">
          </div>
          <div class="col-md-4">
            <label class="form-label">Last Name *</label>
            <input type="text" name="lastname" class="form-control" required>
          </div>
        </div>
        <div class="mb-2">
          <label class="form-label">Password *</label>
          <input type="password" name="password" class="form-control" required>
        </div>
        <div class="mb-2">
          <label class="form-label">Confirm Password *</label>
          <input type="password" name="confirm_password" class="form-control" required>
        </div>
        <div class="mb-2">
          <label class="form-label">Gender *</label>
          <select name="gender" class="form-select" required>
            <option value="">Select Gender</option>
            <option>Male</option>
            <option>Female</option>
          </select>
        </div>
        <div class="row mb-2">
          <div class="col-md-6">
            <label class="form-label">Date of Birth *</label>
            <input type="date" name="dob" class="form-control" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Age *</label>
            <input type="number" name="age" class="form-control" required>
          </div>
        </div>
        <div class="mb-2">
          <label class="form-label">Contact Number *</label>
          <input type="text" name="phone" class="form-control" required>
        </div>
        <div class="mb-2">
          <label class="form-label">Religion</label>
          <input type="text" name="religion" class="form-control">
        </div>
        <div class="mb-2">
          <label class="form-label">Civil Status *</label>
          <select name="civil_status" class="form-select" required>
            <option value="">Select Status</option>
            <option>Single</option>
            <option>Married</option>
            <option>Widowed</option>
            <option>Separated</option>
          </select>
        </div>
        <div class="mb-2">
          <label class="form-label">Occupation</label>
          <input type="text" name="occupation" class="form-control">
        </div>
        <div class="mb-2">
          <label class="form-label">Province Address *</label>
          <input type="text" name="province" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Role *</label>
          <select name="role" class="form-select" required>
            <option value="Customer" selected>Customer/User</option>
            <option value="Admin">Admin</option>
            <option value="Technician">Technician</option>
            <option value="Cashier">Cashier</option>
          </select>
        </div>
        <button type="submit" class="btn btn-danger w-100">Register</button>
      </form>

      <div class="mt-3 text-center">
        <a href="login.php" class="text-danger fw-bold">Already have an account? Login</a>
      </div>
    </div>
  </div>
</body>
</html>
