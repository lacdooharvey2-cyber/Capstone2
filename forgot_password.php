<?php
declare(strict_types=1);

session_start();
require 'db.php';
require 'password_reset_helpers.php';
require 'mail_helper.php';
require_once 'stripe_config.php';

$message = '';
$error = '';

try {
    ensurePasswordResetTable($conn);
} catch (Throwable $exception) {
    error_log('FixTrack password reset setup error: ' . $exception->getMessage());
    $error = 'Password reset is temporarily unavailable. Please try again later.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '') {
    $email = trim((string)($_POST['email'] ?? ''));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $stmt = $conn->prepare("SELECT user_id, name, email FROM users WHERE email = ? AND account_status = 'Active' LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user) {
            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $expiresAt = date('Y-m-d H:i:s', time() + 3600);

            $conn->query('UPDATE password_resets SET used_at = NOW() WHERE user_id = ' . (int)$user['user_id'] . ' AND used_at IS NULL');
            $resetStmt = $conn->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, ?)');
            $resetStmt->bind_param('iss', $user['user_id'], $tokenHash, $expiresAt);
            $resetStmt->execute();

            loadStripeEnvironment();
            $baseUrl = rtrim((string)(getenv('STRIPE_BASE_URL') ?: 'http://localhost/RedStar'), '/');
            $resetUrl = $baseUrl . '/reset_password.php?token=' . urlencode($token);
            $safeName = htmlspecialchars((string)$user['name'], ENT_QUOTES, 'UTF-8');
            $safeUrl = htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8');
            $sent = sendFixTrackEmail(
                $user['email'],
                (string)$user['name'],
                'Reset your FixTrack password',
                "<p>Hello {$safeName},</p><p>We received a request to reset your FixTrack password.</p><p><a href=\"{$safeUrl}\">Reset your password</a></p><p>This link expires in 1 hour and can only be used once.</p><p>If you did not request this, you can ignore this email.</p>",
                "Hello {$user['name']},\n\nReset your FixTrack password here: {$resetUrl}\n\nThis link expires in 1 hour and can only be used once."
            );

            if (!$sent) {
                error_log('FixTrack password reset email was not sent for user ID ' . (int)$user['user_id']);
            }
        }

        $message = 'If an active account matches that email, a password reset link has been sent.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Forgot Password - FixTrack</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    :root { --brand-red: #198754; --brand-dark: #1f2937; }
    * { box-sizing: border-box; }
    html { scroll-behavior: smooth; }
    body { min-height: 100vh; margin: 0; background: #f7f8f7; color: var(--brand-dark); font-family: 'Segoe UI', sans-serif; overflow-x: hidden; }
    .auth-shell { min-height: 100vh; display: flex; align-items: stretch; padding: 0; }
    .auth-panel { width: 100%; max-width: none; min-height: 100vh; margin: 0; background: #fff; border: 0; border-radius: 0; overflow: hidden; box-shadow: none; animation: fadeInUp .7s cubic-bezier(.2,.8,.2,1) both; }
    .brand-side { min-height: 100vh; padding: clamp(48px, 8vw, 120px); background: #f7f8f7; border-right: 1px solid #e4e9e5; color: #17221a; display: flex; flex-direction: column; justify-content: center; position: relative; }
    .brand-mark { width: 38px; height: 38px; border-radius: 6px; background: #003b16; color: #fff; display: grid; place-items: center; font-size: 18px; }
    .brand-lockup { display: inline-flex; align-items: center; gap: 10px; color: #003b16; font-size: 1.25rem; font-weight: 800; text-decoration: none; }
    .brand-title { max-width: 420px; font-size: clamp(2.3rem, 4vw, 4rem); font-weight: 800; line-height: 1.08; margin: 42px 0 16px; }
    .brand-copy { max-width: 390px; color: #647068; font-size: 16px; line-height: 1.7; }
    .feature-row { display: grid; gap: 12px; }
    .feature-pill { display: flex; align-items: center; gap: 12px; padding: 12px 14px; background: #e5f8e9; border: 1px solid #b8e4c2; border-radius: 6px; color: #146c43; animation: contentReveal .7s both; transition: transform .2s ease, background-color .2s ease; }
    .feature-pill:hover { background: #d9f2df; transform: translateX(5px); }
    .feature-pill:nth-child(2) { animation-delay: .12s; }
    .feature-pill:nth-child(3) { animation-delay: .2s; }
    .login-side { padding: clamp(48px, 8vw, 120px); display: flex; flex-direction: column; justify-content: center; animation: contentReveal .7s .18s both; }
    .login-side > * { animation: contentReveal .55s both; }
    .login-side > *:nth-child(2) { animation-delay: .08s; }
    .login-side > *:nth-child(3) { animation-delay: .16s; }
    .login-side > *:nth-child(4) { animation-delay: .24s; }
    .login-side form > .input-group { animation: contentReveal .5s .08s both; }
    .login-side form > button { animation: contentReveal .5s .16s both; }
    .login-heading { font-size: 30px; font-weight: 800; margin-bottom: 6px; }
    .form-control, .input-group-text, .btn { min-height: 46px; border-radius: 8px; }
    .input-group .form-control { border-left: 0; }
    .input-group-text { background: #fff; color: #6c757d; }
    .form-control { transition: border-color .28s ease, box-shadow .28s ease, transform .28s cubic-bezier(.22,1,.36,1); }
    .form-control:focus { border-color: var(--brand-red); box-shadow: 0 0 0 .22rem rgba(25,135,84,.14); transform: translateY(-1px); }
    .input-group:focus-within .input-group-text { border-color: var(--brand-red); color: var(--brand-red); }
    .btn-success { font-weight: 700; background: var(--brand-red); border-color: var(--brand-red); transition: transform .2s ease, box-shadow .2s ease; }
    .btn-success:hover { transform: translateY(-1px); box-shadow: 0 12px 24px rgba(25,135,84,.22); }
    .small-link { color: var(--brand-red); font-weight: 700; text-decoration: none; }
    .small-link:hover { text-decoration: underline; }
    @keyframes fadeInUp { from { opacity: 0; transform: translateY(18px); } to { opacity: 1; transform: translateY(0); } }
    @keyframes contentReveal { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    @media (max-width: 991px) { .brand-side { min-height: auto; padding: 34px; } .login-side { padding: 34px; } }
    @media (max-width: 575px) { .brand-side { display: none; } .login-side { padding: 30px 22px; } .login-heading { font-size: 26px; } }
    @media (prefers-reduced-motion: reduce) { *, *::before, *::after { animation-duration: .01ms !important; animation-iteration-count: 1 !important; transition-duration: .01ms !important; } }
  </style>
</head>
<body>
  <main class="auth-shell">
    <section class="auth-panel">
      <div class="row g-0">
        <div class="col-lg-5 brand-side">
          <div>
            <a class="brand-lockup" href="Homepage.php"><span class="brand-mark"><i class="bi bi-wrench-adjustable"></i></span> FixTrack</a>
            <h1 class="brand-title">Account support, made simple.</h1>
            <p class="brand-copy">Use your email to securely restore access to your FixTrack E-bike service account.</p>
          </div>
        </div>
        <div class="col-lg-7 login-side">
          <div class="mb-4">
            <span class="badge text-bg-danger-subtle text-danger border border-danger-subtle mb-3">FixTrack Portal</span>
            <h2 class="login-heading">Forgot password?</h2>
            <p class="text-secondary mb-0">Enter your email and we will send a secure reset link.</p>
          </div>
          <?php if ($error !== ''): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
          <?php if ($message !== ''): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
          <form method="post" novalidate>
            <label for="email" class="form-label fw-semibold">Email address</label>
            <div class="input-group mb-4">
              <span class="input-group-text"><i class="bi bi-envelope"></i></span>
              <input id="email" name="email" type="email" class="form-control" required autocomplete="email" autofocus>
            </div>
            <button class="btn btn-success w-100" type="submit"><i class="bi bi-send me-1"></i>Send reset link</button>
          </form>
          <div class="text-center mt-4"><a href="login.php" class="small-link"><i class="bi bi-arrow-left me-1"></i>Back to login</a></div>
        </div>
      </div>
    </section>
  </main>
</body>
</html>
