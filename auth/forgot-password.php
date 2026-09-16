<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/rbac.php';

if (isLoggedIn()) {
    redirect('dashboard/index.php');
}

$submitted = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    $email = trim($_POST['email'] ?? '');

    if ($email !== '') {
        $stmt = $pdo->prepare(
            "SELECT u.id, u.full_name FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE u.email = ? AND r.name = 'Admin' AND u.status = 'active'"
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Only ever act for a real Admin account — but the response to the
        // person is identical either way, so no one can use this form to
        // discover whether an email exists or which role it belongs to.
        if ($user) {
            $token = bin2hex(random_bytes(32));
            $pdo->prepare("UPDATE users SET reset_token_hash = ?, reset_token_expires = ? WHERE id = ?")
                ->execute([hash('sha256', $token), date('Y-m-d H:i:s', strtotime('+1 hour')), $user['id']]);

            $resetLink = BASE_URL . 'auth/reset-password.php?token=' . $token;
            $body = "Hi {$user['full_name']},\n\n"
                . "A password reset was requested for your " . SITE_NAME . " admin account.\n\n"
                . "Reset your password here (valid for 1 hour):\n$resetLink\n\n"
                . "If you didn't request this, you can ignore this email — your password won't change.";
            sendAppEmail($email, 'Reset your ' . SITE_NAME . ' password', $body);

            logActivity('account.password_reset_requested', 'Password reset link requested (via forgot-password form)', $user['id']);
        }
    }

    $submitted = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Forgot password — <?= SITE_NAME ?></title>
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
    <p class="auth-tagline">Password recovery by email is for administrator accounts. Other staff should ask their administrator to reset their password from Users &amp; roles — no email needed.</p>
  </div>

  <div class="auth-card">
    <h1>Forgot your password?</h1>

    <?php if ($submitted): ?>
      <div class="alert alert-success">If that's an administrator account, a reset link has been sent to it — check your inbox (and spam folder). The link expires in 1 hour.</div>
      <p class="muted small" style="margin-top:14px;"><a href="<?= BASE_URL ?>auth/login.php" class="link">Back to login</a></p>
    <?php else: ?>
      <p class="muted">Enter the email on your administrator account and we'll send a reset link.</p>
      <form method="post" novalidate>
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <div class="form-field">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" autofocus required>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Send reset link</button>
      </form>
      <p class="muted small" style="margin-top:14px;"><a href="<?= BASE_URL ?>auth/login.php" class="link">Back to login</a></p>
    <?php endif; ?>
  </div>
</div>

</body>
</html>
