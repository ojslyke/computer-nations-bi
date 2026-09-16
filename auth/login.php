<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/rbac.php';

if (isLoggedIn()) {
    redirect('dashboard/index.php');
}

$error = null;
$flash = getFlash();
if ($flash && $flash['type'] === 'error') {
    $error = $flash['message'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Enter both your username and password.';
    } else {
        $stmt = $pdo->prepare(
            "SELECT u.*, r.name AS role_name
             FROM users u JOIN roles r ON r.id = u.role_id
             WHERE u.username = ? AND u.status = 'active'"
        );
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if ($user['role_name'] !== 'Admin' && !isWithinWorkingHours()) {
                $error = workingHoursMessage();
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user'] = [
                    'id'        => $user['id'],
                    'full_name' => $user['full_name'],
                    'username'  => $user['username'],
                    'role_id'   => $user['role_id'],
                    'role_name' => $user['role_name'],
                    'branch_id' => $user['branch_id'],
                ];
                $_SESSION['permissions'] = loadPermissionsForUser($user['id'], $pdo);
                unset($_SESSION['current_branch_id']); // reset branch context on every fresh login

                $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
                logActivity('login', 'User logged in');

                redirect('dashboard/index.php');
            }
        } else {
            $error = 'Incorrect username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Log in — <?= SITE_NAME ?></title>
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
    <p class="auth-tagline">Inventory, orders, purchasing and stock — one dashboard, tailored to what each teammate is allowed to see and do.</p>
    <div class="auth-stats">
      <div><strong>4</strong>role-based dashboards</div>
      <div><strong>16+</strong>granular permissions</div>
      <div><strong>100%</strong>audited stock changes</div>
    </div>
  </div>

  <div class="auth-card">
    <h1>Welcome back</h1>
    <p class="muted">Log in to your dashboard.</p>

    <?php if ($error): ?>
      <div class="alert alert-error"><?= clean($error) ?></div>
    <?php endif; ?>

    <form method="post" novalidate>
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

      <div class="form-field">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" autofocus required>
      </div>

      <div class="form-field">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>
      </div>

      <button type="submit" class="btn btn-primary btn-block">Log in</button>
    </form>

    <p class="muted small" style="margin-top:14px;"><a href="<?= BASE_URL ?>auth/forgot-password.php" class="link">Forgot your password?</a></p>
    <p class="muted small" style="margin-top:6px;">Default admin — username: <code>admin</code> · password: <code>Admin@123</code> (change this after first login)</p>
  </div>
</div>

<script src="<?= BASE_URL ?>assets/js/main.js"></script>
</body>
</html>
