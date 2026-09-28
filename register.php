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
    // Public registration can only create Customer accounts.
    $role     = 'Customer';

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
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>FixTrack - Register</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    :root { --brand-red: #198754; --brand-dark: #1f2937; --brand-muted: #6c757d; }
    * { box-sizing: border-box; }
    html { scroll-behavior: smooth; }
    body {
      min-height: 100vh;
      margin: 0;
      background: #f7f8f7;
      color: var(--brand-dark);
      font-family: "Segoe UI", Arial, sans-serif;
      overflow-x: hidden;
    }
    .register-shell { min-height: 100vh; display: flex; align-items: stretch; padding: 0; }
    .register-panel {
      width: 100%;
      max-width: none;
      min-height: 100vh;
      margin: 0;
      overflow: visible;
      background: #fff;
      border: 0;
      border-radius: 0;
      box-shadow: none;
      animation: panelIn .7s cubic-bezier(.2,.8,.2,1) both;
    }
    .register-panel > .row { min-height: 100vh; }
    .register-brand {
      min-height: 100vh;
      padding: clamp(48px, 7vw, 110px);
      display: flex;
      flex-direction: column;
      justify-content: center;
      color: #17221a;
      background: #f7f8f7;
      border-right: 1px solid #e4e9e5;
      position: relative;
    }
    .brand-mark {
      width: 38px;
      height: 38px;
      display: grid;
      place-items: center;
      border-radius: 6px;
      background: #003b16;
      color: #fff;
      font-size: 18px;
    }
    .brand-lockup { display: inline-flex; align-items: center; gap: 10px; color: #003b16; font-size: 1.25rem; font-weight: 800; text-decoration: none; }
    .brand-title { max-width: 420px; margin: 42px 0 16px; font-size: clamp(2.3rem, 4vw, 4rem); font-weight: 800; line-height: 1.08; }
    .brand-copy { max-width: 390px; color: #647068; line-height: 1.7; }
    .register-points { display: grid; gap: 12px; }
    .register-point {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 12px 14px;
      border: 1px solid #b8e4c2;
      border-radius: 6px;
      background: #e5f8e9;
      color: #146c43;
      animation: reveal .7s both;
    }
    .register-point:nth-child(2) { animation-delay: .12s; }
    .register-point:nth-child(3) { animation-delay: .2s; }
    .register-form {
      min-height: 100vh;
      padding: clamp(32px, 4vw, 64px) clamp(28px, 6vw, 96px);
      animation: reveal .7s .15s both;
    }
    .register-form .form-section { animation: reveal .6s both; }
    .register-form .form-section:nth-child(2) { animation-delay: .08s; }
    .register-form .form-section:nth-child(3) { animation-delay: .16s; }
    .register-form .form-section:nth-child(4) { animation-delay: .24s; }
    .register-form > form > .mt-4 { animation: reveal .6s .32s both; }
    .register-form > .mt-4 { animation: reveal .6s .4s both; }
    .form-heading { margin-bottom: 4px; font-size: 30px; font-weight: 800; }
    .form-subtitle { color: var(--brand-muted); margin-bottom: 28px; }
    .form-section {
      margin-top: 22px;
      padding-top: 18px;
      border-top: 1px solid #e9ecef;
    }
    .form-section-title { color: #146c43; font-size: .9rem; font-weight: 800; letter-spacing: .02em; margin-bottom: 14px; }
    .form-label { color: #495057; font-size: .88rem; font-weight: 700; margin-bottom: 6px; }
    .form-control, .form-select { min-height: 44px; border-radius: 8px; }
    .form-control, .form-select { transition: border-color .28s ease, box-shadow .28s ease, transform .28s cubic-bezier(.22,1,.36,1); }
    .form-control:focus, .form-select:focus { border-color: var(--brand-red); box-shadow: 0 0 0 .22rem rgba(25,135,84,.14); transform: translateY(-1px); }
    .btn-success { min-height: 46px; border-radius: 8px; font-weight: 700; transition: transform .2s ease, box-shadow .2s ease; }
    .btn-success:hover { transform: translateY(-1px); box-shadow: 0 12px 24px rgba(25,135,84,.22); }
    .login-link { color: var(--brand-red); font-weight: 700; text-decoration: none; }
    .login-link:hover { text-decoration: underline; }
    @keyframes panelIn { from { opacity: 0; transform: translateY(18px) scale(.99); } to { opacity: 1; transform: translateY(0) scale(1); } }
    @keyframes reveal { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    @media (max-width: 991px) {
      .register-brand { min-height: auto; padding: 32px; }
      .register-form { padding: 34px; }
    }
    @media (max-width: 575px) {
      .register-panel { border-radius: 0; }
      .register-brand { display: none; }
      .register-form { padding: 26px 20px; }
      .form-heading { font-size: 26px; }
    }
    @media (prefers-reduced-motion: reduce) {
      *, *::before, *::after { animation-duration: .01ms !important; animation-iteration-count: 1 !important; transition-duration: .01ms !important; }
    }
  </style>
</head>
<body>
  <main class="register-shell">
    <section class="register-panel">
      <div class="row g-0">
        <div class="col-lg-3 register-brand">
          <div>
            <a class="brand-lockup" href="Homepage.php"><span class="brand-mark"><i class="bi bi-wrench-adjustable"></i></span> FixTrack</a>
            <h1 class="brand-title">Built for smoother E-bike service.</h1>
            <p class="brand-copy">Create your account to book repairs, track service progress, and manage warranty coverage in one place.</p>
          </div>
        </div>
        <div class="col-lg-9 register-form">
          <div>
            <span class="badge text-bg-danger-subtle text-danger border border-danger-subtle mb-3">FixTrack Portal</span>
            <h2 class="form-heading">Create your account</h2>
            <p class="form-subtitle">Register as a customer to access the e-bike repair portal.</p>
          </div>

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
        <a href="login.php" class="login-link ms-1">Login</a>
      </div>
        </div>
      </div>
    </section>
  </main>
</body>
</html>
