<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/rbac.php';
requirePermission('orders.view');

$pageTitle = 'Orders';
$branchId = currentBranchId();

$sql = "SELECT o.*, c.name AS customer_name, u.full_name AS staff_name, b.name AS branch_name
        FROM orders o
        LEFT JOIN customers c ON c.id = o.customer_id
        JOIN users u ON u.id = o.user_id
        JOIN branches b ON b.id = o.branch_id";
$params = [];
if ($branchId !== null) {
    $sql .= " WHERE o.branch_id = ?";
    $params[] = $branchId;
}
$sql .= " ORDER BY o.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="toolbar">
  <p class="muted">Orders placed at <?= $branchId !== null ? clean(getBranchName($branchId, $pdo)) : 'every branch' ?>.</p>
  <div style="display:flex; gap:8px;">
    <a href="export.php" class="btn btn-secondary"><?= icon('download', 15) ?> Export CSV</a>
    <?php if (hasPermission('orders.create')): ?>
      <a href="create.php" class="btn btn-primary"><?= icon('plus', 15) ?> New order</a>
    <?php endif; ?>
  </div>
</div>

<section class="panel">
  <?php if ($orders): ?>
  <div class="data-table-wrap">
  <table class="data-table">
    <thead>
      <tr><th>Order #</th><th>Date</th><?= $branchId === null ? '<th>Branch</th>' : '' ?><th>Customer</th><th>Staff</th><th>Total</th><th>Status</th><th></th></tr>
    </thead>
    <tbody>
      <?php foreach ($orders as $o): ?>
      <tr>
        <td class="mono"><?= clean($o['order_number']) ?></td>
        <td class="mono"><?= date('d M Y', strtotime($o['created_at'])) ?></td>
        <?= $branchId === null ? '<td>' . clean($o['branch_name']) . '</td>' : '' ?>
        <td><?= clean($o['customer_name'] ?? 'Walk-in') ?></td>
        <td><?= clean($o['staff_name']) ?></td>
        <td class="mono"><?= formatMoney($o['total_amount']) ?></td>
        <td><span class="badge badge-<?= clean($o['status']) ?>"><?= clean($o['status']) ?></span></td>
        <td class="row-actions"><a href="view.php?id=<?= (int)$o['id'] ?>" class="link">View</a></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php else: ?>
    <p class="empty-state">No orders yet.</p>
  <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
