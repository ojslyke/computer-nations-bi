<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/rbac.php';
requirePermission('audit.create');

$pageTitle = 'Conduct stock audit';
$errors = [];
$branchesByTown = listBranchesByTown($pdo);
$branchId = (int)($_POST['branch_id'] ?? currentBranchId() ?? requireActiveBranch($pdo));

$products = $pdo->prepare(
    "SELECT p.id, p.name, p.sku, COALESCE(bs.quantity,0) AS system_quantity
     FROM products p LEFT JOIN branch_stock bs ON bs.product_id = p.id AND bs.branch_id = ?
     WHERE p.status = 'active' ORDER BY p.name"
);
$products->execute([$branchId]);
$products = $products->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST['switch_only'])) {
    csrfCheck();

    $notes = trim($_POST['notes'] ?? '');
    $counted = $_POST['counted'] ?? [];

    $items = [];
    foreach ($products as $p) {
        $raw = trim($counted[$p['id']] ?? '');
        if ($raw === '' || !is_numeric($raw) || (float)$raw < 0) continue;
        $countedQty = (int)$raw;
        $items[] = [
            'product_id' => $p['id'],
            'system_quantity' => (int)$p['system_quantity'],
            'counted_quantity' => $countedQty,
            'variance' => $countedQty - (int)$p['system_quantity'],
        ];
    }

    if (!$items) {
        $errors[] = 'Enter a counted quantity for at least one product.';
    }

    if (!$errors) {
        $pdo->beginTransaction();
        try {
            $auditNumber = 'AUD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
            $pdo->prepare(
                "INSERT INTO stock_audits (audit_number, branch_id, conducted_by, notes) VALUES (?,?,?,?)"
            )->execute([$auditNumber, $branchId, $_SESSION['user_id'], $notes ?: null]);
            $auditId = $pdo->lastInsertId();

            $itemStmt = $pdo->prepare(
                "INSERT INTO stock_audit_items (audit_id, product_id, system_quantity, counted_quantity, variance) VALUES (?,?,?,?,?)"
            );
            foreach ($items as $item) {
                $itemStmt->execute([$auditId, $item['product_id'], $item['system_quantity'], $item['counted_quantity'], $item['variance']]);
            }

            $pdo->commit();
            $varianceCount = count(array_filter($items, fn($i) => $i['variance'] !== 0));
            logActivity('audit.create', "Conducted stock audit $auditNumber at " . getBranchName($branchId, $pdo) . " ($varianceCount discrepancy/ies found)");
            setFlash('success', "$auditNumber recorded — $varianceCount product(s) had a discrepancy.");
            redirect('modules/audit/view.php?id=' . $auditId);
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Could not save the audit. Try again.';
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<section class="panel panel-form panel-wide">
  <h2>Conduct stock audit <span class="mono muted small">· <?= clean(getBranchName($branchId, $pdo)) ?></span></h2>
  <p class="muted small">Enter the physically counted quantity for each product you checked — leave the rest blank to skip them. The system quantity is shown for reference; the difference is recorded automatically.</p>
  <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= clean($err) ?></div><?php endforeach; ?>

  <form method="post" id="auditForm">
    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
    <input type="hidden" name="switch_only" id="switchOnly" value="">

    <div class="form-field">
      <label>Branch</label>
      <select name="branch_id" onchange="document.getElementById('switchOnly').value='1'; this.form.submit();">
        <?php foreach ($branchesByTown as $townName => $townBranches): ?>
          <optgroup label="<?= clean($townName) ?>">
            <?php foreach ($townBranches as $b): ?>
              <option value="<?= $b['id'] ?>" <?= $b['id'] == $branchId ? 'selected' : '' ?>><?= clean($b['name']) ?></option>
            <?php endforeach; ?>
          </optgroup>
        <?php endforeach; ?>
      </select>
      <span class="form-hint">Changing this reloads the product list for that branch.</span>
    </div>

    <div class="form-field">
      <label>Notes (optional)</label>
      <input type="text" name="notes" placeholder="e.g. Monthly count, back stock room only">
    </div>

    <div class="data-table-wrap">
    <table class="data-table">
      <thead><tr><th>Product</th><th>SKU</th><th>System qty</th><th style="width:130px">Counted qty</th></tr></thead>
      <tbody>
        <?php foreach ($products as $p): ?>
        <tr>
          <td><?= clean($p['name']) ?></td>
          <td class="mono"><?= clean($p['sku']) ?></td>
          <td class="mono"><?= (int)$p['system_quantity'] ?></td>
          <td><input type="number" name="counted[<?= $p['id'] ?>]" min="0" placeholder="skip"></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>

    <div class="form-actions">
      <a href="index.php" class="btn btn-secondary">Cancel</a>
      <button type="submit" class="btn btn-primary">Save audit</button>
    </div>
  </form>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
