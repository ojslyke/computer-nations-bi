<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/rbac.php';
requirePermission('users.manage');

$pageTitle = 'Users & roles';

$users = $pdo->query(
    "SELECT u.*, r.name AS role_name, b.name AS branch_name FROM users u JOIN roles r ON r.id = u.role_id LEFT JOIN branches b ON b.id = u.branch_id ORDER BY u.full_name"
)->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="toolbar">
  <p class="muted">Every user is assigned exactly one role. A role's privileges are set on the <a href="roles.php" class="link">Roles &amp; permissions</a> page.</p>
  <a href="add.php" class="btn btn-primary">Add user</a>
</div>

<section class="panel">
  <table class="data-table">
    <thead><tr><th>Name</th><th>Username</th><th>Phone</th><th>Role</th><th>Branch</th><th>Status</th><th>Last login</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($users as $u): ?>
      <tr>
        <td><?= clean($u['full_name']) ?></td>
        <td class="mono"><?= clean($u['username']) ?></td>
        <td class="mono"><?= clean($u['phone'] ?? '—') ?></td>
        <td><?= clean($u['role_name']) ?></td>
        <td><?= clean($u['branch_name'] ?? 'All branches') ?></td>
        <td><span class="badge badge-<?= $u['status'] === 'active' ? 'completed' : 'cancelled' ?>"><?= clean($u['status']) ?></span></td>
        <td class="mono"><?= $u['last_login'] ? date('d M Y, H:i', strtotime($u['last_login'])) : 'Never' ?></td>
        <td class="row-actions"><a href="edit.php?id=<?= (int)$u['id'] ?>" class="link">Edit</a></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
