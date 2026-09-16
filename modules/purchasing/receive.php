<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/rbac.php';
requirePermission('purchasing.receive');

$pageTitle = 'Receive stock';

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$stmt = $pdo->prepare("SELECT po.*, s.name AS supplier_name FROM purchase_orders po JOIN suppliers s ON s.id = po.supplier_id WHERE po.id = ?");
$stmt->execute([$id]);
$po = $stmt->fetch();

if (!$po || !in_array($po['status'], ['ordered', 'partially_received'], true)) {
    setFlash('error', 'This purchase order is not awaiting receipt.');
    redirect('modules/purchasing/index.php');
}

if (!isMultiBranchUser() && currentBranchId() !== (int)$po['branch_id']) {
    setFlash('error', 'Switch to the receiving branch first.');
    redirect('modules/purchasing/view.php?id=' . $id);
}

$items = $pdo->prepare(
    "SELECT poi.*, p.name AS product_name, p.sku
     FROM purchase_order_items poi JOIN products p ON p.id = poi.product_id
     WHERE poi.po_id = ? AND poi.quantity_received < poi.quantity_ordered"
);
$items->execute([$id]);
$items = $items->fetchAll();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    $receiveQty = $_POST['receive_qty'] ?? [];

    $pdo->beginTransaction();
    try {
        $anyReceived = false;
        foreach ($items as $item) {
            $qty = (int)($receiveQty[$item['id']] ?? 0);
            $remaining = $item['quantity_ordered'] - $item['quantity_received'];
            if ($qty <= 0) continue;
            if ($qty > $remaining) $qty = $remaining;

            $pdo->prepare("UPDATE purchase_order_items SET quantity_received = quantity_received + ? WHERE id = ?")
                ->execute([$qty, $item['id']]);
            $pdo->prepare(
                "INSERT INTO branch_stock (branch_id, product_id, quantity) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)"
            )->execute([$po['branch_id'], $item['product_id'], $qty]);
            $pdo->prepare(
                "INSERT INTO stock_movements (branch_id, product_id, type, category, quantity, reason, reference, user_id) VALUES (?, ?, 'in', 'purchase', ?, 'Purchase order receipt', ?, ?)"
            )->execute([$po['branch_id'], $item['product_id'], $qty, $po['po_number'], $_SESSION['user_id']]);
            $anyReceived = true;
        }

        if (!$anyReceived) {
            $errors[] = 'Enter a quantity for at least one item.';
            $pdo->rollBack();
        } else {
            // Recompute PO-level status from the items table
            $remainingCount = $pdo->prepare("SELECT COUNT(*) c FROM purchase_order_items WHERE po_id = ? AND quantity_received < quantity_ordered");
            $remainingCount->execute([$id]);
            $stillOpen = $remainingCount->fetch()['c'];

            $newStatus = $stillOpen > 0 ? 'partially_received' : 'received';
            $receivedAt = $stillOpen > 0 ? null : date('Y-m-d H:i:s');
            $receivedBy = $stillOpen > 0 ? null : $_SESSION['user_id'];
            $pdo->prepare(
                "UPDATE purchase_orders SET status = ?, received_at = COALESCE(?, received_at), received_by = COALESCE(?, received_by) WHERE id = ?"
            )->execute([$newStatus, $receivedAt, $receivedBy, $id]);

            $pdo->commit();
            logActivity('purchasing.receive', "Received stock for {$po['po_number']}");
            setFlash('success', 'Stock received and inventory updated.');
            redirect('modules/purchasing/view.php?id=' . $id);
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $errors[] = 'Could not record receipt. Try again.';
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<section class="panel panel-form panel-wide">
  <h2>Receive stock — <span class="mono muted"><?= clean($po['po_number']) ?></span></h2>
  <p class="muted">From <?= clean($po['supplier_name']) ?>. Enter how many units actually arrived — partial deliveries are fine, receive the rest later.</p>

  <?php foreach ($errors as $err): ?>
    <div class="alert alert-error"><?= clean($err) ?></div>
  <?php endforeach; ?>

  <?php if ($items): ?>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
    <input type="hidden" name="id" value="<?= (int)$id ?>">

    <div class="data-table-wrap">
    <table class="data-table">
      <thead><tr><th>Product</th><th>SKU</th><th>Ordered</th><th>Already received</th><th style="width:140px">Receive now</th></tr></thead>
      <tbody>
        <?php foreach ($items as $item): $remaining = $item['quantity_ordered'] - $item['quantity_received']; ?>
        <tr>
          <td><?= clean($item['product_name']) ?></td>
          <td class="mono"><?= clean($item['sku']) ?></td>
          <td class="mono"><?= (int)$item['quantity_ordered'] ?></td>
          <td class="mono"><?= (int)$item['quantity_received'] ?></td>
          <td><input type="number" name="receive_qty[<?= (int)$item['id'] ?>]" min="0" max="<?= (int)$remaining ?>" value="<?= (int)$remaining ?>"></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>

    <div class="form-actions">
      <a href="view.php?id=<?= (int)$id ?>" class="btn btn-secondary">Cancel</a>
      <button type="submit" class="btn btn-primary"><?= icon('box-check', 15) ?> Confirm receipt</button>
    </div>
  </form>
  <?php else: ?>
    <p class="empty-state">All items on this purchase order have already been received.</p>
  <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
