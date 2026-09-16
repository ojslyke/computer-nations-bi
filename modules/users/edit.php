<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/rbac.php';
requirePermission('users.manage');

$pageTitle = 'Edit user';
$errors = [];

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$editUser = $stmt->fetch();

if (!$editUser) {
    setFlash('error', 'User not found.');
    redirect('modules/users/index.php');
}

$roles = $pdo->query("SELECT id, name FROM roles ORDER BY id")->fetchAll();
$branchesByTown = listBranchesByTown($pdo);
$isSelf = $id === (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();

    $fullName = trim($_POST['full_name']);
    $email    = trim($_POST['email']);
    $phone    = trim($_POST['phone'] ?? '');
    $roleId   = (int)$_POST['role_id'];
    $branchId = ($_POST['branch_id'] ?? '') !== '' ? (int)$_POST['branch_id'] : null;
    $status   = $_POST['status'] === 'suspended' ? 'suspended' : 'active';
    $newPassword = $_POST['new_password'] ?? '';

    if ($isSelf && $status === 'suspended') {
        $errors[] = "You can't suspend your own account.";
    }
    if ($isSelf && $branchId !== $editUser['branch_id']) {
        $errors[] = "You can't change your own branch assignment — ask another admin.";
    }
    if ($fullName === '' || $email === '') {
        $errors[] = 'Name and email are required.';
    }
    if ($newPassword !== '' && strlen($newPassword) < 8) {
        $errors[] = 'New password must be at least 8 characters.';
    }

    if (!$errors) {
        $pdo->prepare("UPDATE users SET full_name=?, email=?, phone=?, role_id=?, branch_id=?, status=? WHERE id=?")
            ->execute([$fullName, $email, $phone ?: null, $roleId, $branchId, $status, $id]);

        if ($newPassword !== '') {
            $pdo->prepare("UPDATE users SET password=? WHERE id=?")
                ->execute([password_hash($newPassword, PASSWORD_DEFAULT), $id]);
        }

        // If the admin edits their own account, refresh session permissions immediately
        if ($isSelf) {
            $_SESSION['permissions'] = loadPermissionsForUser($id, $pdo);
            $_SESSION['user']['full_name'] = $fullName;
        }

        logActivity('users.manage', "Updated user: {$editUser['username']}");
        setFlash('success', 'User updated.');
        redirect('modules/users/index.php');
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<section class="panel panel-form">
  <h2>Edit user <span class="mono muted">@<?= clean($editUser['username']) ?></span></h2>
  <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= clean($err) ?></div><?php endforeach; ?>

  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
    <input type="hidden" name="id" value="<?= (int)$editUser['id'] ?>">

    <div class="form-row">
      <div class="form-field"><label>Full name</label><input type="text" name="full_name" value="<?= clean($editUser['full_name']) ?>" required></div>
      <div class="form-field"><label>Email</label><input type="email" name="email" value="<?= clean($editUser['email']) ?>" required></div>
    </div>

    <div class="form-row">
      <div class="form-field"><label>Phone</label><input type="text" name="phone" value="<?= clean($editUser['phone'] ?? '') ?>" placeholder="e.g. 677000111"></div>
    </div>

    <div class="form-row">
      <div class="form-field">
        <label>Role</label>
        <select name="role_id" required>
          <?php foreach ($roles as $r): ?>
            <option value="<?= $r['id'] ?>" <?= $r['id'] == $editUser['role_id'] ? 'selected' : '' ?>><?= clean($r['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-field">
        <label>Branch</label>
        <select name="branch_id" <?= $isSelf ? 'disabled' : '' ?>>
          <option value="">All branches (head office / roaming staff)</option>
          <?php foreach ($branchesByTown as $townName => $townBranches): ?>
            <optgroup label="<?= clean($townName) ?>">
              <?php foreach ($townBranches as $b): ?>
                <option value="<?= $b['id'] ?>" <?= $b['id'] == $editUser['branch_id'] ? 'selected' : '' ?>><?= clean($b['name']) ?></option>
              <?php endforeach; ?>
            </optgroup>
          <?php endforeach; ?>
        </select>
        <?php if ($isSelf): ?>
          <input type="hidden" name="branch_id" value="<?= clean($editUser['branch_id'] ?? '') ?>">
          <span class="form-hint">Ask another admin to move you between branches.</span>
        <?php endif; ?>
      </div>
    </div>

    <div class="form-row">
      <div class="form-field">
        <label>Status</label>
        <select name="status" <?= $isSelf ? 'disabled' : '' ?>>
          <option value="active" <?= $editUser['status'] === 'active' ? 'selected' : '' ?>>Active</option>
          <option value="suspended" <?= $editUser['status'] === 'suspended' ? 'selected' : '' ?>>Suspended</option>
        </select>
        <?php if ($isSelf): ?><input type="hidden" name="status" value="active"><p class="muted small">You can't suspend your own account.</p><?php endif; ?>
      </div>
      <div class="form-field">
        <label>Reset password (optional)</label>
        <input type="password" name="new_password" minlength="8" placeholder="Leave blank to keep current password">
        <span class="form-hint">Staff don't need email-based recovery — set a new password for them here directly.</span>
      </div>
    </div>

    <div class="form-actions">
      <a href="index.php" class="btn btn-secondary">Cancel</a>
      <button type="submit" class="btn btn-primary">Save changes</button>
    </div>
  </form>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
