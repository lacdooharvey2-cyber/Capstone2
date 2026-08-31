<?php
session_start();
include("db.php");

$error = "";

$googleErrors = [
    'google_cancelled' => 'Google sign-in was cancelled.',
    'invalid_state' => 'Google sign-in session expired. Please try again.',
    'no_code' => 'Google did not return an authorization code. Please try again.',
    'google_auth_failed' => 'Google could not verify your account. Please try again.',
    'google_data_missing' => 'Google did not return the required account details.',
    'google_login_failed' => 'Google login failed. Please try again.',
    'google_library_missing' => 'Google login is not installed yet. Please run composer install.',
    'invalidrole' => 'Your account role is invalid. Please contact an administrator.',
];

if (isset($_GET['error'], $googleErrors[$_GET['error']])) {
    $error = $googleErrors[$_GET['error']];
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $userid = trim($_POST['userid']);
    $password = trim($_POST['password']);

    // Query by custom_id (User ID)
    $sql = "SELECT * FROM users WHERE custom_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $userid);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if (password_verify($password, $row['password_hash'])) {
            // set session
            $_SESSION['user_id']   = $row['user_id'];     // numeric PK
            $_SESSION['custom_id'] = $row['custom_id'];   // string User ID
            $_SESSION['role']      = $row['role'];

            // role-based redirect
            switch ($row['role']) {
                case 'Admin':
                    header("Location: admindashboard.php");
                    break;
                case 'Technician':
                    header("Location: techniciandashboard.php");
                    break;
                case 'Customer':
                    header("Location: customerdashboard.php");
                    break;
                case 'Cashier':
                    header("Location: cashierdashboard.php");
                    break;
                default:
                    header("Location: index.php?error=invalidrole");
            }
            exit();
        } else {
            $error = "Invalid password.";
        }
    } else {
        $error = "User ID not found.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Red Star - Login</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
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
    .login-container {
      max-width: 420px;
      margin: 80px auto;
      padding: 40px;
      background: #fff;
      border-radius: 12px;
      box-shadow: 0px 6px 20px rgba(0,0,0,0.15);
      animation: fadeInUp 1s ease;
    }
    .brand { text-align: center; margin-bottom: 25px; }
    .brand h2 { color: #dc3545; font-weight: bold; }
    .brand .icon { font-size: 48px; color: #ffc107; display: inline-block; animation: pulseStar 2s infinite; }
    @keyframes pulseStar {
      0% { transform: scale(1); }
      50% { transform: scale(1.2); color: #ffca2c; }
      100% { transform: scale(1); }
    }
    .btn-danger:hover { transform: translateY(-2px); box-shadow: 0px 4px 12px rgba(220,53,69,0.4); }
    input:focus { box-shadow: 0 0 6px rgba(220,53,69,0.5); border-color: #dc3545; }


    /* =====================================================
       GOOGLE LOGIN BUTTON - ADDED
       ===================================================== */

    .google-login-btn {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      width: 100%;
      padding: 10px 15px;
      border: 1px solid #dadce0;
      border-radius: 6px;
      background-color: #fff;
      color: #3c4043;
      text-decoration: none;
      font-size: 15px;
      font-weight: 500;
      transition: all 0.2s ease;
    }

    .google-login-btn:hover {
      background-color: #f8f9fa;
      border-color: #b8b8b8;
      color: #3c4043;
      text-decoration: none;
      transform: translateY(-1px);
      box-shadow: 0px 2px 6px rgba(0,0,0,0.10);
    }

    .google-login-btn img {
      display: block;
    }

  </style>
</head>
<body>
  <div class="login-container">
    <div class="brand">
      <div class="icon">⭐</div>
      <h2>FixTrack</h2>
      <p class="text-muted">Warranty & Repair Booking System</p>
    </div>

    <?php if (!empty($error)): ?>
      <div class="alert alert-danger text-center">
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <form action="" method="POST">
      <div class="mb-3">
        <label class="form-label">User ID</label>
        <input type="text" class="form-control" name="userid" required>
      </div>
      <div class="mb-3 position-relative">
        <label class="form-label">Password</label>
        <div class="input-group">
          <input type="password" class="form-control" name="password" id="loginPassword" required>
          <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('loginPassword', this)">
            <i class="bi bi-eye"></i>
          </button>
        </div>
      </div>

      <button type="submit" class="btn btn-danger w-100 mb-2">
        <i class="bi bi-box-arrow-in-right"></i> Login
      </button>
    </form>

    <div class="divider text-center my-3">
      <span>or</span>
    </div>

    <!-- GOOGLE LOGIN -->
    <a href="googlelogin.php" class="google-login-btn">
      <img 
        src="https://www.gstatic.com/firebasejs/ui/2.0.0/images/auth/google.svg"
        width="20"
        height="20"
        alt="Google"
      >
      <span>Continue with Google</span>
    </a>

    <div class="mt-3 text-center">
      <a href="register.php" class="text-danger fw-bold">
        Create an account
      </a>
    </div>

  <script>
    function togglePassword(fieldId, btn) {
      const input = document.getElementById(fieldId);
      const icon = btn.querySelector("i");
      if (input.type === "password") {
        input.type = "text";
        icon.classList.remove("bi-eye");
        icon.classList.add("bi-eye-slash");
      } else {
        input.type = "password";
        icon.classList.remove("bi-eye-slash");
        icon.classList.add("bi-eye");
      }
    }
  </script>
</body>
</html>
