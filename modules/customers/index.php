<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/rbac.php';
requirePermission('customers.view');

$pageTitle = 'Customers';
$canManage = hasPermission('customers.manage');
$errors = [];
$editing = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canManage) {
    csrfCheck();
    $action = $_POST['action'] ?? '';
    $name  = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if ($action === 'delete') {
        $pdo->prepare("DELETE FROM customers WHERE id = ?")->execute([(int)$_POST['id']]);
        logActivity('customers.manage', 'Deleted customer #' . (int)$_POST['id']);
        setFlash('success', 'Customer removed.');
        redirect('modules/customers/index.php');
    }

    if ($name === '') {
        $errors[] = 'Customer name is required.';
    } elseif (!$errors) {
        if ($action === 'update' && !empty($_POST['id'])) {
            $pdo->prepare("UPDATE customers SET name=?, phone=?, email=?, address=? WHERE id=?")
                ->execute([$name, $phone, $email, $address, (int)$_POST['id']]);
            setFlash('success', 'Customer updated.');
        } else {
            $pdo->prepare("INSERT INTO customers (name, phone, email, address, created_by) VALUES (?,?,?,?,?)")
                ->execute([$name, $phone, $email, $address, $_SESSION['user_id']]);
            setFlash('success', 'Customer added.');
        }
        logActivity('customers.manage', "Saved customer: $name");
        redirect('modules/customers/index.php');
    }
}

if ($canManage && isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editing = $stmt->fetch();
}

$customers = $pdo->query(
    "SELECT c.*, u.full_name AS creator_name FROM customers c LEFT JOIN users u ON u.id = c.created_by ORDER BY c.name"
)->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<?php if ($canManage): ?>
<section class="panel panel-form">
  <h2><?= $editing ? 'Edit customer' : 'Add customer' ?></h2>
  <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= clean($err) ?></div><?php endforeach; ?>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
    <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
    <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int)$editing['id'] ?>"><?php endif; ?>
    <div class="form-row">
      <div class="form-field"><label>Name</label><input type="text" name="name" value="<?= clean($editing['name'] ?? '') ?>" required></div>
      <div class="form-field"><label>Phone</label><input type="text" name="phone" value="<?= clean($editing['phone'] ?? '') ?>"></div>
    </div>
    <div class="form-row">
      <div class="form-field"><label>Email</label><input type="email" name="email" value="<?= clean($editing['email'] ?? '') ?>"></div>
      <div class="form-field"><label>Address</label><input type="text" name="address" value="<?= clean($editing['address'] ?? '') ?>"></div>
    </div>
    <div class="form-actions">
      <?php if ($editing): ?><a href="index.php" class="btn btn-secondary">Cancel</a><?php endif; ?>
      <button type="submit" class="btn btn-primary"><?= $editing ? 'Save changes' : 'Add customer' ?></button>
    </div>
  </form>
</section>
<?php endif; ?>

<section class="panel">
  <?php if ($customers): ?>
  <table class="data-table">
    <thead><tr><th>Name</th><th>Phone</th><th>Email</th><th>Address</th><th>Added by</th><?php if ($canManage): ?><th></th><?php endif; ?></tr></thead>
    <tbody>
      <?php foreach ($customers as $c): ?>
      <tr>
        <td><?= clean($c['name']) ?></td>
        <td class="mono"><?= clean($c['phone'] ?? '—') ?></td>
        <td><?= clean($c['email'] ?? '—') ?></td>
        <td><?= clean($c['address'] ?? '—') ?></td>
        <td class="muted small"><?= clean($c['creator_name'] ?? '—') ?></td>
        <?php if ($canManage): ?>
        <td class="row-actions">
          <a href="index.php?edit=<?= (int)$c['id'] ?>" class="link">Edit</a>
          <form method="post" class="js-confirm" data-confirm-title="Remove this customer?" data-confirm-message="Their order history stays intact, but this record will be gone." style="display:inline">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
            <button type="submit" class="link link-danger">Remove</button>
          </form>
        </td>
        <?php endif; ?>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php else: ?>
    <p class="empty-state">No customers yet.</p>
  <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
