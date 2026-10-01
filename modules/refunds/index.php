<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/rbac.php';
requirePermission('refunds.view');

$pageTitle = 'Refunds';
$canManage = hasPermission('refunds.manage');
$branchId = currentBranchId();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canManage) {
    csrfCheck();
    $orderNumber = trim($_POST['order_number'] ?? '');
    $amount = round((float)($_POST['amount'] ?? 0), 2);
    $reason = trim($_POST['reason'] ?? '');
    $method = in_array($_POST['payment_method'] ?? '', ['cash','mobile_money','bank_transfer'], true)
        ? $_POST['payment_method'] : 'cash';

    $order = null;
    if ($orderNumber !== '') {
        $stmt = $pdo->prepare("SELECT id, total_amount, branch_id FROM orders WHERE order_number = ?");
        $stmt->execute([$orderNumber]);
        $order = $stmt->fetch();
    }

    if (!$order) $errors[] = 'Order number not found.';
    if ($amount <= 0) $errors[] = 'Amount must be greater than zero.';
    if ($reason === '') $errors[] = 'Enter a reason for the refund.';

    if (!$errors) {
        $pdo->prepare(
            "INSERT INTO refunds (order_id, amount, reason, payment_method, processed_by) VALUES (?,?,?,?,?)"
        )->execute([$order['id'], $amount, $reason, $method, $_SESSION['user_id']]);
        refreshCashLedgerCascade($pdo, $order['branch_id'], date('Y-m-d'));
        logActivity('refunds.manage', "Processed refund of " . formatMoney($amount) . " for order $orderNumber");
        setFlash('success', 'Refund recorded.');
        redirect('modules/refunds/index.php');
    }
}

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to'] ?? date('Y-m-d');
$sql = "SELECT r.*, o.order_number, b.name AS branch_name, u.full_name AS processor_name FROM refunds r
        JOIN orders o ON o.id = r.order_id JOIN branches b ON b.id = o.branch_id JOIN users u ON u.id = r.processed_by
        WHERE DATE(r.created_at) BETWEEN ? AND ?";
$params = [$from, $to];
if ($branchId !== null) {
    $sql .= " AND o.branch_id = ?";
    $params[] = $branchId;
}
$sql .= " ORDER BY r.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$refunds = $stmt->fetchAll();
$totalInRange = array_sum(array_column($refunds, 'amount'));

require_once __DIR__ . '/../../includes/header.php';
?>

<?php if ($canManage): ?>
<section class="panel panel-form">
  <h2>Process refund</h2>
  <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= clean($err) ?></div><?php endforeach; ?>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
    <div class="form-row">
      <div class="form-field">
        <label>Order number</label>
        <input type="text" name="order_number" placeholder="e.g. ORD-20260924-A1B2C" required>
      </div>
      <div class="form-field">
        <label>Amount</label>
        <input type="number" name="amount" min="0.01" step="0.01" required>
      </div>
      <div class="form-field">
        <label>Refunded via</label>
        <select name="payment_method">
          <option value="cash">Cash</option>
          <option value="mobile_money">Mobile Money</option>
          <option value="bank_transfer">Bank transfer</option>
        </select>
      </div>
    </div>
    <div class="form-field">
      <label>Reason</label>
      <input type="text" name="reason" placeholder="e.g. Defective item returned" required>
    </div>
    <div class="form-actions">
      <button type="submit" class="btn btn-primary">Process refund</button>
    </div>
  </form>
</section>
<?php endif; ?>

<form method="get" class="toolbar">
  <p class="muted">Total refunded in range: <strong><?= formatMoney($totalInRange) ?></strong></p>
  <div class="search-form">
    <input type="date" name="from" value="<?= clean($from) ?>">
    <span class="muted">to</span>
    <input type="date" name="to" value="<?= clean($to) ?>">
    <button type="submit" class="btn btn-secondary">Filter</button>
  </div>
</form>

<section class="panel">
  <?php if ($refunds): ?>
  <div class="data-table-wrap">
  <table class="data-table" data-has-server-search="true">
    <thead><tr><th>Date</th><th>Order #</th><?= $branchId === null ? '<th>Branch</th>' : '' ?><th>Amount</th><th>Reason</th><th>Method</th><th>Processed by</th></tr></thead>
    <tbody>
      <?php foreach ($refunds as $r): ?>
      <tr>
        <td class="mono"><?= date('d M Y', strtotime($r['created_at'])) ?></td>
        <td class="mono"><a href="../orders/view.php?id=<?= (int)$r['order_id'] ?>" class="link"><?= clean($r['order_number']) ?></a></td>
        <?= $branchId === null ? '<td>' . clean($r['branch_name']) . '</td>' : '' ?>
        <td class="mono num-danger"><?= formatMoney($r['amount']) ?></td>
        <td><?= clean($r['reason']) ?></td>
        <td><?= clean(ucwords(str_replace('_', ' ', $r['payment_method']))) ?></td>
        <td class="muted small"><?= clean($r['processor_name']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php else: ?>
    <p class="empty-state">No refunds in this date range.</p>
  <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
