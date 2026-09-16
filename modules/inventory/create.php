<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/rbac.php';
requirePermission('inventory.create');

$pageTitle = 'Add product';
$errors = [];
$defaultBranchId = requireActiveBranch($pdo);

$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();
$suppliers  = $pdo->query("SELECT id, name FROM suppliers ORDER BY name")->fetchAll();
$branchesByTown = listBranchesByTown($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();

    $sku           = trim($_POST['sku']);
    $name          = trim($_POST['name']);
    $categoryId    = $_POST['category_id'] ?: null;
    $supplierId    = $_POST['supplier_id'] ?: null;
    $costPrice     = (float)$_POST['cost_price'];
    $sellingPrice  = (float)$_POST['selling_price'];
    $openingBranch = (int)$_POST['opening_branch_id'];
    $quantity      = (int)$_POST['quantity'];
    $reorderLevel  = (int)$_POST['reorder_level'];

    if ($sku === '' || $name === '') {
        $errors[] = 'SKU and product name are required.';
    }

    // Optional image upload
    $imagePath = null;
    if (!empty($_FILES['image']['name'])) {
        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($_FILES['image']['type'], $allowed, true)) {
            $errors[] = 'Image must be JPG, PNG or WEBP.';
        } elseif ($_FILES['image']['size'] > 2 * 1024 * 1024) {
            $errors[] = 'Image must be under 2MB.';
        } else {
            if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
            $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $filename = 'prod_' . uniqid() . '.' . $ext;
            move_uploaded_file($_FILES['image']['tmp_name'], UPLOAD_DIR . $filename);
            $imagePath = $filename;
        }
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare(
                "INSERT INTO products (sku, name, category_id, supplier_id, cost_price, selling_price, default_reorder_level, image, created_by)
                 VALUES (?,?,?,?,?,?,?,?,?)"
            );
            $stmt->execute([$sku, $name, $categoryId, $supplierId, $costPrice, $sellingPrice, $reorderLevel, $imagePath, $_SESSION['user_id']]);
            $productId = $pdo->lastInsertId();

            if ($quantity > 0 && $openingBranch) {
                $pdo->prepare(
                    "INSERT INTO branch_stock (branch_id, product_id, quantity, reorder_level) VALUES (?,?,?,?)"
                )->execute([$openingBranch, $productId, $quantity, $reorderLevel]);

                $pdo->prepare(
                    "INSERT INTO stock_movements (branch_id, product_id, type, category, quantity, reason, user_id) VALUES (?,?,'in','opening',?,'Opening stock',?)"
                )->execute([$openingBranch, $productId, $quantity, $_SESSION['user_id']]);
            }

            $pdo->commit();
            logActivity('inventory.create', "Added product: $name ($sku)");
            setFlash('success', "$name was added to inventory.");
            redirect('modules/inventory/index.php');
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = $e->getCode() === '23000' ? 'That SKU already exists.' : 'Could not save product.';
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<section class="panel panel-form">
  <h2>Add product</h2>

  <?php foreach ($errors as $err): ?>
    <div class="alert alert-error"><?= clean($err) ?></div>
  <?php endforeach; ?>

  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

    <div class="form-row">
      <div class="form-field">
        <label>SKU</label>
        <input type="text" name="sku" value="<?= clean($_POST['sku'] ?? '') ?>" required>
      </div>
      <div class="form-field">
        <label>Product name</label>
        <input type="text" name="name" value="<?= clean($_POST['name'] ?? '') ?>" required>
      </div>
    </div>

    <div class="form-row">
      <div class="form-field">
        <label>Category <?php if (hasPermission('categories.manage')): ?><a href="../categories/index.php" class="link small" style="font-weight:400;">(manage categories)</a><?php endif; ?></label>
        <select name="category_id">
          <option value="">— None —</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?= $c['id'] ?>"><?= clean($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-field">
        <label>Supplier</label>
        <select name="supplier_id">
          <option value="">— None —</option>
          <?php foreach ($suppliers as $s): ?>
            <option value="<?= $s['id'] ?>"><?= clean($s['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="form-row">
      <div class="form-field">
        <label>Cost price (XAF)</label>
        <input type="number" step="0.01" min="0" name="cost_price" value="<?= clean($_POST['cost_price'] ?? '0') ?>" required>
      </div>
      <div class="form-field">
        <label>Selling price (XAF)</label>
        <input type="number" step="0.01" min="0" name="selling_price" value="<?= clean($_POST['selling_price'] ?? '0') ?>" required>
      </div>
    </div>

    <div class="form-field">
      <label>Opening stock at branch</label>
      <select name="opening_branch_id">
        <?php foreach ($branchesByTown as $townName => $townBranches): ?>
          <optgroup label="<?= clean($townName) ?>">
            <?php foreach ($townBranches as $b): ?>
              <option value="<?= $b['id'] ?>" <?= $b['id'] == $defaultBranchId ? 'selected' : '' ?>><?= clean($b['name']) ?></option>
            <?php endforeach; ?>
          </optgroup>
        <?php endforeach; ?>
      </select>
      <span class="form-hint">You can add stock to other branches later via Transfers or by receiving a purchase order there.</span>
    </div>

    <div class="form-row">
      <div class="form-field">
        <label>Opening quantity</label>
        <input type="number" min="0" name="quantity" value="<?= clean($_POST['quantity'] ?? '0') ?>" required>
      </div>
      <div class="form-field">
        <label>Reorder level</label>
        <input type="number" min="0" name="reorder_level" value="<?= clean($_POST['reorder_level'] ?? '5') ?>" required>
      </div>
    </div>

    <div class="form-field">
      <label>Product image (optional)</label>
      <input type="file" name="image" accept="image/png, image/jpeg, image/webp">
    </div>

    <div class="form-actions">
      <a href="index.php" class="btn btn-secondary">Cancel</a>
      <button type="submit" class="btn btn-primary">Save product</button>
    </div>
  </form>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
