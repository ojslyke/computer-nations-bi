<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/rbac.php';
requirePermission('funds.view');

$pageTitle = 'Fund transfers';
$canManage = hasPermission('funds.manage');
$branchId = currentBranchId() ?? requireActiveBranch($pdo);
$errors = [];
$branchesByTown = listBranchesByTown($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canManage) {
    csrfCheck();
    $transferBranchId = (int)($_POST['branch_id'] ?? $branchId);
    $destination = trim($_POST['destination'] ?? '');
    $amount = round((float)($_POST['amount'] ?? 0), 2);
    $method = in_array($_POST['method'] ?? '', ['cash','mobile_money','bank_transfer'], true)
        ? $_POST['method'] : 'bank_transfer';
    $notes = trim($_POST['notes'] ?? '');

    if ($destination === '') $errors[] = 'Enter the destination account.';
    if ($amount <= 0) $errors[] = 'Amount must be greater than zero.';

    if (!$errors) {
        $transferNumber = 'FND-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
        $pdo->prepare(
            "INSERT INTO fund_transfers (transfer_number, branch_id, destination, amount, method, notes, transferred_by) VALUES (?,?,?,?,?,?,?)"
        )->execute([$transferNumber, $transferBranchId, $destination, $amount, $method, $notes ?: null, $_SESSION['user_id']]);
        refreshCashLedgerCascade($pdo, $transferBranchId, date('Y-m-d'));
        logActivity('funds.manage', "Transferred " . formatMoney($amount) . " from " . getBranchName($transferBranchId, $pdo) . " to $destination");
        setFlash('success', "$transferNumber recorded.");
        redirect('modules/funds/index.php');
    }
}

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to'] ?? date('Y-m-d');
$sql = "SELECT ft.*, b.name AS branch_name, u.full_name AS transferrer_name FROM fund_transfers ft
        JOIN branches b ON b.id = ft.branch_id JOIN users u ON u.id = ft.transferred_by
        WHERE DATE(ft.created_at) BETWEEN ? AND ?";
$params = [$from, $to];
if ($branchId !== null) {
    $sql .= " AND ft.branch_id = ?";
    $params[] = $branchId;
}
$sql .= " ORDER BY ft.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$transfers = $stmt->fetchAll();
$totalInRange = array_sum(array_column($transfers, 'amount'));

require_once __DIR__ . '/../../includes/header.php';
?>

<?php if ($canManage): ?>
<section class="panel panel-form">
  <h2>Record fund transfer</h2>
  <p class="muted small">Money moving out of a branch's till into an actual bank or credit union account.</p>
  <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= clean($err) ?></div><?php endforeach; ?>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
    <div class="form-row">
      <div class="form-field">
        <label>From branch</label>
        <select name="branch_id">
          <?php foreach ($branchesByTown as $townName => $townBranches): ?>
            <optgroup label="<?= clean($townName) ?>">
              <?php foreach ($townBranches as $b): ?>
                <option value="<?= $b['id'] ?>" <?= $b['id'] == $branchId ? 'selected' : '' ?>><?= clean($b['name']) ?></option>
              <?php endforeach; ?>
            </optgroup>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-field">
        <label>Amount</label>
        <input type="number" name="amount" min="0.01" step="0.01" required>
      </div>
      <div class="form-field">
        <label>Method</label>
        <select name="method">
          <option value="bank_transfer">Bank transfer</option>
          <option value="mobile_money">Mobile Money</option>
          <option value="cash">Cash deposit</option>
        </select>
      </div>
    </div>
    <div class="form-field">
      <label>Destination account</label>
      <input type="text" name="destination" placeholder="e.g. Afriland First Bank — Computer Nations Ltd" required>
    </div>
    <div class="form-field">
      <label>Notes (optional)</label>
      <input type="text" name="notes" placeholder="e.g. Weekly cash deposit">
    </div>
    <div class="form-actions">
      <button type="submit" class="btn btn-primary">Record transfer</button>
    </div>
  </form>
</section>
<?php endif; ?>

<form method="get" class="toolbar">
  <p class="muted">Total transferred in range: <strong><?= formatMoney($totalInRange) ?></strong></p>
  <div class="search-form">
    <input type="date" name="from" value="<?= clean($from) ?>">
    <span class="muted">to</span>
    <input type="date" name="to" value="<?= clean($to) ?>">
    <button type="submit" class="btn btn-secondary">Filter</button>
  </div>
</form>

<section class="panel">
  <?php if ($transfers): ?>
  <div class="data-table-wrap">
  <table class="data-table" data-has-server-search="true">
    <thead><tr><th>Transfer #</th><th>Date</th><?= $branchId === null ? '<th>Branch</th>' : '' ?><th>Destination</th><th>Amount</th><th>Method</th><th>By</th></tr></thead>
    <tbody>
      <?php foreach ($transfers as $t): ?>
      <tr>
        <td class="mono"><?= clean($t['transfer_number']) ?></td>
        <td class="mono"><?= date('d M Y', strtotime($t['created_at'])) ?></td>
        <?= $branchId === null ? '<td>' . clean($t['branch_name']) . '</td>' : '' ?>
        <td><?= clean($t['destination']) ?></td>
        <td class="mono"><?= formatMoney($t['amount']) ?></td>
        <td><?= clean(ucwords(str_replace('_', ' ', $t['method']))) ?></td>
        <td class="muted small"><?= clean($t['transferrer_name']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php else: ?>
    <p class="empty-state">No fund transfers in this date range.</p>
  <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
