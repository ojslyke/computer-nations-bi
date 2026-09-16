<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/rbac.php';
requirePermission('users.manage');

$pageTitle = 'Add user';
$errors = [];
$roles = $pdo->query("SELECT id, name FROM roles ORDER BY id")->fetchAll();
$branchesByTown = listBranchesByTown($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();

    $fullName = trim($_POST['full_name']);
    $username = trim($_POST['username']);
    $email    = trim($_POST['email']);
    $phone    = trim($_POST['phone'] ?? '');
    $password = $_POST['password'];
    $roleId   = (int)$_POST['role_id'];
    $branchId = $_POST['branch_id'] !== '' ? (int)$_POST['branch_id'] : null;

    if ($fullName === '' || $username === '' || $email === '' || $password === '') {
        $errors[] = 'All fields are required.';
    } elseif (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }

    if (!$errors) {
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare(
                "INSERT INTO users (full_name, username, email, phone, password, role_id, branch_id) VALUES (?,?,?,?,?,?,?)"
            )->execute([$fullName, $username, $email, $phone ?: null, $hash, $roleId, $branchId]);

            logActivity('users.manage', "Created user: $username");
            setFlash('success', "$fullName was added.");
            redirect('modules/users/index.php');
        } catch (PDOException $e) {
            $errors[] = $e->getCode() === '23000' ? 'That username or email is already taken.' : 'Could not create user.';
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<section class="panel panel-form">
  <h2>Add user</h2>
  <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= clean($err) ?></div><?php endforeach; ?>

  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
    <div class="form-row">
      <div class="form-field"><label>Full name</label><input type="text" name="full_name" value="<?= clean($_POST['full_name'] ?? '') ?>" required></div>
      <div class="form-field"><label>Username</label><input type="text" name="username" value="<?= clean($_POST['username'] ?? '') ?>" required></div>
    </div>
    <div class="form-row">
      <div class="form-field"><label>Email</label><input type="email" name="email" value="<?= clean($_POST['email'] ?? '') ?>" required></div>
      <div class="form-field"><label>Phone</label><input type="text" name="phone" value="<?= clean($_POST['phone'] ?? '') ?>" placeholder="e.g. 677000111"></div>
    </div>
    <div class="form-row">
      <div class="form-field"><label>Temporary password</label><input type="password" name="password" required minlength="8"></div>
    </div>
    <div class="form-field">
      <label>Role</label>
      <select name="role_id" required>
        <?php foreach ($roles as $r): ?>
          <option value="<?= $r['id'] ?>"><?= clean($r['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-field">
      <label>Branch</label>
      <select name="branch_id">
        <option value="">All branches (head office / roaming staff)</option>
        <?php foreach ($branchesByTown as $townName => $townBranches): ?>
          <optgroup label="<?= clean($townName) ?>">
            <?php foreach ($townBranches as $b): ?>
              <option value="<?= $b['id'] ?>"><?= clean($b['name']) ?></option>
            <?php endforeach; ?>
          </optgroup>
        <?php endforeach; ?>
      </select>
      <span class="form-hint">Pin this person to one branch, or leave as "All branches" for HQ-level roles who can switch between locations.</span>
    </div>
    <div class="form-actions">
      <a href="index.php" class="btn btn-secondary">Cancel</a>
      <button type="submit" class="btn btn-primary">Create user</button>
    </div>
  </form>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
