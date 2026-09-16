<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/rbac.php';
requirePermission('branches.manage');

$pageTitle = 'Branches';
$errors = [];
$townErrors = [];
$editing = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_town') {
        $townName = trim($_POST['town_name'] ?? '');
        if ($townName === '') {
            $townErrors[] = 'Town name is required.';
        } else {
            try {
                $pdo->prepare("INSERT INTO towns (name) VALUES (?)")->execute([$townName]);
                logActivity('branches.manage', "Added town: $townName");
                setFlash('success', "$townName added. You can now add a branch there.");
                redirect('modules/branches/index.php');
            } catch (PDOException $e) {
                $townErrors[] = 'That town already exists.';
            }
        }
    }

    if ($action === 'toggle') {
        $id = (int)$_POST['id'];
        $pdo->prepare("UPDATE branches SET is_active = 1 - is_active WHERE id = ?")->execute([$id]);
        logActivity('branches.manage', "Toggled active state for branch #$id");
        setFlash('success', 'Branch updated.');
        redirect('modules/branches/index.php');
    }

    if ($action === 'create' || $action === 'update') {
        $name = trim($_POST['name'] ?? '');
        $townId = (int)($_POST['town_id'] ?? 0);
        $address = trim($_POST['address'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if ($name === '' || !$townId) {
            $errors[] = 'Branch name and town are required.';
        } else {
            if ($action === 'update' && !empty($_POST['id'])) {
                $pdo->prepare("UPDATE branches SET name=?, town_id=?, address=?, phone=? WHERE id=?")
                    ->execute([$name, $townId, $address, $phone, (int)$_POST['id']]);
                setFlash('success', 'Branch updated.');
            } else {
                $pdo->prepare("INSERT INTO branches (name, town_id, address, phone, created_by) VALUES (?,?,?,?,?)")
                    ->execute([$name, $townId, $address, $phone, $_SESSION['user_id']]);
                setFlash('success', 'Branch added. Add stock to it from Inventory or Transfers.');
            }
            logActivity('branches.manage', "Saved branch: $name");
            redirect('modules/branches/index.php');
        }
    }
}

if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM branches WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editing = $stmt->fetch();
}

$towns = $pdo->query("SELECT id, name FROM towns ORDER BY name")->fetchAll();

$branches = $pdo->query(
    "SELECT b.*, t.name AS town_name, u.full_name AS creator_name,
            (SELECT COUNT(*) FROM users us WHERE us.branch_id = b.id) AS staff_count,
            (SELECT COALESCE(SUM(quantity),0) FROM branch_stock bs WHERE bs.branch_id = b.id) AS stock_units
     FROM branches b JOIN towns t ON t.id = b.town_id LEFT JOIN users u ON u.id = b.created_by
     ORDER BY t.name, b.name"
)->fetchAll();

// Group for display so each town heads its own set of branch rows
$grouped = [];
foreach ($branches as $b) {
    $grouped[$b['town_name']][] = $b;
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="panel-grid">

<section class="panel panel-form">
  <h2>Add a town</h2>
  <p class="muted small">Towns just group branches — add one here first, then add branches inside it.</p>
  <?php foreach ($townErrors as $err): ?><div class="alert alert-error"><?= clean($err) ?></div><?php endforeach; ?>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
    <input type="hidden" name="action" value="add_town">
    <div class="form-field">
      <label>Town name</label>
      <input type="text" name="town_name" placeholder="e.g. Garoua" required>
    </div>
    <div class="form-actions">
      <button type="submit" class="btn btn-secondary">Add town</button>
    </div>
  </form>

  <?php if ($towns): ?>
  <p class="muted small" style="margin-top:14px;">Existing towns: <?= implode(', ', array_map(fn($t) => clean($t['name']), $towns)) ?></p>
  <?php endif; ?>
</section>

<section class="panel panel-form">
  <h2><?= $editing ? 'Edit branch' : 'Add branch' ?></h2>
  <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= clean($err) ?></div><?php endforeach; ?>

  <?php if (!$towns): ?>
    <p class="empty-state">Add a town first.</p>
  <?php else: ?>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
    <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
    <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int)$editing['id'] ?>"><?php endif; ?>
    <div class="form-row">
      <div class="form-field">
        <label>Branch name</label>
        <input type="text" name="name" value="<?= clean($editing['name'] ?? '') ?>" placeholder="e.g. Bonabéri Branch" required>
      </div>
      <div class="form-field">
        <label>Town</label>
        <select name="town_id" required>
          <option value="">— Select town —</option>
          <?php foreach ($towns as $t): ?>
            <option value="<?= $t['id'] ?>" <?= isset($editing) && $t['id'] == $editing['town_id'] ? 'selected' : '' ?>><?= clean($t['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="form-row">
      <div class="form-field">
        <label>Address</label>
        <input type="text" name="address" value="<?= clean($editing['address'] ?? '') ?>">
      </div>
      <div class="form-field">
        <label>Phone</label>
        <input type="text" name="phone" value="<?= clean($editing['phone'] ?? '') ?>">
      </div>
    </div>
    <div class="form-actions">
      <?php if ($editing): ?><a href="index.php" class="btn btn-secondary">Cancel</a><?php endif; ?>
      <button type="submit" class="btn btn-primary"><?= $editing ? 'Save changes' : 'Add branch' ?></button>
    </div>
  </form>
  <?php endif; ?>
</section>

</div>

<?php foreach ($grouped as $townName => $townBranches): ?>
<section class="panel">
  <h2><?= clean($townName) ?> <span class="muted small">· <?= count($townBranches) ?> branch<?= count($townBranches) === 1 ? '' : 'es' ?></span></h2>
  <div class="data-table-wrap">
  <table class="data-table">
    <thead><tr><th>Branch</th><th>Address</th><th>Staff</th><th>Stock on hand</th><th>Added by</th><th>Status</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($townBranches as $b): ?>
      <tr>
        <td><?= clean($b['name']) ?></td>
        <td class="muted small"><?= clean($b['address'] ?? '—') ?></td>
        <td class="mono"><?= (int)$b['staff_count'] ?></td>
        <td class="mono"><?= (int)$b['stock_units'] ?> units</td>
        <td class="muted small"><?= clean($b['creator_name'] ?? '—') ?></td>
        <td><span class="badge badge-<?= $b['is_active'] ? 'active' : 'suspended' ?>"><?= $b['is_active'] ? 'Active' : 'Inactive' ?></span></td>
        <td class="row-actions">
          <a href="index.php?edit=<?= (int)$b['id'] ?>" class="link">Edit</a>
          <form method="post" style="display:inline">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="action" value="toggle">
            <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
            <button type="submit" class="link <?= $b['is_active'] ? 'link-danger' : '' ?>"><?= $b['is_active'] ? 'Deactivate' : 'Activate' ?></button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</section>
<?php endforeach; ?>

<?php if (!$branches): ?>
  <section class="panel"><p class="empty-state">No branches yet — add a town, then a branch.</p></section>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
