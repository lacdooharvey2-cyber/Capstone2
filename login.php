<?php
session_start();
include("db.php");
include_once("activity_log_helper.php");

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

    $sql = "SELECT * FROM users WHERE custom_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $userid);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if (password_verify($password, $row['password_hash'])) {
            $_SESSION['user_id'] = $row['user_id'];
            $_SESSION['custom_id'] = $row['custom_id'];
            $_SESSION['role'] = $row['role'];
            logActivity($conn, (int)$row['user_id'], (string)$row['role'], 'Login', 'Signed in to FixTrack.');

            switch ($row['role']) {
                case 'AssistantAdmin':
                case 'Admin':
                case 'AssistantSuperAdmin':
                case 'SuperAdmin':
                    header("Location: admindashboard.php");
                    break;
                case 'HeadTechnician':
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
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>FixTrack - Login</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/css/auth-theme.css?v=20261014" rel="stylesheet">
</head>
<body class="auth-page">
  <main class="auth-shell">
    <section class="auth-panel">
      <div class="row g-0">
        <div class="col-lg-6 brand-side">
          <div>
            <a class="brand-lockup" href="Homepage.php">
              <span class="brand-mark"><i class="bi bi-wrench-adjustable"></i></span> FixTrack
            </a>
            <h1 class="brand-title">Built for smoother<br>E-bike service.</h1>
            <p class="brand-copy">Book repairs, track service progress, and manage warranty coverage in one clear workspace.</p>
          </div>
        </div>

        <div class="col-lg-6 auth-form-side">
          <div class="mb-4">
            <a href="Homepage.php" class="back-link d-inline-flex align-items-center gap-2 mb-4">
              <i class="bi bi-arrow-left"></i> Back to home
            </a>
            <span class="auth-badge">FixTrack Portal</span>
            <h2 class="auth-heading">Welcome back</h2>
            <p class="auth-subtext">Login to book and track your e-bike repair.</p>
          </div>

          <?php if (!empty($error)): ?>
            <div class="alert alert-danger d-flex align-items-center gap-2" role="alert">
              <i class="bi bi-exclamation-triangle-fill"></i>
              <div><?= htmlspecialchars($error) ?></div>
            </div>
          <?php endif; ?>

          <form action="" method="POST">
            <div class="mb-3">
              <label class="form-label fw-semibold">User ID</label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-person-badge"></i></span>
                <input type="text" class="form-control" name="userid" placeholder="Example: NWOW-0001" required>
              </div>
            </div>
            <div class="mb-4">
              <label class="form-label fw-semibold">Password</label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                <input type="password" class="form-control" name="password" id="loginPassword" placeholder="Enter your password" required>
                <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('loginPassword', this)" aria-label="Show password">
                  <i class="bi bi-eye"></i>
                </button>
              </div>
            </div>

            <button type="submit" class="btn btn-success w-100 mb-3">
              <i class="bi bi-box-arrow-in-right me-1"></i> Login
            </button>
          </form>

          <div class="text-center mb-2">
            <a href="forgot_password.php" class="small-link">Forgot password?</a>
          </div>

          <div class="divider my-3">
            <span>or</span>
          </div>

          <a href="googlelogin.php" class="google-login-btn">
            <img 
              src="https://www.gstatic.com/firebasejs/ui/2.0.0/images/auth/google.svg"
              width="20"
              height="20"
              alt="Google"
            >
            <span>Continue with Google</span>
          </a>

          <div class="mt-4 text-center">
            <span class="text-muted">No account yet?</span>
            <a href="register.php" class="small-link ms-1">Create an account</a>
          </div>
        </div>
      </div>
    </section>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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
