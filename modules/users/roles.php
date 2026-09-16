<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/rbac.php';
requirePermission('users.manage');

$pageTitle = 'Roles & permissions';

$roles = $pdo->query("SELECT * FROM roles ORDER BY id")->fetchAll();
$allPermissions = $pdo->query("SELECT * FROM permissions ORDER BY module, id")->fetchAll();

// Group permissions by module for a readable checkbox layout
$grouped = [];
foreach ($allPermissions as $p) {
    $grouped[$p['module']][] = $p;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    $roleId = (int)$_POST['role_id'];
    $selected = array_map('intval', $_POST['permissions'] ?? []);

    $pdo->beginTransaction();
    $pdo->prepare("DELETE FROM role_permissions WHERE role_id = ?")->execute([$roleId]);
    $stmt = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
    foreach ($selected as $permId) {
        $stmt->execute([$roleId, $permId]);
    }
    $pdo->commit();

    // Anyone currently logged in under this role needs their session refreshed to see the change on next request.
    logActivity('users.manage', "Updated permissions for role #$roleId");
    setFlash('success', 'Permissions updated. Affected users will see the change next time they load a page.');
    redirect('modules/users/roles.php?role=' . $roleId);
}

$activeRoleId = (int)($_GET['role'] ?? $roles[0]['id']);
$permStmt = $pdo->prepare("SELECT permission_id FROM role_permissions WHERE role_id = ?");
$permStmt->execute([$activeRoleId]);
$activePermissions = array_column($permStmt->fetchAll(), 'permission_id');

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="tabs">
  <?php foreach ($roles as $r): ?>
    <a href="roles.php?role=<?= $r['id'] ?>" class="tab <?= $r['id'] == $activeRoleId ? 'tab-active' : '' ?>"><?= clean($r['name']) ?></a>
  <?php endforeach; ?>
</div>

<section class="panel">
  <?php if ($activeRoleId == 1): ?>
    <p class="empty-state">The Admin role always has every privilege and can't be restricted, so nobody gets locked out of user management.</p>
  <?php else: ?>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
    <input type="hidden" name="role_id" value="<?= $activeRoleId ?>">

    <?php foreach ($grouped as $module => $perms): ?>
      <div class="permission-group">
        <h3><?= clean(ucfirst($module)) ?></h3>
        <div class="permission-list">
          <?php foreach ($perms as $p): ?>
            <label class="checkbox-row">
              <input type="checkbox" name="permissions[]" value="<?= $p['id'] ?>"
                <?= in_array($p['id'], $activePermissions) ? 'checked' : '' ?>>
              <?= clean($p['label']) ?>
            </label>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>

    <div class="form-actions">
      <button type="submit" class="btn btn-primary">Save permissions</button>
    </div>
  </form>
  <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
