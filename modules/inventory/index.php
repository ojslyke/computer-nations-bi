<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/rbac.php';
requirePermission('inventory.view');

$pageTitle = 'Inventory';
$viewBranchId = resolveViewBranchId($pdo);
$branchesByTown = listBranchesByTown($pdo);

$search = trim($_GET['q'] ?? '');
$searchClause = '';
$params = [];
if ($search !== '') {
    $searchClause = " AND (p.name LIKE ? OR p.sku LIKE ?)";
    $params = ["%$search%", "%$search%"];
}

if ($viewBranchId !== null) {
    // One specific branch: exact quantity + reorder level there, plus units currently in SAV
    $sql = "SELECT p.*, c.name AS category_name, s.name AS supplier_name,
                   COALESCE(bs.quantity, 0) AS quantity,
                   COALESCE(bs.reorder_level, p.default_reorder_level) AS reorder_level,
                   COALESCE(sav.sav_qty, 0) AS sav_qty
            FROM products p
            LEFT JOIN categories c ON c.id = p.category_id
            LEFT JOIN suppliers s ON s.id = p.supplier_id
            LEFT JOIN branch_stock bs ON bs.product_id = p.id AND bs.branch_id = ?
            LEFT JOIN (
                SELECT product_id, SUM(quantity) AS sav_qty FROM sav_items
                WHERE branch_id = ? AND status IN ('reported','with_technician') GROUP BY product_id
            ) sav ON sav.product_id = p.id
            WHERE 1=1 $searchClause
            ORDER BY p.name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge([$viewBranchId, $viewBranchId], $params));
} else {
    // All branches: aggregate good stock + aggregate SAV stock, flag if any branch is low
    $sql = "SELECT p.*, c.name AS category_name, s.name AS supplier_name,
                   COALESCE(SUM(bs.quantity), 0) AS quantity,
                   SUM(CASE WHEN bs.quantity <= bs.reorder_level THEN 1 ELSE 0 END) AS low_branch_count,
                   COALESCE(sav.sav_qty, 0) AS sav_qty
            FROM products p
            LEFT JOIN categories c ON c.id = p.category_id
            LEFT JOIN suppliers s ON s.id = p.supplier_id
            LEFT JOIN branch_stock bs ON bs.product_id = p.id
            LEFT JOIN (
                SELECT product_id, SUM(quantity) AS sav_qty FROM sav_items
                WHERE status IN ('reported','with_technician') GROUP BY product_id
            ) sav ON sav.product_id = p.id
            WHERE 1=1 $searchClause
            GROUP BY p.id
            ORDER BY p.name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
}
$products = $stmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="toolbar">
  <form method="get" class="search-form">
    <input type="text" name="q" placeholder="Search by name or SKU..." value="<?= clean($search) ?>">
    <button type="submit" class="btn btn-secondary">Search</button>
  </form>
  <div style="display:flex; gap:8px;">
    <a href="export.php" class="btn btn-secondary"><?= icon('download', 15) ?> Export CSV</a>
    <?php if (hasPermission('inventory.create')): ?>
      <a href="create.php" class="btn btn-primary"><?= icon('plus', 15) ?> Add product</a>
    <?php endif; ?>
  </div>
</div>

<form method="get" class="toolbar" style="margin-top:-6px;">
  <?php if ($search !== ''): ?><input type="hidden" name="q" value="<?= clean($search) ?>"><?php endif; ?>
  <div class="form-field" style="margin:0;">
    <label class="muted small" style="font-weight:600;">Viewing stock at</label>
    <select name="view_branch" onchange="this.form.submit()">
      <option value="all" <?= $viewBranchId === null ? 'selected' : '' ?>>All branches (totals)</option>
      <?php foreach ($branchesByTown as $townName => $townBranches): ?>
        <optgroup label="<?= clean($townName) ?>">
          <?php foreach ($townBranches as $b): ?>
            <option value="<?= $b['id'] ?>" <?= $viewBranchId == $b['id'] ? 'selected' : '' ?>><?= clean($b['name']) ?></option>
          <?php endforeach; ?>
        </optgroup>
      <?php endforeach; ?>
    </select>
  </div>
  <p class="muted small" style="margin:0;">This is read-only — it doesn't change which branch your actions apply to.</p>
</form>

<section class="panel">
  <?php if ($products): ?>
  <div class="data-table-wrap">
  <table class="data-table" data-has-server-search="true">
    <thead>
      <tr>
        <th></th><th>Product</th><th>SKU</th><th>Category</th><th>Supplier</th>
        <th>Cost</th><th>Price</th><th><?= $viewBranchId !== null ? 'Good stock' : 'Total good stock' ?></th><th>SAV</th><th>Status</th>
        <?php if (hasPermission('inventory.edit') || hasPermission('inventory.delete')): ?><th></th><?php endif; ?>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($products as $p): $isLow = $viewBranchId !== null ? $p['quantity'] <= $p['reorder_level'] : ($p['low_branch_count'] ?? 0) > 0; ?>
      <tr>
        <td>
          <?php if (!empty($p['image'])): ?>
            <img src="<?= UPLOAD_URL . clean($p['image']) ?>" alt="" class="product-thumb" loading="lazy" decoding="async" width="36" height="36">
          <?php else: ?>
            <span class="product-thumb product-thumb-empty"><?= icon('inventory', 16) ?></span>
          <?php endif; ?>
        </td>
        <td><?= clean($p['name']) ?></td>
        <td class="mono"><?= clean($p['sku']) ?></td>
        <td><?= clean($p['category_name'] ?? '—') ?></td>
        <td><?= clean($p['supplier_name'] ?? '—') ?></td>
        <td class="mono"><?= formatMoney($p['cost_price']) ?></td>
        <td class="mono"><?= formatMoney($p['selling_price']) ?></td>
        <td class="mono <?= $isLow ? 'num-danger' : '' ?>"><?= (int)$p['quantity'] ?><?= $viewBranchId === null && $isLow ? ' <span class="badge badge-pending" style="margin-left:4px;">low somewhere</span>' : '' ?></td>
        <td class="mono"><?= $p['sav_qty'] > 0 ? '<span class="badge badge-pending">' . (int)$p['sav_qty'] . '</span>' : '<span class="muted">0</span>' ?></td>
        <td><span class="badge badge-<?= $p['status'] === 'active' ? 'completed' : 'cancelled' ?>"><?= clean($p['status']) ?></span></td>
        <?php if (hasPermission('inventory.edit') || hasPermission('inventory.delete') || hasPermission('sav.create')): ?>
        <td class="row-actions">
          <?php if (hasPermission('inventory.edit')): ?>
            <a href="edit.php?id=<?= (int)$p['id'] ?>" class="link">Edit</a>
          <?php endif; ?>
          <?php if (hasPermission('sav.create')): ?>
            <a href="../sav/create.php?product=<?= (int)$p['id'] ?>" class="link link-danger">Report SAV</a>
          <?php endif; ?>
          <?php if (hasPermission('inventory.delete')): ?>
            <form method="post" action="delete.php" class="js-confirm" data-confirm-title="Delete this product?" data-confirm-message="This removes it from inventory at every branch permanently. This cannot be undone." style="display:inline">
              <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
              <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
              <button type="submit" class="link link-danger">Delete</button>
            </form>
          <?php endif; ?>
        </td>
        <?php endif; ?>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php else: ?>
    <p class="empty-state">No products found<?= $search !== '' ? ' for "' . clean($search) . '"' : '' ?>.</p>
  <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
