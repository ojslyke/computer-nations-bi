<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/rbac.php';
requirePermission('inventory.edit');

$pageTitle = 'Edit product';
$errors = [];

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$stmt = $pdo->prepare("SELECT p.*, u.full_name AS creator_name FROM products p LEFT JOIN users u ON u.id = p.created_by WHERE p.id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    setFlash('error', 'Product not found.');
    redirect('modules/inventory/index.php');
}

$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();
$suppliers  = $pdo->query("SELECT id, name FROM suppliers ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formType = $_POST['form_type'] ?? 'details';
    csrfCheck();

    if ($formType === 'reorder_level') {
        // Per-branch reorder level tweak — a config value, not a stock change
        $branchId = (int)$_POST['branch_id'];
        $level = (int)$_POST['reorder_level'];
        $pdo->prepare(
            "INSERT INTO branch_stock (branch_id, product_id, quantity, reorder_level) VALUES (?, ?, 0, ?)
             ON DUPLICATE KEY UPDATE reorder_level = VALUES(reorder_level)"
        )->execute([$branchId, $id, $level]);
        setFlash('success', 'Reorder level updated for that branch.');
        redirect('modules/inventory/edit.php?id=' . $id);
    }

    $name          = trim($_POST['name']);
    $categoryId    = $_POST['category_id'] ?: null;
    $supplierId    = $_POST['supplier_id'] ?: null;
    $costPrice     = (float)$_POST['cost_price'];
    $sellingPrice  = (float)$_POST['selling_price'];
    $defaultReorder = (int)$_POST['default_reorder_level'];
    $status        = $_POST['status'] === 'discontinued' ? 'discontinued' : 'active';

    if ($name === '') {
        $errors[] = 'Product name is required.';
    }

    if (!$errors) {
        $pdo->prepare(
            "UPDATE products SET name=?, category_id=?, supplier_id=?, cost_price=?, selling_price=?, default_reorder_level=?, status=?
             WHERE id=?"
        )->execute([$name, $categoryId, $supplierId, $costPrice, $sellingPrice, $defaultReorder, $status, $id]);

        logActivity('inventory.edit', "Updated product: $name (ID $id)");
        setFlash('success', "$name was updated.");
        redirect('modules/inventory/index.php');
    }
}

$branchStock = $pdo->prepare(
    "SELECT b.id, b.name, t.name AS town_name, COALESCE(bs.quantity,0) AS quantity, COALESCE(bs.reorder_level, ?) AS reorder_level,
            COALESCE(sav.sav_qty, 0) AS sav_qty
     FROM branches b JOIN towns t ON t.id = b.town_id
     LEFT JOIN branch_stock bs ON bs.branch_id = b.id AND bs.product_id = ?
     LEFT JOIN (
         SELECT branch_id, SUM(quantity) AS sav_qty FROM sav_items
         WHERE product_id = ? AND status IN ('reported','with_technician') GROUP BY branch_id
     ) sav ON sav.branch_id = b.id
     WHERE b.is_active = 1 ORDER BY t.name, b.name"
);
$branchStock->execute([$product['default_reorder_level'], $id, $id]);
$branchStock = $branchStock->fetchAll();
$totalQty = array_sum(array_column($branchStock, 'quantity'));
$totalSav = array_sum(array_column($branchStock, 'sav_qty'));

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="panel-grid">

