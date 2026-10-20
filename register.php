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
    $role     = 'Customer';

    if ($password !== $confirm) {
        $error = "Passwords do not match.";
    } else {
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
                "ssssissssssss",
                $userid,
                $fullname,
                $gender,
                $dob,
                $age,
                $phone,
                $email,
                $religion,
                $civil,
                $occupation,
                $province,
                $hash,
                $role
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
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>FixTrack - Register</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/css/auth-theme.css?v=20261014" rel="stylesheet">
</head>
<body class="auth-page">
  <main class="auth-shell">
    <section class="auth-panel">
      <div class="row g-0">
        <div class="col-lg-4 brand-side">
          <div>
            <a class="brand-lockup" href="Homepage.php">
              <span class="brand-mark"><i class="bi bi-wrench-adjustable"></i></span> FixTrack
            </a>
            <h1 class="brand-title">Built for smoother<br>E-bike service.</h1>
            <p class="brand-copy">Register your account to book repairs, track service progress, and manage warranty coverage.</p>
            <div class="feature-row">
              <div class="feature-pill"><i class="bi bi-tool"></i> Expert e-bike repair service</div>
              <div class="feature-pill"><i class="bi bi-shield-check"></i> Automatic warranty verification</div>
              <div class="feature-pill"><i class="bi bi-geo-alt"></i> Flexible scheduling options</div>
            </div>
          </div>
        </div>

        <div class="col-lg-8 auth-form-side">
          <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
          <?php endif; ?>

          <form method="POST">
            <div class="form-section mt-0 pt-0 border-0">
              <div class="form-section-title">Account details</div>
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">User ID *</label>
                  <input type="text" name="userid" class="form-control" placeholder="Example: NWOW-0001" autocomplete="username" autofocus required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Email *</label>
                  <input type="email" name="email" class="form-control" placeholder="you@example.com" autocomplete="email" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Password *</label>
                  <input type="password" name="password" class="form-control" autocomplete="new-password" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Confirm Password *</label>
                  <input type="password" name="confirm_password" class="form-control" autocomplete="new-password" required>
                </div>
              </div>
            </div>

            <div class="form-section">
              <div class="form-section-title">Personal information</div>
              <div class="row g-3">
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
              <div class="row g-3 mt-0">
                <div class="col-md-4">
                  <label class="form-label">Gender *</label>
                  <select name="gender" class="form-select" required>
                    <option value="">Select Gender</option>
                    <option>Male</option>
                    <option>Female</option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Date of Birth *</label>
                  <input type="date" name="dob" class="form-control" required>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Age *</label>
                  <input type="number" name="age" min="1" max="120" class="form-control" required>
                </div>
              </div>
            </div>

            <div class="form-section">
              <div class="form-section-title">Contact and profile</div>
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">Contact Number *</label>
                  <input type="text" name="phone" class="form-control" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Religion</label>
                  <input type="text" name="religion" class="form-control">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Civil Status *</label>
                  <select name="civil_status" class="form-select" required>
                    <option value="">Select Status</option>
                    <option>Single</option>
                    <option>Married</option>
                    <option>Widowed</option>
                    <option>Separated</option>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Occupation</label>
                  <input type="text" name="occupation" class="form-control">
                </div>
                <div class="col-12">
                  <label class="form-label">Province Address *</label>
                  <input type="text" name="province" class="form-control" required>
                </div>
              </div>
            </div>

            <div class="mt-4">
              <button type="submit" class="btn btn-success w-100"><i class="bi bi-person-plus me-1"></i>Create Customer Account</button>
            </div>
          </form>

          <div class="mt-4 text-center">
            <span class="text-muted">Already have an account?</span>
            <a href="login.php" class="small-link ms-1">Login</a>
          </div>
        </div>
      </div>
    </section>
  </main>
</body>
</html>
