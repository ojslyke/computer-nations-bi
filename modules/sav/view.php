<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/rbac.php';
requirePermission('sav.view');

$pageTitle = 'SAV item';
$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    "SELECT si.*, p.name AS product_name, p.sku, b.name AS branch_name, u.full_name AS reporter_name
     FROM sav_items si
     JOIN products p ON p.id = si.product_id
     JOIN branches b ON b.id = si.branch_id
     JOIN users u ON u.id = si.reported_by
     WHERE si.id = ?"
);
$stmt->execute([$id]);
$sav = $stmt->fetch();

if (!$sav) {
    setFlash('error', 'SAV item not found.');
    redirect('modules/sav/index.php');
}

$errors = [];
$canManage = hasPermission('sav.manage');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canManage) {
    csrfCheck();
    $action = $_POST['action'] ?? '';

    if ($action === 'send_to_technician' && $sav['status'] === 'reported') {
        $technicianId = (int)($_POST['technician_id'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');
        if (!$technicianId) {
            $errors[] = 'Choose a technician.';
        } else {
            $pdo->prepare("UPDATE sav_items SET status = 'with_technician' WHERE id = ?")->execute([$id]);
            $pdo->prepare(
                "INSERT INTO sav_activities (sav_item_id, action, technician_id, notes, user_id) VALUES (?, 'sent_to_technician', ?, ?, ?)"
            )->execute([$id, $technicianId, $notes ?: null, $_SESSION['user_id']]);
            logActivity('sav.manage', "Sent {$sav['sav_number']} to technician");
            setFlash('success', 'Sent to technician.');
            redirect('modules/sav/view.php?id=' . $id);
        }
    }

    if ($action === 'receive_from_technician' && $sav['status'] === 'with_technician') {
        $outcome = $_POST['outcome'] ?? '';
        $notes = trim($_POST['notes'] ?? '');

        if (!in_array($outcome, ['repaired', 'still_broken', 'unrepairable'], true)) {
            $errors[] = 'Choose an outcome.';
        } else {
            $pdo->beginTransaction();
            try {
                $pdo->prepare(
                    "INSERT INTO sav_activities (sav_item_id, action, notes, user_id) VALUES (?, 'received_from_technician', ?, ?)"
                )->execute([$id, $notes ?: ucfirst(str_replace('_', ' ', $outcome)), $_SESSION['user_id']]);

                if ($outcome === 'repaired') {
                    $pdo->prepare("UPDATE sav_items SET status = 'repaired' WHERE id = ?")->execute([$id]);
                    $pdo->prepare(
                        "INSERT INTO branch_stock (branch_id, product_id, quantity) VALUES (?, ?, ?)
                         ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)"
                    )->execute([$sav['branch_id'], $sav['product_id'], $sav['quantity']]);
                    $pdo->prepare(
                        "INSERT INTO stock_movements (branch_id, product_id, type, category, quantity, reason, reference, user_id) VALUES (?,?,'in','sav',?,?,?,?)"
                    )->execute([$sav['branch_id'], $sav['product_id'], $sav['quantity'], 'SAV repaired, returned to stock', $sav['sav_number'], $_SESSION['user_id']]);
                    $pdo->prepare(
                        "INSERT INTO sav_activities (sav_item_id, action, notes, user_id) VALUES (?, 'returned_to_stock', ?, ?)"
                    )->execute([$id, "{$sav['quantity']} unit(s) added back to sellable stock", $_SESSION['user_id']]);
                    $flashMsg = 'Marked repaired — stock returned.';
                } elseif ($outcome === 'still_broken') {
                    $pdo->prepare("UPDATE sav_items SET status = 'reported' WHERE id = ?")->execute([$id]);
                    $flashMsg = 'Back at the branch, still unresolved — send to a technician again when ready.';
                } else {
                    $pdo->prepare("UPDATE sav_items SET status = 'unrepairable' WHERE id = ?")->execute([$id]);
                    $pdo->prepare(
                        "INSERT INTO sav_activities (sav_item_id, action, notes, user_id) VALUES (?, 'written_off', ?, ?)"
                    )->execute([$id, "{$sav['quantity']} unit(s) written off — not repairable", $_SESSION['user_id']]);
                    $flashMsg = 'Marked unrepairable — written off for good.';
                }

                $pdo->commit();
                logActivity('sav.manage', "Received {$sav['sav_number']} from technician: $outcome");
                setFlash('success', $flashMsg);
                redirect('modules/sav/view.php?id=' . $id);
            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = 'Could not record this. Try again.';
            }
        }
    }
}

// Re-fetch in case of the errors path re-render (status won't have changed, but keep data fresh)
$stmt->execute([$id]);
$sav = $stmt->fetch();

$activities = $pdo->prepare(
    "SELECT sa.*, t.name AS technician_name, u.full_name AS user_name
     FROM sav_activities sa
     LEFT JOIN technicians t ON t.id = sa.technician_id
     JOIN users u ON u.id = sa.user_id
     WHERE sa.sav_item_id = ? ORDER BY sa.created_at ASC"
);
$activities->execute([$id]);
$activities = $activities->fetchAll();

$technicians = $pdo->query("SELECT id, name FROM technicians ORDER BY name")->fetchAll();

$actionLabels = [
    'reported'                 => 'Reported',
    'sent_to_technician'       => 'Sent to technician',
    'received_from_technician' => 'Received from technician',
    'returned_to_stock'        => 'Returned to stock',
    'written_off'              => 'Written off',
];
$actionIcons = [
    'reported'                 => 'warning',
    'sent_to_technician'       => 'purchasing',
    'received_from_technician' => 'box-check',
    'returned_to_stock'        => 'inventory',
    'written_off'              => 'trash',
];

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="panel-grid">

<section class="panel">
  <div class="detail-header">
    <div>
      <h2 class="mono"><?= clean($sav['sav_number']) ?></h2>
      <p class="muted"><?= clean($sav['product_name']) ?> · <?= (int)$sav['quantity'] ?> unit(s) · <?= clean($sav['branch_name']) ?></p>
    </div>
    <span class="badge badge-<?= $sav['status'] === 'repaired' ? 'completed' : ($sav['status'] === 'unrepairable' ? 'cancelled' : 'pending') ?>"><?= clean(str_replace('_', ' ', $sav['status'])) ?></span>
  </div>

  <div class="detail-grid">
    <div><span class="muted">Issue</span><br><?= clean($sav['issue_description']) ?></div>
    <div><span class="muted">Reported by</span><br><?= clean($sav['reporter_name']) ?> · <?= date('d M Y', strtotime($sav['created_at'])) ?></div>
  </div>

  <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= clean($err) ?></div><?php endforeach; ?>

  <?php if ($canManage && $sav['status'] === 'reported'): ?>
    <div class="form-actions" style="justify-content:flex-start; margin-top:16px;">
      <form method="post" style="display:flex; gap:8px; align-items:flex-end; flex-wrap:wrap;">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="action" value="send_to_technician">
        <div class="form-field" style="margin-bottom:0;">
          <label>Send to technician</label>
          <select name="technician_id" required>
            <option value="">— Select —</option>
            <?php foreach ($technicians as $t): ?>
              <option value="<?= $t['id'] ?>"><?= clean($t['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-field" style="margin-bottom:0;">
          <label>Notes</label>
          <input type="text" name="notes" placeholder="optional">
        </div>
        <button type="submit" class="btn btn-primary"><?= icon('purchasing', 15) ?> Send</button>
      </form>
    </div>
    <?php if (!$technicians): ?>
      <p class="muted small" style="margin-top:10px;">No technicians yet — <a href="../technicians/index.php" class="link">add one</a> first.</p>
    <?php endif; ?>
  <?php endif; ?>

  <?php if ($canManage && $sav['status'] === 'with_technician'): ?>
    <div class="form-actions" style="justify-content:flex-start; margin-top:16px; flex-direction:column; align-items:flex-start; gap:10px;">
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="action" value="receive_from_technician">
        <label class="muted small" style="display:block; margin-bottom:8px; font-weight:600;">Receive from technician — outcome</label>
        <div style="display:flex; gap:16px; flex-wrap:wrap; margin-bottom:10px;">
          <label class="checkbox-row"><input type="radio" name="outcome" value="repaired" required> Repaired — return to stock</label>
          <label class="checkbox-row"><input type="radio" name="outcome" value="still_broken"> Still broken — back to branch</label>
          <label class="checkbox-row"><input type="radio" name="outcome" value="unrepairable"> Unrepairable — write off</label>
        </div>
        <input type="text" name="notes" placeholder="Notes (optional)" style="margin-bottom:10px; width:100%; max-width:400px; padding:9px 12px; border:1px solid var(--border); border-radius:var(--radius-sm);">
        <div><button type="submit" class="btn btn-primary"><?= icon('box-check', 15) ?> Record outcome</button></div>
      </form>
    </div>
  <?php endif; ?>
</section>

<section class="panel">
  <h2>Activity timeline</h2>
  <div class="timeline">
    <?php foreach ($activities as $a): ?>
      <div class="timeline-item">
        <div class="timeline-dot"><?= icon($actionIcons[$a['action']] ?? 'activity', 15) ?></div>
        <div class="timeline-body">
          <div><strong><?= clean($actionLabels[$a['action']] ?? $a['action']) ?></strong><?= $a['technician_name'] ? ' — ' . clean($a['technician_name']) : '' ?></div>
          <?php if ($a['notes']): ?><div class="muted small"><?= clean($a['notes']) ?></div><?php endif; ?>
          <div class="timeline-meta mono"><?= date('d M Y, H:i', strtotime($a['created_at'])) ?> · <?= clean($a['user_name']) ?></div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>

</div>

<a href="index.php" class="btn btn-secondary">Back to SAV</a>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
