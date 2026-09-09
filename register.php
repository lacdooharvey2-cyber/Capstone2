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
    :root { --brand-red: #dc3545; --brand-dark: #1f2937; --brand-muted: #6c757d; }
    * { box-sizing: border-box; }
    body {
      min-height: 100vh;
      margin: 0;
      background:
        linear-gradient(135deg, rgba(220, 53, 69, .08), rgba(255,255,255,.94)),
        url("https://imgcdn.zigwheels.ph/large/gallery/exterior/154/3048/nwow-gb2-slant-front-view-full-image-859417.jpg") center/cover fixed;
      color: var(--brand-dark);
      font-family: "Segoe UI", Arial, sans-serif;
      overflow-x: hidden;
    }
    .register-shell { min-height: 100vh; display: flex; align-items: center; padding: 28px 16px; }
    .register-panel {
      width: 100%;
      max-width: 1160px;
      margin: 0 auto;
      overflow: hidden;
      background: #fff;
      border: 1px solid rgba(222,226,230,.85);
      border-radius: 18px;
      box-shadow: 0 24px 70px rgba(31,41,55,.18);
      animation: panelIn .7s cubic-bezier(.2,.8,.2,1) both;
    }
    .register-brand {
      min-height: 700px;
      padding: 42px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      color: #fff;
      background:
        linear-gradient(160deg, rgba(132,32,41,.94), rgba(33,37,41,.86)),
        url("https://filebroker-cdn.lazada.com.ph/kf/S5cc28fccabd645cf918dfee7f7a54fa7I.jpg") center/cover;
      position: relative;
      isolation: isolate;
    }
    .register-brand::after {
      content: "";
      position: absolute;
      inset: 0;
      background: linear-gradient(120deg, transparent 25%, rgba(255,255,255,.12) 48%, transparent 70%);
      transform: translateX(-110%);
      animation: sheen 7s ease-in-out 1s infinite;
      pointer-events: none;
      z-index: -1;
    }
    .brand-mark {
      width: 54px;
      height: 54px;
      display: grid;
      place-items: center;
      border-radius: 14px;
      background: rgba(255,255,255,.14);
      border: 1px solid rgba(255,255,255,.25);
      font-size: 28px;
      animation: markFloat 3.5s ease-in-out infinite;
    }
    .brand-title { margin: 22px 0 10px; font-size: 42px; font-weight: 800; }
    .brand-copy { max-width: 370px; color: rgba(255,255,255,.82); line-height: 1.7; }
    .register-points { display: grid; gap: 12px; }
    .register-point {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 12px 14px;
      border: 1px solid rgba(255,255,255,.16);
      border-radius: 8px;
      background: rgba(255,255,255,.12);
      color: rgba(255,255,255,.92);
      animation: reveal .7s both;
    }
    .register-point:nth-child(2) { animation-delay: .12s; }
    .register-point:nth-child(3) { animation-delay: .2s; }
    .register-form { padding: 42px 46px; animation: reveal .7s .15s both; }
    .form-heading { margin-bottom: 4px; font-size: 30px; font-weight: 800; }
    .form-subtitle { color: var(--brand-muted); margin-bottom: 28px; }
    .form-section {
      margin-top: 22px;
      padding-top: 18px;
      border-top: 1px solid #e9ecef;
    }
    .form-section-title { color: #842029; font-size: .9rem; font-weight: 800; letter-spacing: .02em; margin-bottom: 14px; }
    .form-label { color: #495057; font-size: .88rem; font-weight: 700; margin-bottom: 6px; }
    .form-control, .form-select { min-height: 44px; border-radius: 8px; }
    .form-control:focus, .form-select:focus { border-color: var(--brand-red); box-shadow: 0 0 0 .22rem rgba(220,53,69,.14); }
    .btn-danger { min-height: 46px; border-radius: 8px; font-weight: 700; transition: transform .2s ease, box-shadow .2s ease; }
    .btn-danger:hover { transform: translateY(-1px); box-shadow: 0 12px 24px rgba(220,53,69,.22); }
    .login-link { color: var(--brand-red); font-weight: 700; text-decoration: none; }
    .login-link:hover { text-decoration: underline; }
    @keyframes panelIn { from { opacity: 0; transform: translateY(18px) scale(.99); } to { opacity: 1; transform: translateY(0) scale(1); } }
    @keyframes reveal { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    @keyframes sheen { 0%,55% { transform: translateX(-110%); } 75%,100% { transform: translateX(110%); } }
    @keyframes markFloat { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-4px); } }
    @media (max-width: 991px) {
      .register-brand { min-height: auto; padding: 32px; }
      .register-form { padding: 34px; }
    }
    @media (max-width: 575px) {
      .register-shell { padding: 12px 8px; }
      .register-panel { border-radius: 12px; }
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
        <div class="col-lg-4 register-brand">
          <div>
            <div class="brand-mark"><i class="bi bi-lightning-charge-fill"></i></div>
            <h1 class="brand-title">FixTrack</h1>
            <p class="brand-copy">Create your customer account to book repairs, monitor service progress, and keep your e-bike warranty details in one place.</p>
          </div>
          <div class="register-points">
            <div class="register-point"><i class="bi bi-person-check"></i><span>Simple customer registration</span></div>
            <div class="register-point"><i class="bi bi-tools"></i><span>Book and track repairs easily</span></div>
            <div class="register-point"><i class="bi bi-shield-check"></i><span>Keep warranty coverage visible</span></div>
          </div>
        </div>
        <div class="col-lg-8 register-form">
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
        <div class="col-md-6">
          <label class="form-label">Gender *</label>
          <select name="gender" class="form-select" required>
            <option value="">Select Gender</option>
            <option>Male</option>
            <option>Female</option>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">Date of Birth *</label>
          <input type="date" name="dob" class="form-control" required>
        </div>
        <div class="col-md-6">
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
          <button type="submit" class="btn btn-danger w-100"><i class="bi bi-person-plus me-1"></i>Create Customer Account</button>
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
