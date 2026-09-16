<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/rbac.php';
requirePermission('sav.view');

$pageTitle = 'SAV';
$branchId = currentBranchId();
$statusFilter = $_GET['status'] ?? '';

$sql = "SELECT si.*, p.name AS product_name, p.sku, b.name AS branch_name, u.full_name AS reporter_name
        FROM sav_items si
        JOIN products p ON p.id = si.product_id
        JOIN branches b ON b.id = si.branch_id
        JOIN users u ON u.id = si.reported_by
        WHERE 1=1";
$params = [];
if ($branchId !== null) {
    $sql .= " AND si.branch_id = ?";
    $params[] = $branchId;
}
if (in_array($statusFilter, ['reported','with_technician','repaired','unrepairable'], true)) {
    $sql .= " AND si.status = ?";
    $params[] = $statusFilter;
}
$sql .= " ORDER BY si.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$savItems = $stmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="toolbar">
  <form class="search-form" method="get">
    <select name="status" onchange="this.form.submit()">
      <option value="">All statuses</option>
      <option value="reported" <?= $statusFilter === 'reported' ? 'selected' : '' ?>>Reported</option>
      <option value="with_technician" <?= $statusFilter === 'with_technician' ? 'selected' : '' ?>>With technician</option>
      <option value="repaired" <?= $statusFilter === 'repaired' ? 'selected' : '' ?>>Repaired</option>
      <option value="unrepairable" <?= $statusFilter === 'unrepairable' ? 'selected' : '' ?>>Unrepairable</option>
    </select>
  </form>
  <div style="display:flex; gap:8px;">
    <?php if (hasPermission('sav.manage')): ?>
      <a href="../technicians/index.php" class="btn btn-secondary"><?= icon('users', 15) ?> Technicians</a>
    <?php endif; ?>
    <?php if (hasPermission('sav.create')): ?>
      <a href="create.php" class="btn btn-primary"><?= icon('plus', 15) ?> Report SAV item</a>
    <?php endif; ?>
  </div>
</div>

<section class="panel">
  <?php if ($savItems): ?>
  <div class="data-table-wrap">
  <table class="data-table" data-has-server-search="true">
    <thead>
      <tr><th>SAV #</th><th>Date</th><th>Product</th><?= $branchId === null ? '<th>Branch</th>' : '' ?><th>Qty</th><th>Issue</th><th>Reported by</th><th>Status</th><th></th></tr>
    </thead>
    <tbody>
      <?php foreach ($savItems as $s): ?>
      <tr>
        <td class="mono"><?= clean($s['sav_number']) ?></td>
        <td class="mono"><?= date('d M Y', strtotime($s['created_at'])) ?></td>
        <td><?= clean($s['product_name']) ?></td>
        <?= $branchId === null ? '<td>' . clean($s['branch_name']) . '</td>' : '' ?>
        <td class="mono"><?= (int)$s['quantity'] ?></td>
        <td><?= clean($s['issue_description']) ?></td>
        <td><?= clean($s['reporter_name']) ?></td>
        <td><span class="badge badge-<?= $s['status'] === 'repaired' ? 'completed' : ($s['status'] === 'unrepairable' ? 'cancelled' : 'pending') ?>"><?= clean(str_replace('_', ' ', $s['status'])) ?></span></td>
        <td class="row-actions"><a href="view.php?id=<?= (int)$s['id'] ?>" class="link">View</a></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php else: ?>
    <p class="empty-state">No SAV items<?= $statusFilter ? ' with that status' : '' ?>.</p>
  <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
