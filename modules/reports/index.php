<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/rbac.php';
requirePermission('reports.view');

$pageTitle = 'Reports';
$branchId = currentBranchId();

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to'] ?? date('Y-m-d');
$branchClause = $branchId !== null ? " AND branch_id = ?" : "";

$salesSummary = $pdo->prepare(
    "SELECT COUNT(*) AS order_count, COALESCE(SUM(total_amount),0) AS revenue
     FROM orders WHERE status = 'completed' AND DATE(created_at) BETWEEN ? AND ?$branchClause"
);
$salesSummary->execute($branchId !== null ? [$from, $to, $branchId] : [$from, $to]);
$salesSummary = $salesSummary->fetch();

$topProducts = $pdo->prepare(
    "SELECT p.name, p.sku, SUM(oi.quantity) AS units_sold, SUM(oi.subtotal) AS revenue
     FROM order_items oi
     JOIN orders o ON o.id = oi.order_id
     JOIN products p ON p.id = oi.product_id
     WHERE o.status = 'completed' AND DATE(o.created_at) BETWEEN ? AND ?" . str_replace('branch_id', 'o.branch_id', $branchClause) . "
     GROUP BY oi.product_id
     ORDER BY units_sold DESC LIMIT 10"
);
$topProducts->execute($branchId !== null ? [$from, $to, $branchId] : [$from, $to]);
$topProducts = $topProducts->fetchAll();

$inventorySql = "SELECT COUNT(DISTINCT p.id) AS sku_count,
            COALESCE(SUM(bs.quantity),0) AS total_units,
            COALESCE(SUM(bs.quantity * p.cost_price),0) AS stock_value_at_cost,
            COALESCE(SUM(bs.quantity * p.selling_price),0) AS stock_value_at_price
     FROM products p JOIN branch_stock bs ON bs.product_id = p.id
     WHERE p.status = 'active'" . ($branchId !== null ? " AND bs.branch_id = ?" : "");
$stmt = $pdo->prepare($inventorySql);
$stmt->execute($branchId !== null ? [$branchId] : []);
$inventoryValue = $stmt->fetch();

require_once __DIR__ . '/../../includes/header.php';
?>

<form method="get" class="toolbar">
  <p class="muted" style="margin:0;">Showing <?= $branchId !== null ? clean(getBranchName($branchId, $pdo)) : 'all branches' ?>.</p>
  <div class="search-form">
    <label for="from" class="muted small">From</label>
    <input type="date" id="from" name="from" value="<?= clean($from) ?>">
    <label for="to" class="muted small">To</label>
    <input type="date" id="to" name="to" value="<?= clean($to) ?>">
    <button type="submit" class="btn btn-secondary">Apply</button>
    <?php if (hasPermission('reports.export')): ?>
      <a href="export.php?from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>" class="btn btn-secondary"><?= icon('download', 15) ?> Export CSV</a>
    <?php endif; ?>
  </div>
</form>

<div class="widget-grid">
  <div class="widget">
    <span class="widget-label">Completed orders</span>
    <span class="widget-value"><?= (int)$salesSummary['order_count'] ?></span>
  </div>
  <div class="widget widget-accent">
    <span class="widget-label">Revenue in range</span>
    <span class="widget-value"><?= formatMoney($salesSummary['revenue']) ?></span>
  </div>
  <div class="widget">
    <span class="widget-label">Stock on hand (units)</span>
    <span class="widget-value"><?= (int)$inventoryValue['total_units'] ?></span>
  </div>
  <div class="widget">
    <span class="widget-label">Stock value (at cost)</span>
    <span class="widget-value"><?= formatMoney($inventoryValue['stock_value_at_cost']) ?></span>
  </div>
</div>

<section class="panel">
  <h2>Top-selling products</h2>
  <?php if ($topProducts): ?>
  <table class="data-table">
    <thead><tr><th>Product</th><th>SKU</th><th>Units sold</th><th>Revenue</th></tr></thead>
    <tbody>
      <?php foreach ($topProducts as $p): ?>
      <tr>
        <td><?= clean($p['name']) ?></td>
        <td class="mono"><?= clean($p['sku']) ?></td>
        <td class="mono"><?= (int)$p['units_sold'] ?></td>
        <td class="mono"><?= formatMoney($p['revenue']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php else: ?>
    <p class="empty-state">No completed orders in this date range.</p>
  <?php endif; ?>
</section>

<?php if ($branchId === null): ?>
<?php
$branchSales = $pdo->prepare(
    "SELECT b.name AS branch, COUNT(o.id) AS order_count, COALESCE(SUM(o.total_amount),0) AS revenue
     FROM branches b LEFT JOIN orders o ON o.branch_id = b.id AND o.status = 'completed' AND DATE(o.created_at) BETWEEN ? AND ?
     WHERE b.is_active = 1 GROUP BY b.id ORDER BY revenue DESC"
);
$branchSales->execute([$from, $to]);
$branchSales = $branchSales->fetchAll();
?>
<section class="panel">
  <h2>Sales by branch</h2>
  <div class="data-table-wrap">
  <table class="data-table">
    <thead><tr><th>Branch</th><th>Orders</th><th>Revenue</th></tr></thead>
    <tbody>
      <?php foreach ($branchSales as $bs): ?>
      <tr>
        <td><?= clean($bs['branch']) ?></td>
        <td class="mono"><?= (int)$bs['order_count'] ?></td>
        <td class="mono"><?= formatMoney($bs['revenue']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</section>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
