<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/rbac.php';
requirePermission('orders.view');

$pageTitle = 'Order details';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare(
    "SELECT o.*, c.name AS customer_name, c.phone AS customer_phone, u.full_name AS staff_name, b.name AS branch_name, cu.full_name AS canceller_name
     FROM orders o
     LEFT JOIN customers c ON c.id = o.customer_id
     JOIN users u ON u.id = o.user_id
     JOIN branches b ON b.id = o.branch_id
     LEFT JOIN users cu ON cu.id = o.cancelled_by
     WHERE o.id = ?"
);
$stmt->execute([$id]);
$order = $stmt->fetch();

if (!$order) {
    setFlash('error', 'Order not found.');
    redirect('modules/orders/index.php');
}

$items = $pdo->prepare(
    "SELECT oi.*, p.name AS product_name, p.sku FROM order_items oi JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ?"
);
$items->execute([$id]);
$items = $items->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && hasPermission('orders.cancel')) {
    csrfCheck();
    if ($order['status'] !== 'cancelled') {
        $pdo->beginTransaction();
        try {
            // Cancelling returns the stock to the branch it was sold from — logged as an 'in' movement referencing the order
            foreach ($items as $item) {
                $pdo->prepare(
                    "INSERT INTO branch_stock (branch_id, product_id, quantity) VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)"
                )->execute([$order['branch_id'], $item['product_id'], $item['quantity']]);
                $pdo->prepare(
                    "INSERT INTO stock_movements (branch_id, product_id, type, category, quantity, reason, reference, user_id) VALUES (?, ?, 'in', 'order', ?, 'Order cancelled — stock returned', ?, ?)"
                )->execute([$order['branch_id'], $item['product_id'], $item['quantity'], $order['order_number'], $_SESSION['user_id']]);
            }
            $pdo->prepare("UPDATE orders SET status = 'cancelled', cancelled_by = ? WHERE id = ?")->execute([$_SESSION['user_id'], $id]);
            $pdo->commit();

            logActivity('orders.cancel', "Cancelled order {$order['order_number']}");
            setFlash('success', 'Order cancelled and stock returned.');
            redirect('modules/orders/view.php?id=' . $id);
        } catch (Exception $e) {
            $pdo->rollBack();
            setFlash('error', 'Could not cancel the order.');
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<section class="panel panel-wide">
  <div class="detail-header">
    <div>
      <h2 class="mono"><?= clean($order['order_number']) ?></h2>
      <p class="muted"><?= date('d M Y, H:i', strtotime($order['created_at'])) ?> · Handled by <?= clean($order['staff_name']) ?> · <?= clean($order['branch_name']) ?><?= $order['canceller_name'] ? ' · Cancelled by ' . clean($order['canceller_name']) : '' ?></p>
    </div>
    <span class="badge badge-<?= clean($order['status']) ?>"><?= clean($order['status']) ?></span>
  </div>

  <div class="detail-grid">
    <div><span class="muted">Customer</span><br><?= clean($order['customer_name'] ?? 'Walk-in') ?></div>
    <?php if ($order['customer_phone']): ?>
      <div><span class="muted">Phone</span><br><?= clean($order['customer_phone']) ?></div>
    <?php endif; ?>
    <div><span class="muted">Total</span><br><strong class="mono"><?= formatMoney($order['total_amount']) ?></strong></div>
  </div>

  <table class="data-table">
    <thead><tr><th>Product</th><th>SKU</th><th>Qty</th><th>Unit price</th><th>Subtotal</th></tr></thead>
    <tbody>
      <?php foreach ($items as $item): ?>
      <tr>
        <td><?= clean($item['product_name']) ?></td>
        <td class="mono"><?= clean($item['sku']) ?></td>
        <td class="mono"><?= (int)$item['quantity'] ?></td>
        <td class="mono"><?= formatMoney($item['unit_price']) ?></td>
        <td class="mono"><?= formatMoney($item['subtotal']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div class="form-actions">
    <a href="index.php" class="btn btn-secondary">Back to orders</a>
    <a href="print.php?id=<?= (int)$order['id'] ?>" target="_blank" class="btn btn-secondary"><?= icon('print', 15) ?> Print invoice</a>
    <?php if (hasPermission('orders.cancel') && $order['status'] !== 'cancelled'): ?>
      <form method="post" class="js-confirm" data-confirm-title="Cancel this order?" data-confirm-message="The stock from this order will be returned to inventory automatically.">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <button type="submit" class="btn btn-danger">Cancel order</button>
      </form>
    <?php endif; ?>
  </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
