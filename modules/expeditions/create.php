<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/rbac.php';
requirePermission('expeditions.create');

$pageTitle = 'Send expedition';
$errors = [];
$defaultFromBranch = requireActiveBranch($pdo);

$branchesByTown = listBranchesByTown($pdo);
$fromBranchId = (int)($_POST['from_branch_id'] ?? $defaultFromBranch);

$fromTown = $pdo->prepare("SELECT town_id FROM branches WHERE id = ?");
$fromTown->execute([$fromBranchId]);
$fromTownId = $fromTown->fetch()['town_id'] ?? null;

// Only branches in a DIFFERENT town — same-town moves use Transfer instead
$otherTownBranches = [];
foreach ($branchesByTown as $townName => $townBranches) {
    foreach ($townBranches as $b) {
        if ($b['town_id'] != $fromTownId) {
            $otherTownBranches[$townName][] = $b;
        }
    }
}

$products = $pdo->prepare(
    "SELECT p.id, p.name, p.sku, COALESCE(bs.quantity,0) AS quantity
     FROM products p LEFT JOIN branch_stock bs ON bs.product_id = p.id AND bs.branch_id = ?
     WHERE p.status = 'active' AND COALESCE(bs.quantity,0) > 0 ORDER BY p.name"
);
$products->execute([$fromBranchId]);
$products = $products->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST['switch_only'])) {
    csrfCheck();

    $toBranchId = (int)$_POST['to_branch_id'];
    $notes = trim($_POST['notes'] ?? '');
    $productIds = $_POST['product_id'] ?? [];
    $quantities = $_POST['quantity'] ?? [];

    $lineItems = [];
    foreach ($productIds as $i => $pid) {
        $pid = (int)$pid;
        $qty = (int)($quantities[$i] ?? 0);
        if ($pid && $qty > 0) $lineItems[] = ['product_id' => $pid, 'quantity' => $qty];
    }

    $toTown = $pdo->prepare("SELECT town_id FROM branches WHERE id = ?");
    $toTown->execute([$toBranchId]);
    $toTownId = $toTown->fetch()['town_id'] ?? null;

    if (!$toBranchId) $errors[] = 'Choose a destination branch.';
    if ($toBranchId && $toTownId === $fromTownId) $errors[] = 'An Expedition must go to a different town. For the same town, use Transfer instead.';
    if (!$lineItems) $errors[] = 'Add at least one product with a quantity.';

    $stockLookup = [];
    if (!$errors) {
        $ids = array_column($lineItems, 'product_id');
        $in = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare(
            "SELECT p.id, p.name, COALESCE(bs.quantity,0) AS quantity FROM products p
             LEFT JOIN branch_stock bs ON bs.product_id = p.id AND bs.branch_id = ?
             WHERE p.id IN ($in)"
        );
        $stmt->execute(array_merge([$fromBranchId], $ids));
        foreach ($stmt->fetchAll() as $row) $stockLookup[$row['id']] = $row;

        foreach ($lineItems as $item) {
            $p = $stockLookup[$item['product_id']] ?? null;
            if (!$p) {
                $errors[] = 'One of the selected products no longer exists.';
            } elseif ($item['quantity'] > $p['quantity']) {
                $errors[] = "Only {$p['quantity']} units of {$p['name']} available at the source branch.";
            }
        }
    }

    if (!$errors) {
        $pdo->beginTransaction();
        try {
            $expeditionNumber = 'EXP-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
            $destName = getBranchName($toBranchId, $pdo);
            $sourceName = getBranchName($fromBranchId, $pdo);

            $pdo->prepare(
                "INSERT INTO stock_transfers (transfer_number, type, from_branch_id, to_branch_id, user_id, notes, status, dispatched_at)
                 VALUES (?,'expedition',?,?,?,?,'pending', NOW())"
            )->execute([$expeditionNumber, $fromBranchId, $toBranchId, $_SESSION['user_id'], $notes ?: null]);
            $expeditionId = $pdo->lastInsertId();

            $itemStmt = $pdo->prepare("INSERT INTO stock_transfer_items (transfer_id, product_id, quantity) VALUES (?,?,?)");
            foreach ($lineItems as $item) {
                $itemStmt->execute([$expeditionId, $item['product_id'], $item['quantity']]);

                $pdo->prepare(
                    "INSERT INTO branch_stock (branch_id, product_id, quantity) VALUES (?, ?, 0)
                     ON DUPLICATE KEY UPDATE quantity = quantity - ?"
                )->execute([$fromBranchId, $item['product_id'], $item['quantity']]);
                $pdo->prepare(
                    "INSERT INTO stock_movements (branch_id, product_id, type, category, quantity, reason, reference, user_id) VALUES (?,?,'out','transfer',?,?,?,?)"
                )->execute([$fromBranchId, $item['product_id'], $item['quantity'], 'Expedited to ' . $destName, $expeditionNumber, $_SESSION['user_id']]);
            }

            $pdo->commit();
            logActivity('expeditions.create', "Sent expedition $expeditionNumber to $destName");
            setFlash('success', "Expedition $expeditionNumber sent — stock left $sourceName now, and it's pending receipt at $destName.");
            redirect('modules/expeditions/view.php?id=' . $expeditionId);
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Could not send the expedition. Try again.';
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<section class="panel panel-form panel-wide">
  <h2>Send expedition</h2>
  <p class="muted small">Expeditions move stock between two <strong>different towns</strong>, and can't be edited or cancelled once sent. Moving stock within the same town? Use <a href="../transfers/create.php" class="link">Transfer</a> instead.</p>
  <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= clean($err) ?></div><?php endforeach; ?>

  <form method="post" id="expeditionForm" class="js-confirm" data-confirm-title="Send this expedition?" data-confirm-message="Stock leaves the source branch immediately and this can't be undone or edited afterward.">
    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
    <input type="hidden" name="switch_only" id="switchOnly" value="">

    <div class="form-row">
      <div class="form-field">
        <label>From branch</label>
        <select name="from_branch_id" onchange="document.getElementById('switchOnly').value='1'; this.form.submit();">
          <?php foreach ($branchesByTown as $townName => $townBranches): ?>
            <optgroup label="<?= clean($townName) ?>">
              <?php foreach ($townBranches as $b): ?>
                <option value="<?= $b['id'] ?>" <?= $b['id'] == $fromBranchId ? 'selected' : '' ?>><?= clean($b['name']) ?></option>
              <?php endforeach; ?>
            </optgroup>
          <?php endforeach; ?>
        </select>
        <span class="form-hint">Changing this reloads the product list with that branch's stock.</span>
      </div>
      <div class="form-field">
        <label>To branch <span class="muted small">(different town only)</span></label>
        <select name="to_branch_id" required <?= !$otherTownBranches ? 'disabled' : '' ?>>
          <option value="">— Select destination —</option>
          <?php foreach ($otherTownBranches as $townName => $townBranches): ?>
            <optgroup label="<?= clean($townName) ?>">
              <?php foreach ($townBranches as $b): ?>
                <option value="<?= $b['id'] ?>"><?= clean($b['name']) ?></option>
              <?php endforeach; ?>
            </optgroup>
          <?php endforeach; ?>
        </select>
        <?php if (!$otherTownBranches): ?>
          <span class="form-hint">There's only one town set up so far — add another town and branch to send an expedition.</span>
        <?php endif; ?>
      </div>
    </div>

    <div class="form-field">
      <label>Notes (optional)</label>
      <input type="text" name="notes" placeholder="e.g. Sent via inter-city courier, tracking #123">
    </div>

    <?php if ($products && $otherTownBranches): ?>
    <table class="data-table" id="lineItemsTable">
      <thead><tr><th>Product</th><th style="width:120px">Quantity</th><th></th></tr></thead>
      <tbody>
        <tr class="line-item-row">
          <td>
            <select name="product_id[]" class="product-select" required>
              <option value="">— Select product —</option>
              <?php foreach ($products as $p): ?>
                <option value="<?= $p['id'] ?>" data-stock="<?= $p['quantity'] ?>"><?= clean($p['name']) ?> (<?= clean($p['sku']) ?>) — <?= (int)$p['quantity'] ?> available</option>
              <?php endforeach; ?>
            </select>
          </td>
          <td><input type="number" name="quantity[]" class="qty-input" min="1" value="1"></td>
          <td><button type="button" class="link link-danger remove-row">Remove</button></td>
        </tr>
      </tbody>
    </table>
    <button type="button" class="btn btn-secondary" id="addRowBtn"><?= icon('plus', 14) ?> Add another product</button>

    <div class="form-actions">
      <a href="index.php" class="btn btn-secondary">Cancel</a>
      <button type="submit" class="btn btn-primary"><?= icon('transfer', 15) ?> Send expedition</button>
    </div>
    <?php elseif (!$products): ?>
      <p class="empty-state">The source branch has no stock to send right now.</p>
    <?php endif; ?>
  </form>
</section>

<script>
(function () {
  const table = document.getElementById('lineItemsTable');
  if (!table) return;
  const tbody = table.querySelector('tbody');
  const template = tbody.querySelector('.line-item-row');

  document.getElementById('addRowBtn').addEventListener('click', function () {
    const clone = template.cloneNode(true);
    clone.querySelector('.qty-input').value = 1;
    tbody.appendChild(clone);
  });

  tbody.addEventListener('click', function (e) {
    if (e.target.classList.contains('remove-row') && tbody.querySelectorAll('.line-item-row').length > 1) {
      e.target.closest('.line-item-row').remove();
    }
  });
})();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
