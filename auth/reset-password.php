<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/rbac.php';

if (isLoggedIn()) {
    redirect('dashboard/index.php');
}

$token = $_GET['token'] ?? $_POST['token'] ?? '';
$errors = [];
$success = false;

$user = null;
if ($token !== '') {
    $stmt = $pdo->prepare(
        "SELECT id, full_name FROM users WHERE reset_token_hash = ? AND reset_token_expires > NOW() AND status = 'active'"
    );
    $stmt->execute([hash('sha256', $token)]);
    $user = $stmt->fetch();
}

if (!$user) {
    $errors[] = 'This reset link is invalid or has expired. Request a new one from the login page.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (strlen($newPassword) < 8) {
        $errors[] = 'New password must be at least 8 characters.';
    } elseif ($newPassword !== $confirmPassword) {
        $errors[] = 'New password and confirmation do not match.';
    } else {
        $pdo->prepare("UPDATE users SET password = ?, reset_token_hash = NULL, reset_token_expires = NULL WHERE id = ?")
            ->execute([password_hash($newPassword, PASSWORD_DEFAULT), $user['id']]);
        logActivity('account.password_reset', 'Password reset via emailed link', $user['id']);
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reset password — <?= SITE_NAME ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
</head>
<body class="auth-body">

<div class="auth-screen">
  <div class="auth-panel">
    <div class="sidebar-brand" style="padding:0;">
      <span class="brand-mark">CN</span>
      <span class="brand-name">Computer Nations</span>
    </div>
    <p class="auth-tagline">Set a new password to get back into your dashboard.</p>
  </div>

  <div class="auth-card">
    <h1>Reset password</h1>

    <?php foreach ($errors as $err): ?>
      <div class="alert alert-error"><?= clean($err) ?></div>
    <?php endforeach; ?>

    <?php if ($success): ?>
      <div class="alert alert-success">Password updated. You can log in with it now.</div>
      <p class="muted small" style="margin-top:14px;"><a href="<?= BASE_URL ?>auth/login.php" class="link">Go to login</a></p>
    <?php elseif ($user): ?>
      <p class="muted">Hi <?= clean($user['full_name']) ?> — choose a new password.</p>
      <form method="post" novalidate>
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="token" value="<?= clean($token) ?>">
        <div class="form-field">
          <label for="new_password">New password</label>
          <input type="password" id="new_password" name="new_password" minlength="8" autofocus required>
        </div>
        <div class="form-field">
          <label for="confirm_password">Confirm new password</label>
          <input type="password" id="confirm_password" name="confirm_password" minlength="8" required>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Set new password</button>
      </form>
    <?php else: ?>
      <p class="muted small" style="margin-top:14px;"><a href="<?= BASE_URL ?>auth/forgot-password.php" class="link">Request a new reset link</a></p>
    <?php endif; ?>
  </div>
</div>

</body>
</html>
