<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/rbac.php';
requirePermission('sav.view');

$pageTitle = 'Technicians';
$canManage = hasPermission('sav.manage');
$errors = [];
$editing = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canManage) {
    csrfCheck();
    $action = $_POST['action'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if ($action === 'delete') {
        $inUse = $pdo->prepare("SELECT COUNT(*) c FROM sav_activities WHERE technician_id = ?");
        $inUse->execute([(int)$_POST['id']]);
        if ($inUse->fetch()['c'] > 0) {
            setFlash('error', "Can't remove a technician with SAV history — their record stays for traceability.");
        } else {
            $pdo->prepare("DELETE FROM technicians WHERE id = ?")->execute([(int)$_POST['id']]);
            logActivity('sav.manage', 'Deleted technician #' . (int)$_POST['id']);
            setFlash('success', 'Technician removed.');
        }
        redirect('modules/technicians/index.php');
    }

    if ($name === '') {
        $errors[] = 'Technician name is required.';
    } elseif (!$errors) {
        if ($action === 'update' && !empty($_POST['id'])) {
            $pdo->prepare("UPDATE technicians SET name=?, phone=?, address=?, notes=? WHERE id=?")
                ->execute([$name, $phone, $address, $notes, (int)$_POST['id']]);
            setFlash('success', 'Technician updated.');
        } else {
            $pdo->prepare("INSERT INTO technicians (name, phone, address, notes, created_by) VALUES (?,?,?,?,?)")
                ->execute([$name, $phone, $address, $notes, $_SESSION['user_id']]);
            setFlash('success', 'Technician added.');
        }
        logActivity('sav.manage', "Saved technician: $name");
        redirect('modules/technicians/index.php');
    }
}

if ($canManage && isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM technicians WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editing = $stmt->fetch();
}

$technicians = $pdo->query(
    "SELECT t.*, u.full_name AS creator_name,
            (SELECT COUNT(*) FROM sav_activities WHERE technician_id = t.id) AS jobs_count
     FROM technicians t LEFT JOIN users u ON u.id = t.created_by ORDER BY t.name"
)->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<?php if ($canManage): ?>
<section class="panel panel-form">
  <h2><?= $editing ? 'Edit technician' : 'Add technician' ?></h2>
  <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= clean($err) ?></div><?php endforeach; ?>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
    <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
    <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int)$editing['id'] ?>"><?php endif; ?>
    <div class="form-row">
      <div class="form-field"><label>Name</label><input type="text" name="name" value="<?= clean($editing['name'] ?? '') ?>" placeholder="e.g. Paul's Electronics Repair" required></div>
      <div class="form-field"><label>Phone</label><input type="text" name="phone" value="<?= clean($editing['phone'] ?? '') ?>"></div>
    </div>
    <div class="form-field"><label>Address</label><input type="text" name="address" value="<?= clean($editing['address'] ?? '') ?>"></div>
    <div class="form-field"><label>Notes</label><input type="text" name="notes" value="<?= clean($editing['notes'] ?? '') ?>" placeholder="e.g. Specializes in laptop screens"></div>
    <div class="form-actions">
      <?php if ($editing): ?><a href="index.php" class="btn btn-secondary">Cancel</a><?php endif; ?>
      <button type="submit" class="btn btn-primary"><?= $editing ? 'Save changes' : 'Add technician' ?></button>
    </div>
  </form>
</section>
<?php endif; ?>

<section class="panel">
  <?php if ($technicians): ?>
  <div class="data-table-wrap">
  <table class="data-table">
    <thead><tr><th>Name</th><th>Phone</th><th>Address</th><th>SAV jobs</th><th>Added by</th><?php if ($canManage): ?><th></th><?php endif; ?></tr></thead>
    <tbody>
      <?php foreach ($technicians as $t): ?>
      <tr>
        <td><?= clean($t['name']) ?><?php if ($t['notes']): ?><br><span class="muted small"><?= clean($t['notes']) ?></span><?php endif; ?></td>
        <td class="mono"><?= clean($t['phone'] ?? '—') ?></td>
        <td><?= clean($t['address'] ?? '—') ?></td>
        <td class="mono"><?= (int)$t['jobs_count'] ?></td>
        <td class="muted small"><?= clean($t['creator_name'] ?? '—') ?></td>
        <?php if ($canManage): ?>
        <td class="row-actions">
          <a href="index.php?edit=<?= (int)$t['id'] ?>" class="link">Edit</a>
          <form method="post" class="js-confirm" data-confirm-title="Remove this technician?" data-confirm-message="Only possible if they have no SAV history." style="display:inline">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
            <button type="submit" class="link link-danger">Remove</button>
          </form>
        </td>
        <?php endif; ?>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php else: ?>
    <p class="empty-state">No technicians yet.</p>
  <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
