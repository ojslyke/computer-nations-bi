<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/rbac.php';
requirePermission('expeditions.view');

$pageTitle = 'Expeditions';
$branchId = currentBranchId();

$sql = "SELECT st.*, fb.name AS from_branch_name, tb.name AS to_branch_name,
               ft.name AS from_town, tt.name AS to_town,
               u.full_name AS staff_name, ru.full_name AS receiver_name,
               (SELECT COALESCE(SUM(quantity),0) FROM stock_transfer_items WHERE transfer_id = st.id) AS total_units
        FROM stock_transfers st
        JOIN branches fb ON fb.id = st.from_branch_id
        JOIN branches tb ON tb.id = st.to_branch_id
        JOIN towns ft ON ft.id = fb.town_id
        JOIN towns tt ON tt.id = tb.town_id
        JOIN users u ON u.id = st.user_id
        LEFT JOIN users ru ON ru.id = st.received_by
        WHERE st.type = 'expedition'";
$params = [];
if ($branchId !== null) {
    $sql .= " AND (st.from_branch_id = ? OR st.to_branch_id = ?)";
    $params = [$branchId, $branchId];
}
$sql .= " ORDER BY st.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$expeditions = $stmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="toolbar">
  <p class="muted">Stock moving between towns<?= $branchId !== null ? ' involving ' . clean(getBranchName($branchId, $pdo)) : '' ?>.</p>
  <?php if (hasPermission('expeditions.create')): ?>
    <a href="create.php" class="btn btn-primary"><?= icon('plus', 15) ?> Send expedition</a>
  <?php endif; ?>
</div>

<section class="panel">
  <?php if ($expeditions): ?>
  <div class="data-table-wrap">
  <table class="data-table">
    <thead><tr><th>Expedition #</th><th>Date</th><th>From</th><th>To</th><th>Units</th><th>Sent by</th><th>Received by</th><th>Status</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($expeditions as $e): ?>
      <tr>
        <td class="mono"><?= clean($e['transfer_number']) ?></td>
        <td class="mono"><?= date('d M Y', strtotime($e['created_at'])) ?></td>
        <td><?= clean($e['from_branch_name']) ?> <span class="muted small">(<?= clean($e['from_town']) ?>)</span></td>
        <td><?= clean($e['to_branch_name']) ?> <span class="muted small">(<?= clean($e['to_town']) ?>)</span></td>
        <td class="mono"><?= (int)$e['total_units'] ?></td>
        <td><?= clean($e['staff_name']) ?></td>
        <td><?= clean($e['receiver_name'] ?? '—') ?></td>
        <td><span class="badge badge-<?= clean($e['status']) ?>"><?= clean(str_replace('_', ' ', $e['status'])) ?></span></td>
        <td class="row-actions"><a href="view.php?id=<?= (int)$e['id'] ?>" class="link">View</a></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php else: ?>
    <p class="empty-state">No expeditions yet.</p>
  <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
