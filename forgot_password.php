<?php
declare(strict_types=1);

session_start();
require 'db.php';
require 'password_reset_helpers.php';
require 'mail_helper.php';
require_once 'app_config.php';

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

            loadAppEnvironment();
            $baseUrl = rtrim((string)(getenv('APP_BASE_URL') ?: 'http://localhost/RedStar'), '/');
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
  <link href="assets/css/auth-theme.css?v=20261014" rel="stylesheet">
</head>
<body class="auth-page">
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
        <div class="col-lg-7 auth-form-side">
          <div class="mb-4">
            <span class="auth-badge">FixTrack Portal</span>
            <h2 class="auth-heading">Forgot password?</h2>
            <p class="auth-subtext">Enter your email and we will send a secure reset link.</p>
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

