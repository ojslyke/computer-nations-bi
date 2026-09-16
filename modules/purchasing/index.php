<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/rbac.php';
requirePermission('purchasing.view');

$pageTitle = 'Purchase orders';
$branchId = currentBranchId();

$sql = "SELECT po.*, s.name AS supplier_name, u.full_name AS staff_name, b.name AS branch_name,
            (SELECT COALESCE(SUM(quantity_ordered * unit_cost),0) FROM purchase_order_items WHERE po_id = po.id) AS total_cost
     FROM purchase_orders po
     JOIN suppliers s ON s.id = po.supplier_id
     JOIN users u ON u.id = po.user_id
     JOIN branches b ON b.id = po.branch_id";
$params = [];
if ($branchId !== null) {
    $sql .= " WHERE po.branch_id = ?";
    $params[] = $branchId;
}
$sql .= " ORDER BY po.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="toolbar">
  <p class="muted">Orders you've raised with suppliers to restock <?= $branchId !== null ? clean(getBranchName($branchId, $pdo)) : 'your branches' ?>.</p>
  <?php if (hasPermission('purchasing.create')): ?>
    <a href="create.php" class="btn btn-primary"><?= icon('plus', 15) ?> New purchase order</a>
  <?php endif; ?>
</div>

<section class="panel">
  <?php if ($orders): ?>
  <div class="data-table-wrap">
  <table class="data-table">
    <thead><tr><th>PO #</th><th>Date</th><?= $branchId === null ? '<th>Branch</th>' : '' ?><th>Supplier</th><th>Raised by</th><th>Est. cost</th><th>Status</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($orders as $po): ?>
      <tr>
        <td class="mono"><?= clean($po['po_number']) ?></td>
        <td class="mono"><?= date('d M Y', strtotime($po['created_at'])) ?></td>
        <?= $branchId === null ? '<td>' . clean($po['branch_name']) . '</td>' : '' ?>
        <td><?= clean($po['supplier_name']) ?></td>
        <td><?= clean($po['staff_name']) ?></td>
        <td class="mono"><?= formatMoney($po['total_cost']) ?></td>
        <td><span class="badge badge-<?= clean($po['status']) ?>"><?= clean(str_replace('_', ' ', $po['status'])) ?></span></td>
        <td class="row-actions"><a href="view.php?id=<?= (int)$po['id'] ?>" class="link">View</a></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php else: ?>
    <p class="empty-state">No purchase orders yet.</p>
  <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
