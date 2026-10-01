<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/rbac.php';
requirePermission('expenses.view');

$pageTitle = 'Expenses';
$canManage = hasPermission('expenses.manage');
$branchId = currentBranchId() ?? requireActiveBranch($pdo);
$errors = [];

$commonCategories = ['Rent', 'Utilities', 'Salaries', 'Transport', 'Supplies', 'Maintenance', 'Marketing', 'Other'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canManage) {
    csrfCheck();
    $expenseDate = $_POST['expense_date'] ?? date('Y-m-d');
    $category = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $amount = round((float)($_POST['amount'] ?? 0), 2);
    $method = in_array($_POST['payment_method'] ?? '', ['cash','mobile_money','bank_transfer'], true)
        ? $_POST['payment_method'] : 'cash';
    $expenseBranchId = (int)($_POST['branch_id'] ?? $branchId);

    if ($category === '') $errors[] = 'Choose or enter a category.';
    if ($amount <= 0) $errors[] = 'Amount must be greater than zero.';

    if (!$errors) {
        $pdo->prepare(
            "INSERT INTO expenses (branch_id, expense_date, category, description, amount, payment_method, recorded_by) VALUES (?,?,?,?,?,?,?)"
        )->execute([$expenseBranchId, $expenseDate, $category, $description ?: null, $amount, $method, $_SESSION['user_id']]);
        refreshCashLedgerCascade($pdo, $expenseBranchId, $expenseDate);
        logActivity('expenses.manage', "Recorded $category expense of " . formatMoney($amount) . " at " . getBranchName($expenseBranchId, $pdo));
        setFlash('success', 'Expense recorded.');
        redirect('modules/expenses/index.php');
    }
}

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to'] ?? date('Y-m-d');
$sql = "SELECT e.*, b.name AS branch_name, u.full_name AS recorder_name FROM expenses e
        JOIN branches b ON b.id = e.branch_id JOIN users u ON u.id = e.recorded_by
        WHERE e.expense_date BETWEEN ? AND ?";
$params = [$from, $to];
if ($branchId !== null) {
    $sql .= " AND e.branch_id = ?";
    $params[] = $branchId;
}
$sql .= " ORDER BY e.expense_date DESC, e.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$expenses = $stmt->fetchAll();
$totalInRange = array_sum(array_column($expenses, 'amount'));

$branchesByTown = listBranchesByTown($pdo);

require_once __DIR__ . '/../../includes/header.php';
?>

<?php if ($canManage): ?>
<section class="panel panel-form">
  <h2>Record expense</h2>
  <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= clean($err) ?></div><?php endforeach; ?>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
    <div class="form-row">
      <div class="form-field">
        <label>Date</label>
        <input type="date" name="expense_date" value="<?= date('Y-m-d') ?>" required>
      </div>
      <?php if ($branchId === null): ?>
      <div class="form-field">
        <label>Branch</label>
        <select name="branch_id">
          <?php foreach ($branchesByTown as $townName => $townBranches): ?>
            <optgroup label="<?= clean($townName) ?>">
              <?php foreach ($townBranches as $b): ?>
                <option value="<?= $b['id'] ?>"><?= clean($b['name']) ?></option>
              <?php endforeach; ?>
            </optgroup>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
    </div>
    <div class="form-row">
      <div class="form-field">
        <label>Category</label>
        <input type="text" name="category" list="expenseCategories" placeholder="e.g. Rent" required>
        <datalist id="expenseCategories">
          <?php foreach ($commonCategories as $c): ?><option value="<?= clean($c) ?>"><?php endforeach; ?>
        </datalist>
      </div>
      <div class="form-field">
        <label>Amount</label>
        <input type="number" name="amount" min="0.01" step="0.01" required>
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
      <label>Description (optional)</label>
      <input type="text" name="description" placeholder="e.g. September rent, branch generator fuel">
    </div>
    <div class="form-actions">
      <button type="submit" class="btn btn-primary">Record expense</button>
    </div>
  </form>
</section>
<?php endif; ?>

<form method="get" class="toolbar">
  <p class="muted">Total in range: <strong><?= formatMoney($totalInRange) ?></strong></p>
  <div class="search-form">
    <input type="date" name="from" value="<?= clean($from) ?>">
    <span class="muted">to</span>
    <input type="date" name="to" value="<?= clean($to) ?>">
    <button type="submit" class="btn btn-secondary">Filter</button>
  </div>
</form>

<section class="panel">
  <?php if ($expenses): ?>
  <div class="data-table-wrap">
  <table class="data-table" data-has-server-search="true">
    <thead><tr><th>Date</th><?= $branchId === null ? '<th>Branch</th>' : '' ?><th>Category</th><th>Description</th><th>Amount</th><th>Method</th><th>Recorded by</th></tr></thead>
    <tbody>
      <?php foreach ($expenses as $e): ?>
      <tr>
        <td class="mono"><?= date('d M Y', strtotime($e['expense_date'])) ?></td>
        <?= $branchId === null ? '<td>' . clean($e['branch_name']) . '</td>' : '' ?>
        <td><?= clean($e['category']) ?></td>
        <td><?= clean($e['description'] ?? '—') ?></td>
        <td class="mono"><?= formatMoney($e['amount']) ?></td>
        <td><?= clean(ucwords(str_replace('_', ' ', $e['payment_method']))) ?></td>
        <td class="muted small"><?= clean($e['recorder_name']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php else: ?>
    <p class="empty-state">No expenses in this date range.</p>
  <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
