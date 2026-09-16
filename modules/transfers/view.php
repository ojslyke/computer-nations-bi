<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/rbac.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    "SELECT st.*, fb.name AS from_branch_name, tb.name AS to_branch_name,
            u.full_name AS staff_name, ru.full_name AS receiver_name
     FROM stock_transfers st
     JOIN branches fb ON fb.id = st.from_branch_id
     JOIN branches tb ON tb.id = st.to_branch_id
     JOIN users u ON u.id = st.user_id
     LEFT JOIN users ru ON ru.id = st.received_by
     WHERE st.id = ?"
);
$stmt->execute([$id]);
$transfer = $stmt->fetch();

// Permission and labels depend on the record's own type, since this one file
// is shared by both the Transfers module (same town) and Expeditions (between towns).
$isExpedition = $transfer && $transfer['type'] === 'expedition';
$noun         = $isExpedition ? 'Expedition' : 'Transfer';
$viewPerm     = $isExpedition ? 'expeditions.view'    : 'transfers.view';
$createPerm   = $isExpedition ? 'expeditions.create'  : 'transfers.create';
$receivePerm  = $isExpedition ? 'expeditions.receive' : 'transfers.receive';
$indexPath    = $isExpedition ? 'modules/expeditions/index.php' : 'modules/transfers/index.php';
$pageTitle    = $noun;

requirePermission($viewPerm);

if (!$transfer) {
    setFlash('error', "$noun not found.");
    redirect($indexPath);
}

$items = $pdo->prepare(
    "SELECT sti.*, p.name AS product_name, p.sku FROM stock_transfer_items sti JOIN products p ON p.id = sti.product_id WHERE sti.transfer_id = ?"
);
$items->execute([$id]);
$items = $items->fetchAll();

$activeBranchId = currentBranchId();
$canActOnDest = isMultiBranchUser() || $activeBranchId === (int)$transfer['to_branch_id'];
$isPending = $transfer['status'] === 'pending';

// Receiving is the ONLY action left once a transfer/expedition exists — sending
// already happened at creation, and it can't be edited or cancelled afterward.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isPending && hasPermission($receivePerm) && $canActOnDest) {
    csrfCheck();
    if (($_POST['action'] ?? '') === 'receive') {
        $pdo->beginTransaction();
        try {
            foreach ($items as $item) {
                $pdo->prepare(
                    "INSERT INTO branch_stock (branch_id, product_id, quantity) VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)"
                )->execute([$transfer['to_branch_id'], $item['product_id'], $item['quantity']]);
                $pdo->prepare(
                    "INSERT INTO stock_movements (branch_id, product_id, type, category, quantity, reason, reference, user_id) VALUES (?,?,'in','transfer',?,?,?,?)"
                )->execute([$transfer['to_branch_id'], $item['product_id'], $item['quantity'], 'Received from ' . $transfer['from_branch_name'], $transfer['transfer_number'], $_SESSION['user_id']]);
            }
            $pdo->prepare("UPDATE stock_transfers SET status = 'received', received_at = NOW(), received_by = ? WHERE id = ?")
                ->execute([$_SESSION['user_id'], $id]);
            $pdo->commit();
            logActivity(strtolower($noun) . '.receive', "Received {$transfer['transfer_number']}");
            setFlash('success', "$noun received — stock added to this branch.");
            redirect(($isExpedition ? 'modules/expeditions/' : 'modules/transfers/') . 'view.php?id=' . $id);
        } catch (Exception $e) {
            $pdo->rollBack();
            setFlash('error', "Could not receive the $noun.");
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<section class="panel panel-wide">
  <div class="detail-header">
    <div>
      <h2 class="mono"><?= clean($transfer['transfer_number']) ?> <span class="badge" style="background:var(--violet-soft); color:var(--violet); text-transform:none;"><?= clean($noun) ?></span></h2>
      <p class="muted"><?= date('d M Y, H:i', strtotime($transfer['created_at'])) ?> · Sent by <?= clean($transfer['staff_name']) ?><?= $transfer['receiver_name'] ? ' · Received by ' . clean($transfer['receiver_name']) : '' ?></p>
    </div>
    <span class="badge badge-<?= clean($transfer['status']) ?>"><?= clean(str_replace('_', ' ', $transfer['status'])) ?></span>
  </div>

  <div class="detail-grid">
    <div><span class="muted">From</span><br><strong><?= clean($transfer['from_branch_name']) ?></strong></div>
    <div><?= icon('arrow-right', 18) ?></div>
    <div><span class="muted">To</span><br><strong><?= clean($transfer['to_branch_name']) ?></strong></div>
    <?php if ($transfer['notes']): ?><div><span class="muted">Notes</span><br><?= clean($transfer['notes']) ?></div><?php endif; ?>
  </div>

  <?php if ($isPending): ?>
    <div class="alert alert-success" style="background:var(--warning-soft); color:#B54708; border-color:#FEDF89;">
      <?= icon('warning', 16) ?> Pending on both ends — stock already left <?= clean($transfer['from_branch_name']) ?>. This can't be edited; only <?= clean($transfer['to_branch_name']) ?> can confirm receipt.
    </div>
  <?php endif; ?>

  <div class="data-table-wrap">
  <table class="data-table">
    <thead><tr><th>Product</th><th>SKU</th><th>Quantity</th></tr></thead>
    <tbody>
      <?php foreach ($items as $item): ?>
      <tr>
        <td><?= clean($item['product_name']) ?></td>
        <td class="mono"><?= clean($item['sku']) ?></td>
        <td class="mono"><?= (int)$item['quantity'] ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>

  <div class="form-actions">
    <a href="index.php" class="btn btn-secondary">Back to <?= strtolower(clean($noun)) ?>s</a>

    <?php if ($isPending && hasPermission($receivePerm) && $canActOnDest): ?>
      <form method="post" class="js-confirm" data-confirm-title="Confirm receipt?" data-confirm-message="Only confirm once the goods have physically arrived at <?= clean($transfer['to_branch_name']) ?>. This updates the status on both branches." style="display:inline">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="action" value="receive">
        <button type="submit" class="btn btn-primary"><?= icon('box-check', 15) ?> Confirm receipt</button>
      </form>
    <?php elseif ($isPending && !$canActOnDest): ?>
      <p class="muted small" style="align-self:center;">Switch to <?= clean($transfer['to_branch_name']) ?> to confirm receipt there.</p>
    <?php endif; ?>
  </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