<section class="panel panel-form">
  <h2>Edit product <span class="mono muted">#<?= (int)$product['id'] ?> · <?= clean($product['sku']) ?></span></h2>
  <?php if ($product['creator_name']): ?><p class="muted small">Added by <?= clean($product['creator_name']) ?></p><?php endif; ?>

  <?php foreach ($errors as $err): ?>
    <div class="alert alert-error"><?= clean($err) ?></div>
  <?php endforeach; ?>

  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
    <input type="hidden" name="form_type" value="details">
    <input type="hidden" name="id" value="<?= (int)$product['id'] ?>">

    <div class="form-row">
      <div class="form-field">
        <label>SKU</label>
        <input type="text" value="<?= clean($product['sku']) ?>" disabled>
      </div>
      <div class="form-field">
        <label>Product name</label>
        <input type="text" name="name" value="<?= clean($product['name']) ?>" required>
      </div>
    </div>

    <div class="form-row">
      <div class="form-field">
        <label>Category</label>
        <select name="category_id">
          <option value="">— None —</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $c['id'] == $product['category_id'] ? 'selected' : '' ?>><?= clean($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-field">
        <label>Supplier</label>
        <select name="supplier_id">
          <option value="">— None —</option>
          <?php foreach ($suppliers as $s): ?>
            <option value="<?= $s['id'] ?>" <?= $s['id'] == $product['supplier_id'] ? 'selected' : '' ?>><?= clean($s['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="form-row">
      <div class="form-field">
        <label>Cost price (XAF)</label>
        <input type="number" step="0.01" min="0" name="cost_price" value="<?= clean($product['cost_price']) ?>" required>
      </div>
      <div class="form-field">
        <label>Selling price (XAF)</label>
        <input type="number" step="0.01" min="0" name="selling_price" value="<?= clean($product['selling_price']) ?>" required>
      </div>
    </div>

    <div class="form-row">
      <div class="form-field">
        <label>Default reorder level</label>
        <input type="number" min="0" name="default_reorder_level" value="<?= clean($product['default_reorder_level']) ?>" required>
        <span class="form-hint">Used when a branch first stocks this product. Override per branch on the right.</span>
      </div>
      <div class="form-field">
        <label>Status</label>
        <select name="status">
          <option value="active" <?= $product['status'] === 'active' ? 'selected' : '' ?>>Active</option>
          <option value="discontinued" <?= $product['status'] === 'discontinued' ? 'selected' : '' ?>>Discontinued</option>
        </select>
      </div>
    </div>

    <div class="form-actions">
      <a href="index.php" class="btn btn-secondary">Cancel</a>
      <button type="submit" class="btn btn-primary">Save changes</button>
    </div>
  </form>
</section>

<section class="panel">
  <h2>Stock by branch <span class="mono muted">· <?= (int)$totalQty ?> good<?= $totalSav > 0 ? ' · ' . (int)$totalSav . ' in SAV' : '' ?></span></h2>
  <p class="muted small">Quantity only changes through Stock movements, Transfers, or receiving a purchase order — not editable here. Reorder level is a per-branch setting you can adjust directly.</p>
  <div class="data-table-wrap">
  <table class="data-table">
    <thead><tr><th>Branch</th><th>Good stock</th><th>SAV</th><th>Reorder level</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($branchStock as $bs): ?>
      <tr>
        <td><?= clean($bs['name']) ?><br><span class="muted small"><?= clean($bs['town_name']) ?></span></td>
        <td class="mono <?= $bs['quantity'] <= $bs['reorder_level'] ? 'num-danger' : '' ?>"><?= (int)$bs['quantity'] ?></td>
        <td class="mono"><?= $bs['sav_qty'] > 0 ? '<span class="badge badge-pending">' . (int)$bs['sav_qty'] . '</span>' : '<span class="muted">0</span>' ?></td>
        <td>
          <form method="post" style="display:flex; gap:6px; align-items:center;">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="form_type" value="reorder_level">
            <input type="hidden" name="branch_id" value="<?= (int)$bs['id'] ?>">
            <input type="number" name="reorder_level" value="<?= (int)$bs['reorder_level'] ?>" min="0" style="width:70px; padding:5px 8px;">
            <button type="submit" class="btn btn-secondary btn-sm">Save</button>
          </form>
        </td>
        <td class="row-actions">
          <a href="../stock/index.php?product=<?= (int)$id ?>" class="link">History</a>
          <?php if ($bs['sav_qty'] > 0 && hasPermission('sav.view')): ?>
            <a href="../sav/index.php" class="link">SAV</a>
          <?php endif; ?>
          <?php if (hasPermission('sav.create')): ?>
            <a href="../sav/create.php?product=<?= (int)$id ?>" class="link link-danger">Report SAV</a>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</section>

</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
