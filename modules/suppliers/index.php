<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/rbac.php';
requirePermission('suppliers.view');

$pageTitle = 'Suppliers';
$canManage = hasPermission('suppliers.manage');
$errors = [];
$editing = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canManage) {
    csrfCheck();
    $action = $_POST['action'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $contact = trim($_POST['contact_person'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if ($action === 'delete') {
        $pdo->prepare("DELETE FROM suppliers WHERE id = ?")->execute([(int)$_POST['id']]);
        logActivity('suppliers.manage', 'Deleted supplier #' . (int)$_POST['id']);
        setFlash('success', 'Supplier removed.');
        redirect('modules/suppliers/index.php');
    }

    if ($name === '') {
        $errors[] = 'Supplier name is required.';
    } elseif (!$errors) {
        if ($action === 'update' && !empty($_POST['id'])) {
            $pdo->prepare("UPDATE suppliers SET name=?, contact_person=?, phone=?, email=?, address=? WHERE id=?")
                ->execute([$name, $contact, $phone, $email, $address, (int)$_POST['id']]);
            setFlash('success', 'Supplier updated.');
        } else {
            $pdo->prepare("INSERT INTO suppliers (name, contact_person, phone, email, address, created_by) VALUES (?,?,?,?,?,?)")
                ->execute([$name, $contact, $phone, $email, $address, $_SESSION['user_id']]);
            setFlash('success', 'Supplier added.');
        }
        logActivity('suppliers.manage', "Saved supplier: $name");
        redirect('modules/suppliers/index.php');
    }
}

if ($canManage && isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM suppliers WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editing = $stmt->fetch();
}

$suppliers = $pdo->query(
    "SELECT s.*, u.full_name AS creator_name FROM suppliers s LEFT JOIN users u ON u.id = s.created_by ORDER BY s.name"
)->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<?php if ($canManage): ?>
<section class="panel panel-form">
  <h2><?= $editing ? 'Edit supplier' : 'Add supplier' ?></h2>
  <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= clean($err) ?></div><?php endforeach; ?>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
    <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
    <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int)$editing['id'] ?>"><?php endif; ?>
    <div class="form-row">
      <div class="form-field"><label>Company name</label><input type="text" name="name" value="<?= clean($editing['name'] ?? '') ?>" required></div>
      <div class="form-field"><label>Contact person</label><input type="text" name="contact_person" value="<?= clean($editing['contact_person'] ?? '') ?>"></div>
    </div>
    <div class="form-row">
      <div class="form-field"><label>Phone</label><input type="text" name="phone" value="<?= clean($editing['phone'] ?? '') ?>"></div>
      <div class="form-field"><label>Email</label><input type="email" name="email" value="<?= clean($editing['email'] ?? '') ?>"></div>
    </div>
    <div class="form-field"><label>Address</label><input type="text" name="address" value="<?= clean($editing['address'] ?? '') ?>"></div>
    <div class="form-actions">
      <?php if ($editing): ?><a href="index.php" class="btn btn-secondary">Cancel</a><?php endif; ?>
      <button type="submit" class="btn btn-primary"><?= $editing ? 'Save changes' : 'Add supplier' ?></button>
    </div>
  </form>
</section>
<?php endif; ?>

<section class="panel">
  <?php if ($suppliers): ?>
  <table class="data-table">
    <thead><tr><th>Company</th><th>Contact</th><th>Phone</th><th>Email</th><th>Added by</th><?php if ($canManage): ?><th></th><?php endif; ?></tr></thead>
    <tbody>
      <?php foreach ($suppliers as $s): ?>
      <tr>
        <td><?= clean($s['name']) ?></td>
        <td><?= clean($s['contact_person'] ?? '—') ?></td>
        <td class="mono"><?= clean($s['phone'] ?? '—') ?></td>
        <td><?= clean($s['email'] ?? '—') ?></td>
        <td class="muted small"><?= clean($s['creator_name'] ?? '—') ?></td>
        <?php if ($canManage): ?>
        <td class="row-actions">
          <a href="index.php?edit=<?= (int)$s['id'] ?>" class="link">Edit</a>
          <form method="post" class="js-confirm" data-confirm-title="Remove this supplier?" data-confirm-message="Products linked to them will keep their history, but this record will be gone." style="display:inline">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
            <button type="submit" class="link link-danger">Remove</button>
          </form>
        </td>
        <?php endif; ?>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php else: ?>
    <p class="empty-state">No suppliers yet.</p>
  <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
