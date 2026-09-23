<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/rbac.php';
requirePermission('audit.view');

$pageTitle = 'Stock audit';
$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    "SELECT sa.*, b.name AS branch_name, u.full_name AS conductor_name
     FROM stock_audits sa JOIN branches b ON b.id = sa.branch_id JOIN users u ON u.id = sa.conducted_by
     WHERE sa.id = ?"
);
$stmt->execute([$id]);
$audit = $stmt->fetch();

if (!$audit) {
    setFlash('error', 'Stock audit not found.');
    redirect('modules/audit/index.php');
}

$items = $pdo->prepare(
    "SELECT sai.*, p.name AS product_name, p.sku FROM stock_audit_items sai
     JOIN products p ON p.id = sai.product_id WHERE sai.audit_id = ? ORDER BY p.name"
);
$items->execute([$id]);
$items = $items->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<section class="panel panel-wide">
  <div class="detail-header">
    <div>
      <h2 class="mono"><?= clean($audit['audit_number']) ?></h2>
      <p class="muted"><?= clean($audit['branch_name']) ?> · <?= date('d M Y, H:i', strtotime($audit['created_at'])) ?> · Conducted by <?= clean($audit['conductor_name']) ?></p>
    </div>
    <?php if (hasPermission('audit.export')): ?>
      <a href="export.php?audit=<?= (int)$id ?>" class="btn btn-secondary"><?= icon('download', 15) ?> Download CSV</a>
    <?php endif; ?>
  </div>

  <?php if ($audit['notes']): ?><p class="muted small"><?= clean($audit['notes']) ?></p><?php endif; ?>

  <div class="data-table-wrap">
  <table class="data-table">
    <thead><tr><th>Product</th><th>SKU</th><th>System qty</th><th>Counted qty</th><th>Variance</th></tr></thead>
    <tbody>
      <?php foreach ($items as $item): ?>
      <tr>
        <td><?= clean($item['product_name']) ?></td>
        <td class="mono"><?= clean($item['sku']) ?></td>
        <td class="mono"><?= (int)$item['system_quantity'] ?></td>
        <td class="mono"><?= (int)$item['counted_quantity'] ?></td>
        <td class="mono <?= $item['variance'] > 0 ? 'num-danger' : ($item['variance'] < 0 ? 'num-danger' : '') ?>">
          <?php if ($item['variance'] > 0): ?>
            <span class="badge badge-pending">+<?= (int)$item['variance'] ?> excess</span>
          <?php elseif ($item['variance'] < 0): ?>
            <span class="badge badge-cancelled"><?= (int)$item['variance'] ?> shortage</span>
          <?php else: ?>
            <span class="muted">Matched</span>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</section>

<a href="index.php" class="btn btn-secondary">Back to audits</a>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
