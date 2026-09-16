<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/rbac.php';
requirePermission('purchasing.create');

$pageTitle = 'New purchase order';
$errors = [];
$defaultBranchId = requireActiveBranch($pdo);

$suppliers = $pdo->query("SELECT id, name FROM suppliers ORDER BY name")->fetchAll();
$products  = $pdo->query("SELECT id, name, sku, cost_price FROM products WHERE status='active' ORDER BY name")->fetchAll();
$branchesByTown = listBranchesByTown($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();

    $branchId   = (int)$_POST['branch_id'];
    $supplierId = (int)$_POST['supplier_id'];
    $status     = $_POST['status'] === 'ordered' ? 'ordered' : 'draft';
    $notes      = trim($_POST['notes'] ?? '');
    $productIds = $_POST['product_id'] ?? [];
    $quantities = $_POST['quantity'] ?? [];
    $costs      = $_POST['unit_cost'] ?? [];

    $lineItems = [];
    foreach ($productIds as $i => $pid) {
        $pid = (int)$pid;
        $qty = (int)($quantities[$i] ?? 0);
        $cost = (float)($costs[$i] ?? 0);
        if ($pid && $qty > 0) {
            $lineItems[] = ['product_id' => $pid, 'quantity' => $qty, 'unit_cost' => $cost];
        }
    }

    if (!$branchId) $errors[] = 'Choose a receiving branch.';
    if (!$supplierId) $errors[] = 'Choose a supplier.';
    if (!$lineItems) $errors[] = 'Add at least one product with a quantity.';

    if (!$errors) {
        $pdo->beginTransaction();
        try {
            $poNumber = generatePoNumber();
            $pdo->prepare(
                "INSERT INTO purchase_orders (po_number, branch_id, supplier_id, user_id, status, notes) VALUES (?,?,?,?,?,?)"
            )->execute([$poNumber, $branchId, $supplierId, $_SESSION['user_id'], $status, $notes ?: null]);
            $poId = $pdo->lastInsertId();

            $stmt = $pdo->prepare(
                "INSERT INTO purchase_order_items (po_id, product_id, quantity_ordered, unit_cost) VALUES (?,?,?,?)"
            );
            foreach ($lineItems as $item) {
                $stmt->execute([$poId, $item['product_id'], $item['quantity'], $item['unit_cost']]);
            }

            $pdo->commit();
            logActivity('purchasing.create', "Created purchase order $poNumber");
            setFlash('success', "Purchase order $poNumber created.");
            redirect('modules/purchasing/view.php?id=' . $poId);
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Could not create the purchase order. Try again.';
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<section class="panel panel-form panel-wide">
  <h2>New purchase order</h2>

  <?php foreach ($errors as $err): ?>
    <div class="alert alert-error"><?= clean($err) ?></div>
  <?php endforeach; ?>

  <form method="post" id="poForm">
    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

    <div class="form-row">
      <div class="form-field">
        <label>Receiving branch</label>
        <select name="branch_id" required>
          <?php foreach ($branchesByTown as $townName => $townBranches): ?>
            <optgroup label="<?= clean($townName) ?>">
              <?php foreach ($townBranches as $b): ?>
                <option value="<?= $b['id'] ?>" <?= $b['id'] == $defaultBranchId ? 'selected' : '' ?>><?= clean($b['name']) ?></option>
              <?php endforeach; ?>
            </optgroup>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-field">
        <label>Supplier</label>
        <select name="supplier_id" required>
          <option value="">— Select supplier —</option>
          <?php foreach ($suppliers as $s): ?>
            <option value="<?= $s['id'] ?>"><?= clean($s['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="form-row">
      <div class="form-field">
        <label>Status</label>
        <select name="status">
          <option value="draft">Draft (not sent yet)</option>
          <option value="ordered">Ordered (already sent to supplier)</option>
        </select>
      </div>
      <div class="form-field">
        <label>Notes (optional)</label>
        <input type="text" name="notes" placeholder="e.g. Delivery expected next Friday">
      </div>
    </div>

    <table class="data-table" id="lineItemsTable">
      <thead><tr><th>Product</th><th style="width:110px">Quantity</th><th style="width:140px">Unit cost</th><th style="width:140px">Line total</th><th></th></tr></thead>
      <tbody>
        <tr class="line-item-row">
          <td>
            <select name="product_id[]" class="product-select" required>
              <option value="">— Select product —</option>
              <?php foreach ($products as $p): ?>
                <option value="<?= $p['id'] ?>" data-cost="<?= $p['cost_price'] ?>"><?= clean($p['name']) ?> (<?= clean($p['sku']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </td>
          <td><input type="number" name="quantity[]" class="qty-input" min="1" value="1"></td>
          <td><input type="number" name="unit_cost[]" class="cost-input" min="0" step="0.01" value="0"></td>
          <td class="mono line-total">0 XAF</td>
          <td><button type="button" class="link link-danger remove-row">Remove</button></td>
        </tr>
      </tbody>
    </table>

    <button type="button" class="btn btn-secondary" id="addRowBtn"><?= icon('plus', 14) ?> Add another product</button>

    <div class="order-total">Estimated total: <strong class="mono" id="poTotal">0 XAF</strong></div>

    <div class="form-actions">
      <a href="index.php" class="btn btn-secondary">Cancel</a>
      <button type="submit" class="btn btn-primary">Create purchase order</button>
    </div>
  </form>
</section>

<script>
(function () {
  const table = document.getElementById('lineItemsTable').querySelector('tbody');
  const template = table.querySelector('.line-item-row');

  function onProductChange(row) {
    const select = row.querySelector('.product-select');
    const cost = select.selectedOptions[0]?.dataset.cost || 0;
    row.querySelector('.cost-input').value = cost;
  }

  function recalcRow(row) {
    const qty = parseFloat(row.querySelector('.qty-input').value) || 0;
    const cost = parseFloat(row.querySelector('.cost-input').value) || 0;
    const lineTotal = qty * cost;
    row.querySelector('.line-total').textContent = lineTotal.toLocaleString() + ' XAF';
    return lineTotal;
  }

  function recalcAll() {
    let total = 0;
    table.querySelectorAll('.line-item-row').forEach(row => total += recalcRow(row));
    document.getElementById('poTotal').textContent = total.toLocaleString() + ' XAF';
  }

  table.addEventListener('change', function (e) {
    if (e.target.classList.contains('product-select')) onProductChange(e.target.closest('.line-item-row'));
    recalcAll();
  });
  table.addEventListener('input', recalcAll);

  document.getElementById('addRowBtn').addEventListener('click', function () {
    const clone = template.cloneNode(true);
    clone.querySelector('.qty-input').value = 1;
    clone.querySelector('.cost-input').value = 0;
    table.appendChild(clone);
    recalcAll();
  });

  table.addEventListener('click', function (e) {
    if (e.target.classList.contains('remove-row') && table.querySelectorAll('.line-item-row').length > 1) {
      e.target.closest('.line-item-row').remove();
      recalcAll();
    }
  });
})();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
