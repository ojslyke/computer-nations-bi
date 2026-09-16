<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/rbac.php';
requirePermission('transfers.view');

$pageTitle = 'Transfers';
$branchId = currentBranchId();

$sql = "SELECT st.*, fb.name AS from_branch_name, tb.name AS to_branch_name,
               u.full_name AS staff_name, ru.full_name AS receiver_name,
               (SELECT COALESCE(SUM(quantity),0) FROM stock_transfer_items WHERE transfer_id = st.id) AS total_units
        FROM stock_transfers st
        JOIN branches fb ON fb.id = st.from_branch_id
        JOIN branches tb ON tb.id = st.to_branch_id
        JOIN users u ON u.id = st.user_id
        LEFT JOIN users ru ON ru.id = st.received_by
        WHERE st.type = 'transfer'";
$params = [];
if ($branchId !== null) {
    $sql .= " AND (st.from_branch_id = ? OR st.to_branch_id = ?)";
    $params = [$branchId, $branchId];
}
$sql .= " ORDER BY st.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$transfers = $stmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="toolbar">
  <p class="muted">Stock moving between branches<?= $branchId !== null ? ' involving ' . clean(getBranchName($branchId, $pdo)) : '' ?>.</p>
  <?php if (hasPermission('transfers.create')): ?>
    <a href="create.php" class="btn btn-primary"><?= icon('plus', 15) ?> Send transfer</a>
  <?php endif; ?>
</div>

<section class="panel">
  <?php if ($transfers): ?>
  <div class="data-table-wrap">
  <table class="data-table">
    <thead><tr><th>Transfer #</th><th>Date</th><th>From</th><th>To</th><th>Units</th><th>Sent by</th><th>Received by</th><th>Status</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($transfers as $t): ?>
      <tr>
        <td class="mono"><?= clean($t['transfer_number']) ?></td>
        <td class="mono"><?= date('d M Y', strtotime($t['created_at'])) ?></td>
        <td><?= clean($t['from_branch_name']) ?></td>
        <td><?= clean($t['to_branch_name']) ?></td>
        <td class="mono"><?= (int)$t['total_units'] ?></td>
        <td><?= clean($t['staff_name']) ?></td>
        <td><?= clean($t['receiver_name'] ?? '—') ?></td>
        <td><span class="badge badge-<?= clean($t['status']) ?>"><?= clean(str_replace('_', ' ', $t['status'])) ?></span></td>
        <td class="row-actions"><a href="view.php?id=<?= (int)$t['id'] ?>" class="link">View</a></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php else: ?>
    <p class="empty-state">No transfers yet.</p>
  <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
