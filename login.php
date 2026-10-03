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
  <style>
    :root {
      --app-primary: #198754;
      --app-primary-dark: #1f2937;
    }

    * {
      box-sizing: border-box;
    }

    html { scroll-behavior: smooth; }

    body {
      min-height: 100vh;
      background: #f7f8f7;
      color: var(--app-primary-dark);
      font-family: system-ui, "Segoe UI", Roboto, sans-serif;
      overflow-x: hidden;
    }

    .auth-shell {
      min-height: 100vh;
      display: flex;
      align-items: stretch;
      padding: 0;
    }

    .auth-panel {
      width: 100%;
      max-width: none;
      min-height: 100vh;
      margin: 0;
      background: #fff;
      border: 0;
      border-radius: 0;
      overflow: hidden;
      box-shadow: none;
      animation: fadeInUp 0.7s cubic-bezier(.2,.8,.2,1) both;
    }

    .auth-panel > .row {
      min-height: 100vh;
    }

    @keyframes fadeInUp {
      from { opacity: 0; transform: translateY(18px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .brand-side {
      min-height: 100vh;
      padding: clamp(48px, 8vw, 120px);
      background: #f7f8f7;
      border-right: 1px solid #e4e9e5;
      color: #17221a;
      display: flex;
      flex-direction: column;
      justify-content: center;
      position: relative;
    }

    .brand-mark {
      width: 38px;
      height: 38px;
      border-radius: 6px;
      background: #003b16;
      color: #fff;
      display: grid;
      place-items: center;
      font-size: 18px;
    }

    .brand-lockup {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      color: #003b16;
      font-size: 1.25rem;
      font-weight: 800;
      text-decoration: none;
    }

    .brand-title {
      max-width: 420px;
      font-size: clamp(2.3rem, 4vw, 4rem);
      font-weight: 800;
      letter-spacing: 0;
      line-height: 1.08;
      margin: 42px 0 16px;
    }

    .brand-copy {
      max-width: 390px;
      color: #647068;
      font-size: 16px;
      line-height: 1.7;
    }

    .feature-row {
      display: grid;
      gap: 12px;
    }

    .feature-pill {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 12px 14px;
      background: #e5f8e9;
      border: 1px solid #b8e4c2;
      border-radius: 6px;
      color: #146c43;
      transition: transform .2s ease, background-color .2s ease;
    }

    .feature-pill:hover {
      background: #d9f2df;
      transform: translateX(5px);
    }

    .feature-pill:nth-child(2) { animation-delay: .12s; }
    .feature-pill:nth-child(3) { animation-delay: .2s; }

    @keyframes contentReveal {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .login-side {
      min-height: 100vh;
      padding: clamp(48px, 8vw, 120px);
      display: flex;
      flex-direction: column;
      justify-content: center;
      animation: contentReveal .7s .18s both;
    }

    .login-side > * { animation: contentReveal .55s both; }
    .login-side > *:nth-child(2) { animation-delay: .08s; }
    .login-side > *:nth-child(3) { animation-delay: .16s; }
    .login-side > *:nth-child(4) { animation-delay: .24s; }
    .login-side > *:nth-child(5) { animation-delay: .32s; }
    .login-side > *:nth-child(6) { animation-delay: .4s; }
    .login-side form > div { animation: contentReveal .5s both; }
    .login-side form > div:nth-child(2) { animation-delay: .08s; }
    .login-side form > button { animation: contentReveal .5s .16s both; }

    .login-heading {
      font-size: 30px;
      font-weight: 800;
      letter-spacing: 0;
      margin-bottom: 6px;
    }

    .form-control,
    .input-group-text,
    .btn {
      min-height: 46px;
      border-radius: 8px;
    }

    .input-group .form-control {
      border-left: 0;
    }

    .input-group-text {
      background: #fff;
      color: #6c757d;
    }

    .form-control:focus {
      border-color: var(--app-primary);
      box-shadow: 0 0 0 .22rem rgba(25, 135, 84, .14);
    }

    .form-control,
    .input-group-text,
    .btn,
    .small-link,
    .google-login-btn {
      transition: border-color .28s ease, color .28s ease, background-color .28s ease, box-shadow .28s ease, transform .28s cubic-bezier(.22,1,.36,1);
    }

    .input-group:focus-within .input-group-text {
      border-color: var(--app-primary);
      color: var(--app-primary);
    }

    .btn-danger {
      font-weight: 700;
      background: var(--app-primary);
      border-color: var(--app-primary);
      transition: transform .2s ease, box-shadow .2s ease, background-color .2s ease;
    }

    .btn-danger:hover {
      transform: translateY(-1px);
      box-shadow: 0 12px 24px rgba(25,135,84,0.22);
    }

    .divider {
      display: flex;
      align-items: center;
      gap: 14px;
      color: #6c757d;
      font-size: 14px;
    }

    .divider::before,
    .divider::after {
      content: "";
      flex: 1;
      height: 1px;
      background: #dee2e6;
    }

    .google-login-btn {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      width: 100%;
      min-height: 46px;
      padding: 10px 15px;
      border: 1px solid #dadce0;
      border-radius: 8px;
      background-color: #fff;
      color: #3c4043;
      text-decoration: none;
      font-size: 15px;
      font-weight: 700;
      transition: all 0.2s ease;
    }

    .google-login-btn:hover {
      background-color: #f8f9fa;
      border-color: #b8b8b8;
      color: #3c4043;
      text-decoration: none;
      transform: translateY(-1px);
      box-shadow: 0 8px 20px rgba(0,0,0,0.10);
    }

    .google-login-btn img {
      display: block;
    }

    .small-link {
      color: var(--app-primary);
      font-weight: 700;
      text-decoration: none;
    }

    .small-link:hover {
      text-decoration: underline;
    }

    .auth-panel .badge.text-bg-danger-subtle {
      background: #e5f8e9 !important;
      color: #146c43 !important;
      border-color: #b8e4c2 !important;
    }

    .auth-panel a.text-danger {
      color: var(--app-primary) !important;
    }

    @media (max-width: 991px) {
      .brand-side {
        min-height: auto;
        padding: 34px;
      }

      .login-side {
        padding: 34px;
      }

      .brand-title {
        font-size: 34px;
      }
    }

    @media (max-width: 575px) {
      .brand-side {
        display: none;
      }

      .auth-panel {
        border-radius: 0;
      }

      .login-side {
        padding: 28px 20px;
      }
    }

    @media (prefers-reduced-motion: reduce) {
      *, *::before, *::after {
        animation-duration: .01ms !important;
        animation-iteration-count: 1 !important;
        scroll-behavior: auto !important;
        transition-duration: .01ms !important;
      }
    }
  </style>
</head>
<body>
  <main class="auth-shell">
    <section class="auth-panel">
      <div class="row g-0">
        <div class="col-lg-6 brand-side">
          <div>
            <a class="brand-lockup" href="Homepage.php">
              <span class="brand-mark"><i class="bi bi-wrench-adjustable"></i></span> FixTrack
            </a>
            <h1 class="brand-title">Built for smoother E-bike service.</h1>
            <p class="brand-copy">Book repairs, track service progress, and manage warranty coverage in one clear workspace.</p>
          </div>
        </div>

        <div class="col-lg-6 login-side">
          <div class="mb-4">
            <a href="Homepage.php" class="small text-success text-decoration-none d-inline-flex align-items-center gap-2 mb-4">
              <i class="bi bi-arrow-left"></i> Back to home
            </a>
            <br>
            <span class="badge text-bg-danger-subtle text-danger border border-danger-subtle mb-3">FixTrack Portal</span>
            <h2 class="login-heading">Welcome back</h2>
            <p class="text-muted mb-0">Login to book and track your e-bike repair.</p>
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

            <button type="submit" class="btn btn-danger w-100 mb-3">
              <i class="bi bi-box-arrow-in-right me-1"></i> Login
            </button>
          </form>

          <div class="text-center mb-2">
            <a href="forgot_password.php" class="small text-danger text-decoration-none">Forgot password?</a>
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

