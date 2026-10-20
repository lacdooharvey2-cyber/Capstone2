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
  <link href="assets/css/auth-theme.css?v=20261014" rel="stylesheet">
</head>
<body class="auth-page">
  <main class="reset-main">
    <div class="text-success fs-2 mb-2"><i class="bi bi-key"></i></div>
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

