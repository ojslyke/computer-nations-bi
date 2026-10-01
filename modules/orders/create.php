<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/rbac.php';
requirePermission('orders.create');

$pageTitle = 'New order';
$errors = [];
$branchId = requireActiveBranch($pdo);

if ($branchId === null) {
    setFlash('error', 'No active branch found. Ask an admin to add one first.');
    redirect('modules/orders/index.php');
}

$products = $pdo->prepare(
    "SELECT p.id, p.name, p.sku, p.selling_price, COALESCE(bs.quantity,0) AS quantity
     FROM products p LEFT JOIN branch_stock bs ON bs.product_id = p.id AND bs.branch_id = ?
     WHERE p.status='active' AND COALESCE(bs.quantity,0) > 0 ORDER BY p.name"
);
$products->execute([$branchId]);
$products = $products->fetchAll();
$customers = $pdo->query("SELECT id, name FROM customers ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();

    $customerId  = $_POST['customer_id'] ?: null;
    $productIds  = $_POST['product_id'] ?? [];
    $quantities  = $_POST['quantity'] ?? [];
    $unitPrices  = $_POST['unit_price'] ?? [];

    $lineItems = [];
    foreach ($productIds as $i => $pid) {
        $pid = (int)$pid;
        $qty = (int)($quantities[$i] ?? 0);
        // A custom price paid can be entered per line (discounts, negotiated
        // deals); falls back to the catalog price if left blank or invalid.
        $customPrice = isset($unitPrices[$i]) && is_numeric($unitPrices[$i]) && (float)$unitPrices[$i] > 0
            ? round((float)$unitPrices[$i], 2) : null;
        if ($pid && $qty > 0) {
            $lineItems[] = ['product_id' => $pid, 'quantity' => $qty, 'custom_price' => $customPrice];
        }
    }

    if (!$lineItems) {
        $errors[] = 'Add at least one product with a quantity.';
    }

    // Validate stock availability at this branch for every line before touching the database
    $productLookup = [];
    if (!$errors) {
        $ids = array_column($lineItems, 'product_id');
        $in  = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare(
            "SELECT p.id, p.name, p.selling_price, COALESCE(bs.quantity,0) AS quantity
             FROM products p LEFT JOIN branch_stock bs ON bs.product_id = p.id AND bs.branch_id = ?
             WHERE p.id IN ($in)"
        );
        $stmt->execute(array_merge([$branchId], $ids));
        foreach ($stmt->fetchAll() as $row) {
            $productLookup[$row['id']] = $row;
        }
        foreach ($lineItems as $item) {
            $p = $productLookup[$item['product_id']] ?? null;
            if (!$p) {
                $errors[] = 'One of the selected products no longer exists.';
            } elseif ($item['quantity'] > $p['quantity']) {
                $errors[] = "Only {$p['quantity']} units of {$p['name']} are available at this branch.";
            }
        }
    }

    if (!$errors) {
        $pdo->beginTransaction();
        try {
            $orderNumber = generateOrderNumber();
            $total = 0;
            foreach ($lineItems as $item) {
                $price = $item['custom_price'] ?? $productLookup[$item['product_id']]['selling_price'];
                $total += $price * $item['quantity'];
            }

            $paymentMethod = in_array($_POST['payment_method'] ?? '', ['cash','mobile_money','bank_transfer'], true)
                ? $_POST['payment_method'] : 'cash';
            $isInstallment = !empty($_POST['is_installment']);
            $depositAmount = $isInstallment
                ? max(0, min($total, round((float)($_POST['deposit_amount'] ?? 0), 2)))
                : $total;
            $paymentStatus = $depositAmount >= $total ? 'paid' : ($depositAmount > 0 ? 'partial' : 'unpaid');

            $pdo->prepare(
                "INSERT INTO orders (order_number, branch_id, customer_id, user_id, status, total_amount, payment_method, payment_status, amount_paid) VALUES (?,?,?,?,?,?,?,?,?)"
            )->execute([$orderNumber, $branchId, $customerId, $_SESSION['user_id'], 'completed', $total, $paymentMethod, $paymentStatus, $depositAmount]);
            $orderId = $pdo->lastInsertId();

            if ($depositAmount > 0) {
                $pdo->prepare(
                    "INSERT INTO installment_payments (order_id, amount, payment_method, notes, received_by) VALUES (?,?,?,?,?)"
                )->execute([$orderId, $depositAmount, $paymentMethod, $isInstallment ? 'Initial deposit at sale' : 'Paid in full at sale', $_SESSION['user_id']]);
                if ($paymentMethod === 'cash') {
                    refreshCashLedgerCascade($pdo, $branchId, date('Y-m-d'));
                }
            }

            foreach ($lineItems as $item) {
                $p = $productLookup[$item['product_id']];
                $price = $item['custom_price'] ?? $p['selling_price'];
                $subtotal = $price * $item['quantity'];

                $pdo->prepare(
                    "INSERT INTO order_items (order_id, product_id, quantity, unit_price, subtotal) VALUES (?,?,?,?,?)"
                )->execute([$orderId, $item['product_id'], $item['quantity'], $price, $subtotal]);

                // Fulfilling the order removes stock at this branch — logged as a movement, same as any other stock change
                $pdo->prepare(
                    "INSERT INTO branch_stock (branch_id, product_id, quantity) VALUES (?, ?, 0)
                     ON DUPLICATE KEY UPDATE quantity = quantity - ?"
                )->execute([$branchId, $item['product_id'], $item['quantity']]);
                $pdo->prepare(
                    "INSERT INTO stock_movements (branch_id, product_id, type, category, quantity, reason, reference, user_id) VALUES (?, ?, 'out', 'order', ?, 'Order fulfilment', ?, ?)"
                )->execute([$branchId, $item['product_id'], $item['quantity'], $orderNumber, $_SESSION['user_id']]);
            }

            $pdo->commit();
            logActivity('orders.create', "Created order $orderNumber (" . formatMoney($total) . ")");
            setFlash('success', "Order $orderNumber created.");
            redirect('modules/orders/view.php?id=' . $orderId);
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Could not create the order. Try again.';
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<section class="panel panel-form panel-wide">
  <h2>New order <span class="mono muted small">· <?= clean(getBranchName($branchId, $pdo)) ?></span></h2>

  <?php foreach ($errors as $err): ?>
    <div class="alert alert-error"><?= clean($err) ?></div>
  <?php endforeach; ?>

  <form method="post" id="orderForm">
    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

    <div class="form-field">
      <label>Customer</label>
      <select name="customer_id">
        <option value="">Walk-in customer</option>
        <?php foreach ($customers as $c): ?>
          <option value="<?= $c['id'] ?>"><?= clean($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-row">
      <div class="form-field">
        <label>Payment method</label>
        <select name="payment_method">
          <option value="cash">Cash</option>
          <option value="mobile_money">Mobile Money</option>
          <option value="bank_transfer">Bank transfer</option>
        </select>
      </div>
      <div class="form-field">
        <label class="checkbox-row" style="margin-top:22px;">
          <input type="checkbox" name="is_installment" value="1" id="installmentToggle"> Customer is paying in installments
        </label>
      </div>
    </div>
    <div class="form-field" id="depositField" style="display:none;">
      <label>Deposit paid today</label>
      <input type="number" name="deposit_amount" min="0" step="0.01" placeholder="0 for no deposit yet">
      <span class="form-hint">The remaining balance can be recorded later from Installments.</span>
    </div>

    <?php if ($products): ?>
    <table class="data-table" id="lineItemsTable">
      <thead><tr><th>Product</th><th style="width:110px">Quantity</th><th style="width:130px">Price paid</th><th style="width:140px">Line total</th><th></th></tr></thead>
      <tbody>
        <tr class="line-item-row">
          <td>
            <select name="product_id[]" class="product-select" required>
              <option value="">— Select product —</option>
              <?php foreach ($products as $p): ?>
                <option value="<?= $p['id'] ?>" data-price="<?= $p['selling_price'] ?>" data-stock="<?= $p['quantity'] ?>">
                  <?= clean($p['name']) ?> (<?= clean($p['sku']) ?>) — <?= (int)$p['quantity'] ?> available
                </option>
              <?php endforeach; ?>
            </select>
          </td>
          <td><input type="number" name="quantity[]" class="qty-input" min="1" value="1"></td>
          <td><input type="number" name="unit_price[]" class="price-input mono" min="0" step="0.01" placeholder="catalog price"></td>
          <td class="mono line-total">0 XAF</td>
          <td><button type="button" class="link link-danger remove-row">Remove</button></td>
        </tr>
      </tbody>
    </table>
    <p class="muted small" style="margin-top:6px;">Price paid defaults to the catalog price — edit it per line for a negotiated price or discount.</p>

    <button type="button" class="btn btn-secondary" id="addRowBtn">+ Add another product</button>

    <div class="order-total">Order total: <strong class="mono" id="orderTotal">0 XAF</strong></div>

    <div class="form-actions">
      <a href="index.php" class="btn btn-secondary">Cancel</a>
      <button type="submit" class="btn btn-primary">Create order</button>
    </div>
    <?php else: ?>
      <p class="empty-state">No stock available to sell at this branch right now.</p>
    <?php endif; ?>
  </form>
</section>

<script>
document.getElementById('installmentToggle').addEventListener('change', function (e) {
  document.getElementById('depositField').style.display = e.target.checked ? '' : 'none';
});

// Dynamic order line items: add/remove rows and keep the running total in sync.
(function () {
  const table = document.getElementById('lineItemsTable');
  if (!table) return;
  const tbody = table.querySelector('tbody');
  const template = tbody.querySelector('.line-item-row');

  function recalcRow(row) {
    const select = row.querySelector('.product-select');
    const qty = parseInt(row.querySelector('.qty-input').value) || 0;
    const priceInput = row.querySelector('.price-input');
    const catalogPrice = parseFloat(select.selectedOptions[0]?.dataset.price || 0);
    const price = priceInput.value !== '' ? parseFloat(priceInput.value) || 0 : catalogPrice;
    const lineTotal = price * qty;
    row.querySelector('.line-total').textContent = lineTotal.toLocaleString() + ' XAF';
    return lineTotal;
  }

  function recalcOrder() {
    let total = 0;
    tbody.querySelectorAll('.line-item-row').forEach(row => total += recalcRow(row));
    document.getElementById('orderTotal').textContent = total.toLocaleString() + ' XAF';
  }

  tbody.addEventListener('input', recalcOrder);
  tbody.addEventListener('change', function (e) {
    // Selecting a different product resets the price field to that
    // product's catalog price, ready to be overridden again if needed.
    if (e.target.classList.contains('product-select')) {
      const priceInput = e.target.closest('.line-item-row').querySelector('.price-input');
      priceInput.value = '';
      priceInput.placeholder = e.target.selectedOptions[0]?.dataset.price
        ? parseFloat(e.target.selectedOptions[0].dataset.price).toLocaleString() + ' XAF (catalog)'
        : 'catalog price';
    }
    recalcOrder();
  });

  document.getElementById('addRowBtn').addEventListener('click', function () {
    const clone = template.cloneNode(true);
    clone.querySelector('.qty-input').value = 1;
    clone.querySelector('.price-input').value = '';
    clone.querySelector('.price-input').placeholder = 'catalog price';
    tbody.appendChild(clone);
    recalcOrder();
  });

  tbody.addEventListener('click', function (e) {
    if (e.target.classList.contains('remove-row') && tbody.querySelectorAll('.line-item-row').length > 1) {
      e.target.closest('.line-item-row').remove();
      recalcOrder();
    }
  });
})();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
