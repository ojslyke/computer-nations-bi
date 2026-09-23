<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/rbac.php';
requirePermission('audit.view');

$pageTitle = 'Stock audits';
$branchId = currentBranchId();
$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to'] ?? date('Y-m-d');

$sql = "SELECT sa.*, b.name AS branch_name, u.full_name AS conductor_name,
               (SELECT COUNT(*) FROM stock_audit_items WHERE audit_id = sa.id) AS item_count,
               (SELECT COUNT(*) FROM stock_audit_items WHERE audit_id = sa.id AND variance > 0) AS excess_count,
               (SELECT COUNT(*) FROM stock_audit_items WHERE audit_id = sa.id AND variance < 0) AS shortage_count
        FROM stock_audits sa
        JOIN branches b ON b.id = sa.branch_id
        JOIN users u ON u.id = sa.conducted_by
        WHERE DATE(sa.created_at) BETWEEN ? AND ?";
$params = [$from, $to];
if ($branchId !== null) {
    $sql .= " AND sa.branch_id = ?";
    $params[] = $branchId;
}
$sql .= " ORDER BY sa.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$audits = $stmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<form method="get" class="toolbar">
  <div class="search-form">
    <input type="date" name="from" value="<?= clean($from) ?>">
    <span class="muted">to</span>
    <input type="date" name="to" value="<?= clean($to) ?>">
    <button type="submit" class="btn btn-secondary">Filter</button>
  </div>
  <div style="display:flex; gap:8px;">
    <a href="export.php?from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>" class="btn btn-secondary"><?= icon('download', 15) ?> Download CSV</a>
    <?php if (hasPermission('audit.create')): ?>
      <a href="create.php" class="btn btn-primary"><?= icon('plus', 15) ?> Conduct audit</a>
    <?php endif; ?>
  </div>
</form>

<section class="panel">
  <?php if ($audits): ?>
  <div class="data-table-wrap">
  <table class="data-table">
    <thead><tr><th>Audit #</th><th>Date</th><?= $branchId === null ? '<th>Branch</th>' : '' ?><th>Conducted by</th><th>Items counted</th><th>Discrepancies</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($audits as $a): ?>
      <tr>
        <td class="mono"><?= clean($a['audit_number']) ?></td>
        <td class="mono"><?= date('d M Y', strtotime($a['created_at'])) ?></td>
        <?= $branchId === null ? '<td>' . clean($a['branch_name']) . '</td>' : '' ?>
        <td><?= clean($a['conductor_name']) ?></td>
        <td class="mono"><?= (int)$a['item_count'] ?></td>
        <td>
          <?php if ($a['excess_count'] == 0 && $a['shortage_count'] == 0): ?>
            <span class="badge badge-completed">Matched</span>
          <?php else: ?>
            <?php if ($a['excess_count'] > 0): ?><span class="badge badge-pending"><?= (int)$a['excess_count'] ?> excess</span><?php endif; ?>
            <?php if ($a['shortage_count'] > 0): ?><span class="badge badge-cancelled"><?= (int)$a['shortage_count'] ?> shortage</span><?php endif; ?>
          <?php endif; ?>
        </td>
        <td class="row-actions"><a href="view.php?id=<?= (int)$a['id'] ?>" class="link">View</a></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php else: ?>
    <p class="empty-state">No stock audits in this date range.</p>
  <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
