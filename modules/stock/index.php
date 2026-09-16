<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/rbac.php';
requirePermission('stock.view');

$pageTitle = 'Stock movements';
$branchId = currentBranchId();
$productFilter = (int)($_GET['product'] ?? 0);

$sql = "SELECT sm.*, p.name AS product_name, p.sku, u.full_name AS user_name, b.name AS branch_name
        FROM stock_movements sm
        JOIN products p ON p.id = sm.product_id
        JOIN users u ON u.id = sm.user_id
        JOIN branches b ON b.id = sm.branch_id
        WHERE 1=1";
$params = [];
if ($branchId !== null) {
    $sql .= " AND sm.branch_id = ?";
    $params[] = $branchId;
}
if ($productFilter) {
    $sql .= " AND sm.product_id = ?";
    $params[] = $productFilter;
}
$sql .= " ORDER BY sm.created_at DESC LIMIT 200";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$movements = $stmt->fetchAll();

$filteredProductName = null;
if ($productFilter) {
    $stmt = $pdo->prepare("SELECT name FROM products WHERE id = ?");
    $stmt->execute([$productFilter]);
    $filteredProductName = $stmt->fetch()['name'] ?? null;
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="toolbar">
  <p class="muted">
    Every change to stock — in, out, or a correction — shows up here with who made it, at <?= $branchId !== null ? clean(getBranchName($branchId, $pdo)) : 'every branch' ?>.
    <?php if ($filteredProductName): ?> Filtered to <strong><?= clean($filteredProductName) ?></strong> — <a href="index.php" class="link">clear filter</a>.<?php endif; ?>
  </p>
  <?php if (hasPermission('stock.adjust')): ?>
    <a href="adjust.php" class="btn btn-primary"><?= icon('plus', 15) ?> Record stock movement</a>
  <?php endif; ?>
</div>

<section class="panel">
  <?php if ($movements): ?>
  <div class="data-table-wrap">
  <table class="data-table">
    <thead>
      <tr><th>Date</th><th>Product</th><th>SKU</th><?= $branchId === null ? '<th>Branch</th>' : '' ?><th>Type</th><th>Category</th><th>Qty</th><th>Reason</th><th>Reference</th><th>By</th></tr>
    </thead>
    <tbody>
      <?php foreach ($movements as $m): ?>
      <tr>
        <td class="mono"><?= date('d M Y, H:i', strtotime($m['created_at'])) ?></td>
        <td><a href="index.php?product=<?= (int)$m['product_id'] ?>" class="link"><?= clean($m['product_name']) ?></a></td>
        <td class="mono"><?= clean($m['sku']) ?></td>
        <?= $branchId === null ? '<td>' . clean($m['branch_name']) . '</td>' : '' ?>
        <td><span class="badge badge-<?= $m['type'] === 'in' ? 'completed' : ($m['type'] === 'out' ? 'cancelled' : 'pending') ?>"><?= clean($m['type']) ?></span></td>
        <td class="muted small"><?= clean(ucfirst($m['category'])) ?></td>
        <td class="mono"><?= $m['type'] === 'out' ? '-' : '+' ?><?= (int)$m['quantity'] ?></td>
        <td><?= clean($m['reason'] ?? '—') ?></td>
        <td class="mono small"><?= clean($m['reference'] ?? '—') ?></td>
        <td><?= clean($m['user_name']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php else: ?>
    <p class="empty-state">No stock movements recorded yet.</p>
  <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
