<?php
declare(strict_types=1);

require 'db.php';
require 'password_reset_helpers.php';

$token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
$error = '';
$message = '';
$reset = null;

try {
    ensurePasswordResetTable($conn);
    if ($token !== '') {
        $tokenHash = hash('sha256', $token);
        $stmt = $conn->prepare("SELECT reset_id, user_id FROM password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW() LIMIT 1");
        $stmt->bind_param('s', $tokenHash);
        $stmt->execute();
        $reset = $stmt->get_result()->fetch_assoc();
    }
} catch (Throwable $exception) {
    error_log('FixTrack password reset validation error: ' . $exception->getMessage());
    $error = 'Password reset is temporarily unavailable. Please try again later.';
}

if ($error === '' && !$reset) {
    $error = 'This reset link is invalid, expired, or already used.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '') {
    $password = (string)($_POST['password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    if (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $update = $conn->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?');
        $update->bind_param('si', $hash, $reset['user_id']);
        $update->execute();

        $consume = $conn->prepare('UPDATE password_resets SET used_at = NOW() WHERE reset_id = ?');
        $consume->bind_param('i', $reset['reset_id']);
        $consume->execute();
        $message = 'Your password has been changed. You can now log in.';
        $reset = null;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Reset Password - FixTrack</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    html { scroll-behavior: smooth; }
    body { min-height: 100vh; display: grid; place-items: stretch; padding: 0; background: #f7f8f7; font-family: system-ui, "Segoe UI", Roboto, sans-serif; }
    .reset-card { width: 100%; max-width: none; min-height: 100vh; display: flex; flex-direction: column; justify-content: center; background: #fff; border: 0; border-radius: 0; padding: clamp(40px, 9vw, 140px); box-shadow: none; animation: resetRise .6s cubic-bezier(.2,.8,.2,1) both; }
    .reset-card > * { width: 100%; max-width: 520px; margin-left: auto; margin-right: auto; }
    .reset-card > * { animation: resetReveal .5s both; }
    .reset-card > *:nth-child(2) { animation-delay: .08s; }
    .reset-card > *:nth-child(3) { animation-delay: .16s; }
    .reset-card > *:nth-child(4) { animation-delay: .24s; }
    .reset-card .form-control { transition: border-color .28s ease, box-shadow .28s ease, transform .28s cubic-bezier(.22,1,.36,1); }
    .reset-card .form-control:focus { border-color: #198754; box-shadow: 0 0 0 .22rem rgba(25,135,84,.14); transform: translateY(-1px); }
    .reset-card .btn-success { transition: transform .28s cubic-bezier(.22,1,.36,1), box-shadow .28s ease; }
    .reset-card .btn-success:hover { transform: translateY(-1px); box-shadow: 0 12px 24px rgba(25,135,84,.22); }
    @keyframes resetRise { from { opacity: 0; transform: translateY(18px) scale(.98); } to { opacity: 1; transform: translateY(0) scale(1); } }
    @keyframes resetReveal { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
    @media (prefers-reduced-motion: reduce) { *, *::before, *::after { animation-duration: .01ms !important; animation-iteration-count: 1 !important; transition-duration: .01ms !important; } }
  </style>
</head>
<body>
  <main class="reset-card">
    <div class="text-danger fs-2 mb-2"><i class="bi bi-key"></i></div>
    <h1 class="h3 fw-bold mb-2">Create a new password</h1>
    <?php if ($error !== ''): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($message !== ''): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><a href="login.php" class="btn btn-success w-100">Back to login</a><?php endif; ?>
    <?php if ($reset): ?>
      <p class="text-secondary mb-4">Use at least 8 characters for your new password.</p>
      <form method="post">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
        <label class="form-label fw-semibold" for="password">New password</label>
        <input class="form-control mb-3" id="password" name="password" type="password" minlength="8" required autocomplete="new-password">
        <label class="form-label fw-semibold" for="confirm_password">Confirm password</label>
        <input class="form-control mb-4" id="confirm_password" name="confirm_password" type="password" minlength="8" required autocomplete="new-password">
        <button class="btn btn-success w-100" type="submit"><i class="bi bi-check2-circle me-1"></i>Update password</button>
      </form>
    <?php elseif ($message === ''): ?>
      <a href="forgot_password.php" class="btn btn-success w-100">Request a new reset link</a>
    <?php endif; ?>
  </main>
</body>
</html>

