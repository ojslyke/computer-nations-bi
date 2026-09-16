<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/rbac.php';
requirePermission('orders.view');

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare(
    "SELECT o.*, c.name AS customer_name, c.phone AS customer_phone, c.address AS customer_address, u.full_name AS staff_name
     FROM orders o
     LEFT JOIN customers c ON c.id = o.customer_id
     JOIN users u ON u.id = o.user_id
     WHERE o.id = ?"
);
$stmt->execute([$id]);
$order = $stmt->fetch();

if (!$order) {
    redirect('modules/orders/index.php');
}

$items = $pdo->prepare(
    "SELECT oi.*, p.name AS product_name, p.sku FROM order_items oi JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ?"
);
$items->execute([$id]);
$items = $items->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Invoice <?= clean($order['order_number']) ?> — <?= SITE_NAME ?></title>
<style>
  * { box-sizing: border-box; }
  body { font-family: 'Helvetica Neue', Arial, sans-serif; color: #101828; padding: 50px; max-width: 720px; margin: 0 auto; }
  .invoice-head { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid #12172A; padding-bottom: 20px; margin-bottom: 24px; }
  .brand { font-size: 20px; font-weight: 700; }
  .brand span { color: #2F6FED; }
  .meta { text-align: right; font-size: 13px; color: #667085; }
  .meta strong { color: #101828; font-size: 15px; display: block; margin-bottom: 4px; font-family: monospace; }
  .parties { display: flex; justify-content: space-between; margin-bottom: 30px; }
  .parties h4 { font-size: 11px; text-transform: uppercase; letter-spacing: 0.04em; color: #98A2B3; margin: 0 0 6px; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
  th { text-align: left; font-size: 11px; text-transform: uppercase; color: #98A2B3; border-bottom: 2px solid #12172A; padding: 8px 6px; }
  td { padding: 10px 6px; border-bottom: 1px solid #E4E7EC; font-size: 13.5px; }
  .num { text-align: right; font-family: monospace; }
  .totals { display: flex; justify-content: flex-end; }
  .totals table { width: 260px; }
  .totals td { border: none; padding: 6px; }
  .totals .grand { font-size: 16px; font-weight: 700; border-top: 2px solid #12172A; }
  .status { display: inline-block; padding: 3px 10px; border-radius: 100px; font-size: 11px; font-weight: 600; background: #ECFDF3; color: #027A48; text-transform: capitalize; }
  .footer-note { margin-top: 40px; font-size: 12px; color: #98A2B3; text-align: center; }
  .print-bar { text-align: right; margin-bottom: 20px; }
  .print-bar button { background: #2F6FED; color: #fff; border: none; padding: 9px 16px; border-radius: 6px; font-size: 13px; cursor: pointer; }
  @media print { .print-bar { display: none; } body { padding: 0; } }
</style>
</head>
<body>

<div class="print-bar"><button onclick="window.print()">Print / Save as PDF</button></div>

<div class="invoice-head">
  <div class="brand"><?= SITE_NAME ?> <span>·</span> Invoice</div>
  <div class="meta">
    <strong><?= clean($order['order_number']) ?></strong>
    <?= date('d M Y, H:i', strtotime($order['created_at'])) ?><br>
    <span class="status"><?= clean($order['status']) ?></span>
  </div>
</div>

<div class="parties">
  <div>
    <h4>Billed to</h4>
    <?= clean($order['customer_name'] ?? 'Walk-in customer') ?><br>
    <?php if ($order['customer_phone']): ?><?= clean($order['customer_phone']) ?><br><?php endif; ?>
    <?php if ($order['customer_address']): ?><?= clean($order['customer_address']) ?><?php endif; ?>
  </div>
  <div>
    <h4>Served by</h4>
    <?= clean($order['staff_name']) ?>
  </div>
</div>

<table>
  <thead><tr><th>Item</th><th>SKU</th><th class="num">Qty</th><th class="num">Unit price</th><th class="num">Subtotal</th></tr></thead>
  <tbody>
    <?php foreach ($items as $item): ?>
    <tr>
      <td><?= clean($item['product_name']) ?></td>
      <td class="num"><?= clean($item['sku']) ?></td>
      <td class="num"><?= (int)$item['quantity'] ?></td>
      <td class="num"><?= formatMoney($item['unit_price']) ?></td>
      <td class="num"><?= formatMoney($item['subtotal']) ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<div class="totals">
  <table>
    <tr class="grand"><td>Total</td><td class="num"><?= formatMoney($order['total_amount']) ?></td></tr>
  </table>
</div>

<div class="footer-note">Thank you for your business — <?= SITE_NAME ?></div>

</body>
</html>
