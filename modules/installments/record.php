<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/rbac.php';
requirePermission('installments.view');

$pageTitle = 'Record payment';
$orderId = (int)($_GET['order'] ?? 0);

$stmt = $pdo->prepare(
    "SELECT o.*, c.name AS customer_name, b.name AS branch_name FROM orders o
     LEFT JOIN customers c ON c.id = o.customer_id JOIN branches b ON b.id = o.branch_id
     WHERE o.id = ?"
);
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    setFlash('error', 'Order not found.');
    redirect('modules/installments/index.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && hasPermission('installments.manage')) {
    csrfCheck();
    $balance = $order['total_amount'] - $order['amount_paid'];
    $amount = round((float)($_POST['amount'] ?? 0), 2);
    $method = in_array($_POST['payment_method'] ?? '', ['cash','mobile_money','bank_transfer'], true)
        ? $_POST['payment_method'] : 'cash';
    $notes = trim($_POST['notes'] ?? '');

    if ($amount <= 0) {
        $errors[] = 'Enter an amount greater than zero.';
    } elseif ($amount > $balance + 0.01) {
        $errors[] = 'That\'s more than the remaining balance (' . formatMoney($balance) . ').';
    }

    if (!$errors) {
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                "INSERT INTO installment_payments (order_id, amount, payment_method, notes, received_by) VALUES (?,?,?,?,?)"
            )->execute([$orderId, $amount, $method, $notes ?: null, $_SESSION['user_id']]);

            $newPaid = $order['amount_paid'] + $amount;
            $newStatus = $newPaid >= $order['total_amount'] - 0.01 ? 'paid' : ($newPaid > 0 ? 'partial' : 'unpaid');
            $pdo->prepare("UPDATE orders SET amount_paid = ?, payment_status = ? WHERE id = ?")
                ->execute([$newPaid, $newStatus, $orderId]);
            refreshCashLedgerCascade($pdo, $order['branch_id'], date('Y-m-d'));

            $pdo->commit();
            logActivity('installments.manage', "Recorded " . formatMoney($amount) . " payment on {$order['order_number']}");
            setFlash('success', formatMoney($amount) . ' recorded.' . ($newStatus === 'paid' ? ' Order is now fully paid.' : ''));
            redirect('modules/installments/record.php?order=' . $orderId);
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Could not record the payment. Try again.';
        }
    }
    // Refresh order data after a failed/succeeded attempt
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
}

$payments = $pdo->prepare(
    "SELECT ip.*, u.full_name FROM installment_payments ip JOIN users u ON u.id = ip.received_by
     WHERE ip.order_id = ? ORDER BY ip.created_at ASC"
);
$payments->execute([$orderId]);
$payments = $payments->fetchAll();
$balance = $order['total_amount'] - $order['amount_paid'];

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="panel-grid">

<section class="panel">
  <div class="detail-header">
    <div>
      <h2 class="mono"><?= clean($order['order_number']) ?></h2>
      <p class="muted"><?= clean($order['customer_name'] ?? 'Walk-in') ?> · <?= clean($order['branch_name']) ?></p>
    </div>
    <span class="badge badge-pending"><?= clean(ucfirst($order['payment_status'])) ?></span>
  </div>

  <div class="detail-grid">
    <div><span class="muted">Total</span><br><?= formatMoney($order['total_amount']) ?></div>
    <div><span class="muted">Paid so far</span><br><?= formatMoney($order['amount_paid']) ?></div>
    <div><span class="muted">Balance</span><br><strong><?= formatMoney($balance) ?></strong></div>
  </div>

  <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= clean($err) ?></div><?php endforeach; ?>

  <?php if (hasPermission('installments.manage') && $balance > 0.01): ?>
    <form method="post" style="margin-top:16px;">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
      <div class="form-row">
        <div class="form-field">
          <label>Amount received</label>
          <input type="number" name="amount" min="0.01" max="<?= $balance ?>" step="0.01" required>
        </div>
        <div class="form-field">
          <label>Payment method</label>
          <select name="payment_method">
            <option value="cash">Cash</option>
            <option value="mobile_money">Mobile Money</option>
            <option value="bank_transfer">Bank transfer</option>
          </select>
        </div>
      </div>
      <div class="form-field">
        <label>Notes (optional)</label>
        <input type="text" name="notes" placeholder="e.g. 2nd installment">
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary">Record payment</button>
      </div>
    </form>
  <?php elseif ($balance <= 0.01): ?>
    <div class="alert alert-success" style="margin-top:16px;">This order is fully paid.</div>
  <?php endif; ?>
</section>

<section class="panel">
  <h2>Payment history</h2>
  <div class="timeline">
    <?php foreach ($payments as $p): ?>
      <div class="timeline-item">
        <div class="timeline-dot"><?= icon('box-check', 15) ?></div>
        <div class="timeline-body">
          <div><strong><?= formatMoney($p['amount']) ?></strong> — <?= clean(ucwords(str_replace('_', ' ', $p['payment_method']))) ?></div>
          <?php if ($p['notes']): ?><div class="muted small"><?= clean($p['notes']) ?></div><?php endif; ?>
          <div class="timeline-meta mono"><?= date('d M Y, H:i', strtotime($p['created_at'])) ?> · <?= clean($p['full_name']) ?></div>
        </div>
      </div>
    <?php endforeach; ?>
    <?php if (!$payments): ?><p class="empty-state">No payments recorded yet.</p><?php endif; ?>
  </div>
</section>

</div>

<a href="index.php" class="btn btn-secondary">Back to installments</a>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
