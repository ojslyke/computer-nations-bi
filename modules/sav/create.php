<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/rbac.php';
requirePermission('sav.create');

$pageTitle = 'Report SAV item';
$errors = [];
$branchId = requireActiveBranch($pdo);
$preselectedProductId = (int)($_GET['product'] ?? 0);

if ($branchId === null) {
    setFlash('error', 'No active branch found. Ask an admin to add one first.');
    redirect('modules/sav/index.php');
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
    $quantity  = (int)$_POST['quantity'];
    $issue     = trim($_POST['issue_description'] ?? '');

    if (!$productId || $quantity <= 0) {
        $errors[] = 'Choose a product and a quantity greater than zero.';
    }
    if ($issue === '') {
        $errors[] = 'Describe the issue.';
    }

    $product = null;
    if (!$errors) {
        $stmt = $pdo->prepare("SELECT name, COALESCE((SELECT quantity FROM branch_stock WHERE branch_id = ? AND product_id = p.id), 0) AS quantity FROM products p WHERE p.id = ?");
        $stmt->execute([$branchId, $productId]);
        $product = $stmt->fetch();
        if (!$product) {
            $errors[] = 'Product not found.';
        } elseif ($quantity > $product['quantity']) {
            $errors[] = "Cannot report $quantity units — only {$product['quantity']} in stock at this branch.";
        }
    }

    if (!$errors) {
        $pdo->beginTransaction();
        try {
            $savNumber = generateSavNumber();

            $pdo->prepare(
                "INSERT INTO sav_items (sav_number, branch_id, product_id, quantity, issue_description, status, reported_by) VALUES (?,?,?,?,?,'reported',?)"
            )->execute([$savNumber, $branchId, $productId, $quantity, $issue, $_SESSION['user_id']]);
            $savId = $pdo->lastInsertId();

            $pdo->prepare(
                "INSERT INTO branch_stock (branch_id, product_id, quantity) VALUES (?, ?, 0)
                 ON DUPLICATE KEY UPDATE quantity = quantity - ?"
            )->execute([$branchId, $productId, $quantity]);
            $pdo->prepare(
                "INSERT INTO stock_movements (branch_id, product_id, type, category, quantity, reason, reference, user_id) VALUES (?,?,'out','sav',?,?,?,?)"
            )->execute([$branchId, $productId, $quantity, "SAV reported: $issue", $savNumber, $_SESSION['user_id']]);

            $pdo->prepare(
                "INSERT INTO sav_activities (sav_item_id, action, notes, user_id) VALUES (?, 'reported', ?, ?)"
            )->execute([$savId, $issue, $_SESSION['user_id']]);

            $pdo->commit();
            logActivity('sav.create', "Reported SAV item {$savNumber}: {$product['name']} x$quantity");
            setFlash('success', "$savNumber reported — {$product['name']} moved out of sellable stock pending repair.");
            redirect('modules/sav/view.php?id=' . $savId);
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Could not report the SAV item. Try again.';
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<section class="panel panel-form">
  <h2>Report SAV item <span class="mono muted small">· <?= clean(getBranchName($branchId, $pdo)) ?></span></h2>
  <p class="muted small">This takes the unit(s) out of sellable stock right away. If they come back repaired, they'll be added back automatically.</p>
  <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= clean($err) ?></div><?php endforeach; ?>

  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
    <div class="form-field">
      <label>Product</label>
      <select name="product_id" required>
        <option value="">— Select a product —</option>
        <?php foreach ($products as $p): ?>
          <option value="<?= $p['id'] ?>" <?= $p['id'] == $preselectedProductId ? 'selected' : '' ?>><?= clean($p['name']) ?> (<?= clean($p['sku']) ?>) — <?= (int)$p['quantity'] ?> in stock</option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row">
      <div class="form-field">
        <label>Quantity</label>
        <input type="number" min="1" name="quantity" required>
      </div>
      <div class="form-field">
        <label>Issue</label>
        <input type="text" name="issue_description" placeholder="e.g. Screen cracked, won't power on" required>
      </div>
    </div>
    <div class="form-actions">
      <a href="index.php" class="btn btn-secondary">Cancel</a>
      <button type="submit" class="btn btn-primary">Report SAV item</button>
    </div>
  </form>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
