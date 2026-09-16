<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/rbac.php';
require_once __DIR__ . '/includes/icons.php';
requireLogin();

$pageTitle = 'Account settings';
$errors = [];
$userId = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$account = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    $formType = $_POST['form_type'] ?? '';

    if ($formType === 'profile') {
        $fullName = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone'] ?? '');

        if ($fullName === '' || $email === '') {
            $errors[] = 'Name and email are required.';
        } else {
            try {
                $pdo->prepare("UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id = ?")->execute([$fullName, $email, $phone ?: null, $userId]);
                $_SESSION['user']['full_name'] = $fullName;
                logActivity('account.update', 'Updated own profile');
                setFlash('success', 'Profile updated.');
                redirect('account.php');
            } catch (PDOException $e) {
                $errors[] = 'That email is already in use.';
            }
        }
    }

    if ($formType === 'password') {
        $current = $_POST['current_password'];
        $new = $_POST['new_password'];
        $confirm = $_POST['confirm_password'];

        if (!password_verify($current, $account['password'])) {
            $errors[] = 'Your current password is incorrect.';
        } elseif (strlen($new) < 8) {
            $errors[] = 'New password must be at least 8 characters.';
        } elseif ($new !== $confirm) {
            $errors[] = 'New password and confirmation do not match.';
        } else {
            $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")
                ->execute([password_hash($new, PASSWORD_DEFAULT), $userId]);
            logActivity('account.password', 'Changed own password');
            setFlash('success', 'Password changed.');
            redirect('account.php');
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="panel-grid">

  <section class="panel panel-form">
    <h2>Profile</h2>
    <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= clean($err) ?></div><?php endforeach; ?>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
      <input type="hidden" name="form_type" value="profile">
      <div class="form-field">
        <label>Full name</label>
        <input type="text" name="full_name" value="<?= clean($account['full_name']) ?>" required>
      </div>
      <div class="form-field">
        <label>Email</label>
        <input type="email" name="email" value="<?= clean($account['email']) ?>" required>
      </div>
      <div class="form-field">
        <label>Phone</label>
        <input type="text" name="phone" value="<?= clean($account['phone'] ?? '') ?>" placeholder="e.g. 677000111">
      </div>
      <div class="form-field">
        <label>Username</label>
        <input type="text" value="<?= clean($account['username']) ?>" disabled>
        <span class="form-hint">Usernames can't be changed. Ask an admin if you need this updated.</span>
      </div>
      <div class="form-field">
        <label>Role</label>
        <input type="text" value="<?= clean($_SESSION['user']['role_name']) ?>" disabled>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary">Save profile</button>
      </div>
    </form>
  </section>

  <section class="panel panel-form">
    <h2>Change password</h2>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
      <input type="hidden" name="form_type" value="password">
      <div class="form-field">
        <label>Current password</label>
        <input type="password" name="current_password" required>
      </div>
      <div class="form-field">
        <label>New password</label>
        <input type="password" name="new_password" minlength="8" required>
      </div>
      <div class="form-field">
        <label>Confirm new password</label>
        <input type="password" name="confirm_password" minlength="8" required>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary">Update password</button>
      </div>
    </form>
  </section>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
