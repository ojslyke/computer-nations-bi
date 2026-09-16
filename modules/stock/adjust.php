<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/rbac.php';
requirePermission('stock.adjust');

$pageTitle = 'Record stock movement';
$errors = [];
$branchId = requireActiveBranch($pdo);

if ($branchId === null) {
    setFlash('error', 'No active branch found. Ask an admin to add one first.');
    redirect('modules/stock/index.php');
}

$products = $pdo->prepare(
    "SELECT p.id, p.name, p.sku, COALESCE(bs.quantity,0) AS quantity
     FROM products p LEFT JOIN branch_stock bs ON bs.product_id = p.id AND bs.branch_id = ?
     WHERE p.status = 'active' ORDER BY p.name"
);
$products->execute([$branchId]);
$products = $products->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();

    $productId = (int)$_POST['product_id'];
    $type      = in_array($_POST['type'], ['in', 'out', 'adjustment'], true) ? $_POST['type'] : null;
    $quantity  = (int)$_POST['quantity'];
    $reason    = trim($_POST['reason'] ?? '');

    if (!$productId || !$type || $quantity <= 0) {
        $errors[] = 'Choose a product, a movement type, and a quantity greater than zero.';
    }

    $currentQty = 0;
    if (!$errors) {
        $product = $pdo->prepare("SELECT p.name, COALESCE(bs.quantity,0) AS quantity FROM products p LEFT JOIN branch_stock bs ON bs.product_id = p.id AND bs.branch_id = ? WHERE p.id = ?");
        $product->execute([$branchId, $productId]);
        $product = $product->fetch();

        if (!$product) {
            $errors[] = 'Product not found.';
        } elseif ($type === 'out' && $quantity > $product['quantity']) {
            $errors[] = "Cannot remove $quantity units — only {$product['quantity']} in stock at this branch.";
        }
    }

    if (!$errors) {
        $pdo->beginTransaction();
        try {
            $delta = $type === 'out' ? -$quantity : $quantity;
            $pdo->prepare(
                "INSERT INTO branch_stock (branch_id, product_id, quantity) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)"
            )->execute([$branchId, $productId, $delta]);
            $pdo->prepare(
                "INSERT INTO stock_movements (branch_id, product_id, type, category, quantity, reason, user_id) VALUES (?,?,?,'manual',?,?,?)"
            )->execute([$branchId, $productId, $type, $quantity, $reason ?: null, $_SESSION['user_id']]);
            $pdo->commit();

            logActivity('stock.adjust', "{$product['name']}: $type $quantity ($reason)");
            setFlash('success', 'Stock movement recorded.');
            redirect('modules/stock/index.php');
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Could not record the movement. Try again.';
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<section class="panel panel-form">
  <h2>Record stock movement <span class="mono muted small">· <?= clean(getBranchName($branchId, $pdo)) ?></span></h2>

  <?php foreach ($errors as $err): ?>
    <div class="alert alert-error"><?= clean($err) ?></div>
  <?php endforeach; ?>

  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

    <div class="form-field">
      <label>Product</label>
      <select name="product_id" required>
        <option value="">— Select a product —</option>
        <?php foreach ($products as $p): ?>
          <option value="<?= $p['id'] ?>"><?= clean($p['name']) ?> (<?= clean($p['sku']) ?>) — <?= (int)$p['quantity'] ?> in stock here</option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-row">
      <div class="form-field">
        <label>Movement type</label>
        <select name="type" required>
          <option value="in">Stock in (received / found)</option>
          <option value="out">Stock out (used / removed)</option>
          <option value="adjustment">Adjustment (correction)</option>
        </select>
      </div>
      <div class="form-field">
        <label>Quantity</label>
        <input type="number" min="1" name="quantity" required>
      </div>
    </div>

    <div class="form-field">
      <label>Reason / note</label>
      <input type="text" name="reason" placeholder="e.g. Found extra unit during shelf count">
    </div>

    <?php if (hasPermission('sav.create')): ?>
      <p class="muted small">Damaged or faulty stock? Use <a href="../sav/create.php" class="link">SAV</a> instead, so it's tracked through repair rather than just written off.</p>
    <?php endif; ?>

    <div class="form-actions">
      <a href="index.php" class="btn btn-secondary">Cancel</a>
      <button type="submit" class="btn btn-primary">Record movement</button>
    </div>
  </form>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
