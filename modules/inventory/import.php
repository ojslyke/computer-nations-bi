<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/rbac.php';
requirePermission('inventory.import');

$pageTitle = 'Import stock from Excel';
$errors = [];
$preview = null;
$branchesByTown = listBranchesByTown($pdo);
$defaultBranch = currentBranchId() ?? requireActiveBranch($pdo);

$step = $_POST['step'] ?? 'upload';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 'upload') {
    csrfCheck();
    $branchId = (int)($_POST['branch_id'] ?? 0);

    if (!$branchId) {
        $errors[] = 'Choose which branch this stock count applies to.';
    } elseif (empty($_FILES['file']['name'])) {
        $errors[] = 'Choose a file to upload.';
    } else {
        $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx', 'csv'], true)) {
            $errors[] = 'File must be .xlsx or .csv.';
        } elseif ($_FILES['file']['size'] > 5 * 1024 * 1024) {
            $errors[] = 'File must be under 5MB.';
        } else {
            $rows = parseSpreadsheetFile($_FILES['file']['tmp_name'], $_FILES['file']['name']);
            if ($rows === false) {
                $errors[] = 'Could not read that file — make sure it\'s a genuine .xlsx or .csv export.';
            } else {
                // First row is always treated as a header and skipped.
                array_shift($rows);
                if (!$rows) {
                    $errors[] = 'No data rows found below the header.';
                }
            }

            if (!$errors) {
                // Match every SKU against this branch's products in one query
                // rather than one lookup per row.
                $skus = [];
                foreach ($rows as $row) {
                    $sku = trim($row[0] ?? '');
                    if ($sku !== '') $skus[$sku] = true;
                }
                $skus = array_keys($skus);
                $productBySkU = [];
                if ($skus) {
                    $in = implode(',', array_fill(0, count($skus), '?'));
                    $stmt = $pdo->prepare(
                        "SELECT p.id, p.sku, p.name, COALESCE(bs.quantity,0) AS current_quantity
                         FROM products p LEFT JOIN branch_stock bs ON bs.product_id = p.id AND bs.branch_id = ?
                         WHERE p.sku IN ($in)"
                    );
                    $stmt->execute(array_merge([$branchId], $skus));
                    foreach ($stmt->fetchAll() as $r) {
                        $productBySkU[strtoupper($r['sku'])] = $r;
                    }
                }

                $previewRows = [];
                foreach ($rows as $row) {
                    $sku = trim($row[0] ?? '');
                    $qtyRaw = trim($row[1] ?? '');
                    if ($sku === '' && $qtyRaw === '') continue; // skip fully blank rows

                    $match = $productBySkU[strtoupper($sku)] ?? null;
                    $qtyValid = $qtyRaw !== '' && is_numeric($qtyRaw) && (float)$qtyRaw >= 0;

                    $previewRows[] = [
                        'sku' => $sku,
                        'product_id' => $match['id'] ?? null,
                        'product_name' => $match['name'] ?? null,
                        'current_quantity' => $match['current_quantity'] ?? null,
                        'new_quantity' => $qtyValid ? (int)$qtyRaw : null,
                        'ok' => $match !== null && $qtyValid,
                    ];
                }

                if (!$previewRows) {
                    $errors[] = 'No usable rows found — expected Column A: SKU, Column B: Quantity.';
                } else {
                    $_SESSION['stock_import_preview'] = ['branch_id' => $branchId, 'rows' => $previewRows];
                    $preview = $_SESSION['stock_import_preview'];
                }
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 'confirm') {
    csrfCheck();
    $pending = $_SESSION['stock_import_preview'] ?? null;
    if (!$pending) {
        setFlash('error', 'That import preview expired — upload the file again.');
        redirect('modules/inventory/import.php');
    }

    $branchId = $pending['branch_id'];
    $applied = 0;
    $pdo->beginTransaction();
    try {
        foreach ($pending['rows'] as $row) {
            if (!$row['ok']) continue; // unmatched SKUs or bad quantities are silently skipped, shown in the preview as such
            $diff = $row['new_quantity'] - $row['current_quantity'];
            if ($diff === 0) continue;

            $pdo->prepare(
                "INSERT INTO branch_stock (branch_id, product_id, quantity) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)"
            )->execute([$branchId, $row['product_id'], $row['new_quantity']]);

            $pdo->prepare(
                "INSERT INTO stock_movements (branch_id, product_id, type, category, quantity, reason, user_id) VALUES (?,?,?,?,?,?,?)"
            )->execute([
                $branchId, $row['product_id'], $diff > 0 ? 'in' : 'out', 'manual',
                abs($diff), 'Bulk stock import (Excel) — set to ' . $row['new_quantity'], $_SESSION['user_id'],
            ]);
            $applied++;
        }
        $pdo->commit();
        unset($_SESSION['stock_import_preview']);
        logActivity('inventory.import', "Bulk stock import applied to " . getBranchName($branchId, $pdo) . " ($applied product(s) changed)");
        setFlash('success', "Stock import applied — $applied product(s) updated at " . getBranchName($branchId, $pdo) . '.');
        redirect('modules/inventory/index.php');
    } catch (Exception $e) {
        $pdo->rollBack();
        setFlash('error', 'Could not apply the import. Try again.');
        redirect('modules/inventory/import.php');
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<?php if ($preview): ?>
<section class="panel panel-wide">
  <h2>Review before applying</h2>
  <p class="muted small">This <strong>sets</strong> each matched product's stock at <?= clean(getBranchName($preview['branch_id'], $pdo)) ?> to the quantity in the file — it does not add to the existing amount. Rows in red are skipped (unmatched SKU or invalid quantity).</p>

  <div class="data-table-wrap">
  <table class="data-table">
    <thead><tr><th>SKU</th><th>Product</th><th>Current</th><th>New</th><th>Change</th></tr></thead>
    <tbody>
      <?php foreach ($preview['rows'] as $row): ?>
      <tr<?= !$row['ok'] ? ' style="background:var(--danger-soft);"' : '' ?>>
        <td class="mono"><?= clean($row['sku']) ?></td>
        <td><?= $row['product_name'] ? clean($row['product_name']) : '<span class="muted">SKU not found</span>' ?></td>
        <td class="mono"><?= $row['current_quantity'] !== null ? (int)$row['current_quantity'] : '—' ?></td>
        <td class="mono"><?= $row['new_quantity'] !== null ? (int)$row['new_quantity'] : '<span class="muted">invalid</span>' ?></td>
        <td class="mono">
          <?php if ($row['ok']): $diff = $row['new_quantity'] - $row['current_quantity']; ?>
            <?= $diff > 0 ? '+' . $diff : $diff ?>
          <?php else: ?>—<?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>

  <form method="post" class="form-actions">
    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
    <input type="hidden" name="step" value="confirm">
    <a href="import.php" class="btn btn-secondary">Cancel</a>
    <button type="submit" class="btn btn-primary">Apply import</button>
  </form>
</section>
<?php else: ?>

<section class="panel panel-form">
  <h2>Import stock from Excel</h2>
  <p class="muted small">Accepts .xlsx or .csv. Expected format: <strong>Column A = SKU</strong>, <strong>Column B = Quantity</strong>, with a header row on top. This sets each product's stock to the file's quantity — it doesn't add to the current amount.</p>
  <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= clean($err) ?></div><?php endforeach; ?>

  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
    <input type="hidden" name="step" value="upload">
    <div class="form-field">
      <label>Branch</label>
      <select name="branch_id" required>
        <?php foreach ($branchesByTown as $townName => $townBranches): ?>
          <optgroup label="<?= clean($townName) ?>">
            <?php foreach ($townBranches as $b): ?>
              <option value="<?= $b['id'] ?>" <?= $b['id'] == $defaultBranch ? 'selected' : '' ?>><?= clean($b['name']) ?></option>
            <?php endforeach; ?>
          </optgroup>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-field">
      <label>File</label>
      <input type="file" name="file" accept=".xlsx,.csv" required>
    </div>
    <div class="form-actions">
      <a href="index.php" class="btn btn-secondary">Cancel</a>
      <button type="submit" class="btn btn-primary">Preview import</button>
    </div>
  </form>
</section>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
