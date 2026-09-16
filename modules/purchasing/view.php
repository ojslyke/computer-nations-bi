<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/rbac.php';
requirePermission('purchasing.view');

$pageTitle = 'Purchase order';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare(
    "SELECT po.*, s.name AS supplier_name, s.phone AS supplier_phone, s.email AS supplier_email,
            u.full_name AS staff_name, b.name AS branch_name, ru.full_name AS receiver_name, cu.full_name AS canceller_name
     FROM purchase_orders po
     JOIN suppliers s ON s.id = po.supplier_id
     JOIN users u ON u.id = po.user_id
     JOIN branches b ON b.id = po.branch_id
     LEFT JOIN users ru ON ru.id = po.received_by
     LEFT JOIN users cu ON cu.id = po.cancelled_by
     WHERE po.id = ?"
);
$stmt->execute([$id]);
$po = $stmt->fetch();

if (!$po) {
    setFlash('error', 'Purchase order not found.');
    redirect('modules/purchasing/index.php');
}

$items = $pdo->prepare(
    "SELECT poi.*, p.name AS product_name, p.sku
     FROM purchase_order_items poi JOIN products p ON p.id = poi.product_id
     WHERE poi.po_id = ?"
);
$items->execute([$id]);
$items = $items->fetchAll();

$totalCost = array_sum(array_map(fn($i) => $i['quantity_ordered'] * $i['unit_cost'], $items));

// Status transitions (mark as ordered / cancel) — receiving stock has its own page
if ($_SERVER['REQUEST_METHOD'] === 'POST' && hasPermission('purchasing.create')) {
    csrfCheck();
    $action = $_POST['action'] ?? '';

    if ($action === 'mark_ordered' && $po['status'] === 'draft') {
        $pdo->prepare("UPDATE purchase_orders SET status = 'ordered' WHERE id = ?")->execute([$id]);
        logActivity('purchasing.manage', "Marked {$po['po_number']} as ordered");
        setFlash('success', 'Purchase order marked as ordered.');
        redirect('modules/purchasing/view.php?id=' . $id);
    }

    if ($action === 'cancel' && in_array($po['status'], ['draft', 'ordered'], true)) {
        $pdo->prepare("UPDATE purchase_orders SET status = 'cancelled', cancelled_by = ? WHERE id = ?")->execute([$_SESSION['user_id'], $id]);
        logActivity('purchasing.manage', "Cancelled {$po['po_number']}");
        setFlash('success', 'Purchase order cancelled.');
        redirect('modules/purchasing/view.php?id=' . $id);
    }
}

$activeBranchId = currentBranchId();
$canReceiveHere = isMultiBranchUser() || $activeBranchId === (int)$po['branch_id'];

require_once __DIR__ . '/../../includes/header.php';
?>

<section class="panel panel-wide">
  <div class="detail-header">
    <div>
      <h2 class="mono"><?= clean($po['po_number']) ?></h2>
      <p class="muted"><?= date('d M Y, H:i', strtotime($po['created_at'])) ?> · Raised by <?= clean($po['staff_name']) ?> · Receiving at <?= clean($po['branch_name']) ?><?= $po['receiver_name'] ? ' · Received by ' . clean($po['receiver_name']) : '' ?><?= $po['canceller_name'] ? ' · Cancelled by ' . clean($po['canceller_name']) : '' ?></p>
    </div>
    <span class="badge badge-<?= clean($po['status']) ?>"><?= clean(str_replace('_', ' ', $po['status'])) ?></span>
  </div>

  <div class="detail-grid">
    <div><span class="muted">Supplier</span><br><?= clean($po['supplier_name']) ?></div>
    <?php if ($po['supplier_phone']): ?><div><span class="muted">Phone</span><br><?= clean($po['supplier_phone']) ?></div><?php endif; ?>
    <?php if ($po['notes']): ?><div><span class="muted">Notes</span><br><?= clean($po['notes']) ?></div><?php endif; ?>
    <div><span class="muted">Estimated total</span><br><strong class="mono"><?= formatMoney($totalCost) ?></strong></div>
  </div>

  <div class="data-table-wrap">
  <table class="data-table">
    <thead><tr><th>Product</th><th>SKU</th><th>Ordered</th><th>Received</th><th>Unit cost</th><th>Line total</th></tr></thead>
    <tbody>
      <?php foreach ($items as $item): ?>
      <tr>
        <td><?= clean($item['product_name']) ?></td>
        <td class="mono"><?= clean($item['sku']) ?></td>
        <td class="mono"><?= (int)$item['quantity_ordered'] ?></td>
        <td class="mono <?= $item['quantity_received'] < $item['quantity_ordered'] ? 'num-danger' : '' ?>"><?= (int)$item['quantity_received'] ?></td>
        <td class="mono"><?= formatMoney($item['unit_cost']) ?></td>
        <td class="mono"><?= formatMoney($item['quantity_ordered'] * $item['unit_cost']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>

  <div class="form-actions">
    <a href="index.php" class="btn btn-secondary">Back to purchase orders</a>

    <?php if (hasPermission('purchasing.create') && $po['status'] === 'draft'): ?>
      <form method="post" style="display:inline">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="action" value="mark_ordered">
        <button type="submit" class="btn btn-secondary">Mark as ordered</button>
      </form>
    <?php endif; ?>

    <?php if (hasPermission('purchasing.receive') && in_array($po['status'], ['ordered', 'partially_received'], true) && $canReceiveHere): ?>
      <a href="receive.php?id=<?= (int)$po['id'] ?>" class="btn btn-primary"><?= icon('box-check', 15) ?> Receive stock</a>
    <?php elseif (in_array($po['status'], ['ordered', 'partially_received'], true) && !$canReceiveHere): ?>
      <p class="muted small" style="align-self:center;">Switch to <?= clean($po['branch_name']) ?> to receive this stock.</p>
    <?php endif; ?>

    <?php if (hasPermission('purchasing.create') && in_array($po['status'], ['draft', 'ordered'], true)): ?>
      <form method="post" class="js-confirm" data-confirm-title="Cancel this purchase order?" data-confirm-message="You can't undo this. Create a new one if you need to reorder.">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="action" value="cancel">
        <button type="submit" class="btn btn-danger">Cancel PO</button>
      </form>
    <?php endif; ?>
  </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
