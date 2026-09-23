<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/rbac.php';
requirePermission('activity.view');

$pageTitle = 'Activity log';

$userFilter = (int)($_GET['user'] ?? 0);
$perPage = 50;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$whereClause = $userFilter ? " WHERE al.user_id = ?" : "";
$params = $userFilter ? [$userFilter] : [];

$countStmt = $pdo->prepare("SELECT COUNT(*) c FROM activity_logs al" . $whereClause);
$countStmt->execute($params);
$totalCount = (int)$countStmt->fetch()['c'];
$totalPages = max(1, (int)ceil($totalCount / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$sql = "SELECT al.*, u.full_name FROM activity_logs al LEFT JOIN users u ON u.id = al.user_id" . $whereClause
     . " ORDER BY al.created_at DESC LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

$users = $pdo->query("SELECT id, full_name FROM users ORDER BY full_name")->fetchAll();

// Map action prefixes to icons for a bit of visual variety in the timeline
function activityIcon($action) {
    if (str_starts_with($action, 'login') || str_starts_with($action, 'logout')) return 'users';
    if (str_starts_with($action, 'inventory')) return 'inventory';
    if (str_starts_with($action, 'stock')) return 'stock';
    if (str_starts_with($action, 'orders')) return 'orders';
    if (str_starts_with($action, 'purchasing')) return 'purchasing';
    if (str_starts_with($action, 'suppliers')) return 'suppliers';
    if (str_starts_with($action, 'customers')) return 'customers';
    if (str_starts_with($action, 'users')) return 'settings';
    if (str_starts_with($action, 'categories')) return 'categories';
    return 'activity';
}

require_once __DIR__ . '/../../includes/header.php';
?>

<form method="get" class="toolbar">
  <p class="muted"><?= number_format($totalCount) ?> action<?= $totalCount === 1 ? '' : 's' ?> recorded<?= $totalCount > 0 ? ' · page ' . $page . ' of ' . $totalPages : '' ?></p>
  <div class="search-form">
    <select name="user" onchange="this.form.submit()">
      <option value="0">All users</option>
      <?php foreach ($users as $u): ?>
        <option value="<?= $u['id'] ?>" <?= $u['id'] == $userFilter ? 'selected' : '' ?>><?= clean($u['full_name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
</form>

<section class="panel">
  <?php if ($logs): ?>
  <div class="timeline">
    <?php foreach ($logs as $log): ?>
      <div class="timeline-item">
        <div class="timeline-dot"><?= icon(activityIcon($log['action']), 15) ?></div>
        <div class="timeline-body">
          <div><strong><?= clean($log['full_name'] ?? 'Unknown user') ?></strong> — <?= clean($log['description'] ?: str_replace('.', ' ', $log['action'])) ?></div>
          <div class="timeline-meta mono"><?= timeAgo($log['created_at']) ?> · <?= date('d M Y, H:i', strtotime($log['created_at'])) ?><?= $log['ip_address'] ? ' · ' . clean($log['ip_address']) : '' ?></div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php if ($totalPages > 1): ?>
    <div class="pagination">
      <?php $qs = $userFilter ? '&user=' . $userFilter : ''; ?>
      <a href="?page=<?= max(1, $page - 1) ?><?= $qs ?>" class="btn btn-secondary <?= $page <= 1 ? 'disabled' : '' ?>">&larr; Newer</a>
      <span class="muted small">Page <?= $page ?> of <?= $totalPages ?></span>
      <a href="?page=<?= min($totalPages, $page + 1) ?><?= $qs ?>" class="btn btn-secondary <?= $page >= $totalPages ? 'disabled' : '' ?>">Older &rarr;</a>
    </div>
  <?php endif; ?>
  <?php else: ?>
    <p class="empty-state">No activity recorded<?= $userFilter ? ' for this user' : '' ?>.</p>
  <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
