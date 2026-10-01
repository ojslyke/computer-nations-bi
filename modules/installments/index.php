<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/rbac.php';
requirePermission('installments.view');

$pageTitle = 'Installments';
$branchId = currentBranchId();

$sql = "SELECT o.id, o.order_number, o.total_amount, o.amount_paid, o.payment_status, o.created_at,
               c.name AS customer_name, b.name AS branch_name
        FROM orders o
        LEFT JOIN customers c ON c.id = o.customer_id
        JOIN branches b ON b.id = o.branch_id
        WHERE o.payment_status IN ('partial','unpaid') AND o.status != 'cancelled'";
$params = [];
if ($branchId !== null) {
    $sql .= " AND o.branch_id = ?";
    $params[] = $branchId;
}
$sql .= " ORDER BY o.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<p class="muted" style="margin-bottom:12px;">Orders with a balance still owed<?= $branchId !== null ? ' at ' . clean(getBranchName($branchId, $pdo)) : '' ?>.</p>

<section class="panel">
  <?php if ($orders): ?>
  <div class="data-table-wrap">
  <table class="data-table" data-has-server-search="true">
    <thead><tr><th>Order #</th><th>Date</th><?= $branchId === null ? '<th>Branch</th>' : '' ?><th>Customer</th><th>Total</th><th>Paid</th><th>Balance</th><th>Status</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($orders as $o): $balance = $o['total_amount'] - $o['amount_paid']; ?>
      <tr>
        <td class="mono"><?= clean($o['order_number']) ?></td>
        <td class="mono"><?= date('d M Y', strtotime($o['created_at'])) ?></td>
        <?= $branchId === null ? '<td>' . clean($o['branch_name']) . '</td>' : '' ?>
        <td><?= clean($o['customer_name'] ?? 'Walk-in') ?></td>
        <td class="mono"><?= formatMoney($o['total_amount']) ?></td>
        <td class="mono"><?= formatMoney($o['amount_paid']) ?></td>
        <td class="mono num-danger"><?= formatMoney($balance) ?></td>
        <td><span class="badge badge-pending"><?= clean(ucfirst($o['payment_status'])) ?></span></td>
        <td class="row-actions">
          <?php if (hasPermission('installments.manage')): ?>
            <a href="record.php?order=<?= (int)$o['id'] ?>" class="link">Record payment</a>
          <?php endif; ?>
          <a href="../orders/view.php?id=<?= (int)$o['id'] ?>" class="link">View order</a>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php else: ?>
    <p class="empty-state">No orders with an outstanding balance.</p>
  <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
