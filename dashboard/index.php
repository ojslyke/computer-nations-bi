<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/rbac.php';
require_once __DIR__ . '/../includes/icons.php';
requirePermission('dashboard.view');

$pageTitle = 'Dashboard';
$branchId = currentBranchId();

$totalProducts = $lowStock = $todayOrders = $monthRevenue = $lastMonthRevenue = 0;
$pendingPOs = 0;
$lowStockItems = [];
$recentOrders  = [];
$revenueTrend  = [];
$categoryBreakdown = [];
$branchRevenue = [];

if (hasPermission('inventory.view')) {
    $totalProducts = $pdo->query("SELECT COUNT(*) c FROM products WHERE status='active'")->fetch()['c'];

    if ($branchId !== null) {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) c FROM branch_stock bs JOIN products p ON p.id = bs.product_id
             WHERE bs.branch_id = ? AND bs.quantity <= bs.reorder_level AND p.status = 'active'"
        );
        $stmt->execute([$branchId]);
        $lowStock = $stmt->fetch()['c'];

        $stmt = $pdo->prepare(
            "SELECT p.name, p.sku, bs.quantity, bs.reorder_level FROM branch_stock bs
             JOIN products p ON p.id = bs.product_id
             WHERE bs.branch_id = ? AND bs.quantity <= bs.reorder_level AND p.status = 'active'
             ORDER BY bs.quantity ASC LIMIT 5"
        );
        $stmt->execute([$branchId]);
        $lowStockItems = $stmt->fetchAll();

        $categoryBreakdown = cacheRemember("dash:category:$branchId", 60, function () use ($pdo, $branchId) {
            $stmt = $pdo->prepare(
                "SELECT COALESCE(c.name, 'Uncategorized') AS category, SUM(bs.quantity) AS units
                 FROM branch_stock bs JOIN products p ON p.id = bs.product_id LEFT JOIN categories c ON c.id = p.category_id
                 WHERE bs.branch_id = ? AND p.status = 'active'
                 GROUP BY c.name ORDER BY units DESC LIMIT 6"
            );
            $stmt->execute([$branchId]);
            return $stmt->fetchAll();
        });
    } else {
        $lowStock = $pdo->query(
            "SELECT COUNT(*) c FROM branch_stock bs JOIN products p ON p.id = bs.product_id
             WHERE bs.quantity <= bs.reorder_level AND p.status = 'active'"
        )->fetch()['c'];

        $lowStockItems = $pdo->query(
            "SELECT p.name, p.sku, bs.quantity, bs.reorder_level, b.name AS branch_name FROM branch_stock bs
             JOIN products p ON p.id = bs.product_id JOIN branches b ON b.id = bs.branch_id
             WHERE bs.quantity <= bs.reorder_level AND p.status = 'active'
             ORDER BY bs.quantity ASC LIMIT 5"
        )->fetchAll();

        $categoryBreakdown = cacheRemember('dash:category:all', 60, function () use ($pdo) {
            return $pdo->query(
                "SELECT COALESCE(c.name, 'Uncategorized') AS category, SUM(bs.quantity) AS units
                 FROM branch_stock bs JOIN products p ON p.id = bs.product_id LEFT JOIN categories c ON c.id = p.category_id
                 WHERE p.status = 'active'
                 GROUP BY c.name ORDER BY units DESC LIMIT 6"
            )->fetchAll();
        });
    }
}

if (hasPermission('orders.view')) {
    $branchClause = $branchId !== null ? " AND branch_id = ?" : "";
    $branchParam = $branchId !== null ? [$branchId] : [];

    $stmt = $pdo->prepare("SELECT COUNT(*) c FROM orders WHERE DATE(created_at) = CURDATE()$branchClause");
    $stmt->execute($branchParam);
    $todayOrders = $stmt->fetch()['c'];

    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(total_amount),0) s FROM orders
         WHERE status = 'completed' AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())$branchClause"
    );
    $stmt->execute($branchParam);
    $monthRevenue = $stmt->fetch()['s'];

    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(total_amount),0) s FROM orders
         WHERE status = 'completed' AND MONTH(created_at) = MONTH(CURDATE() - INTERVAL 1 MONTH) AND YEAR(created_at) = YEAR(CURDATE() - INTERVAL 1 MONTH)$branchClause"
    );
    $stmt->execute($branchParam);
    $lastMonthRevenue = $stmt->fetch()['s'];

    $stmt = $pdo->prepare(
        "SELECT o.order_number, o.total_amount, o.status, o.created_at, c.name AS customer_name, b.name AS branch_name
         FROM orders o LEFT JOIN customers c ON c.id = o.customer_id JOIN branches b ON b.id = o.branch_id
         WHERE 1=1 $branchClause
         ORDER BY o.created_at DESC LIMIT 6"
    );
    $stmt->execute($branchParam);
    $recentOrders = $stmt->fetchAll();

    // Revenue for the last 14 days, zero-filled so the chart has no gaps
    $byDate = cacheRemember("dash:trend:" . ($branchId ?? 'all'), 60, function () use ($pdo, $branchClause, $branchParam) {
        $stmt = $pdo->prepare(
            "SELECT DATE(created_at) d, SUM(total_amount) s FROM orders
             WHERE status = 'completed' AND created_at >= CURDATE() - INTERVAL 13 DAY $branchClause
             GROUP BY DATE(created_at)"
        );
        $stmt->execute($branchParam);
        $byDate = [];
        foreach ($stmt->fetchAll() as $r) { $byDate[$r['d']] = (float)$r['s']; }
        return $byDate;
    });
    for ($i = 13; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $revenueTrend[] = ['label' => date('d M', strtotime($d)), 'value' => $byDate[$d] ?? 0];
    }

    // Only meaningful in "All branches" view — this month's revenue split by location
    if ($branchId === null) {
        $branchRevenue = cacheRemember('dash:branch_revenue', 60, function () use ($pdo) {
            return $pdo->query(
                "SELECT b.name AS branch, COALESCE(SUM(o.total_amount),0) AS revenue
                 FROM branches b
                 LEFT JOIN orders o ON o.branch_id = b.id AND o.status = 'completed'
                     AND MONTH(o.created_at) = MONTH(CURDATE()) AND YEAR(o.created_at) = YEAR(CURDATE())
                 WHERE b.is_active = 1
                 GROUP BY b.id ORDER BY revenue DESC"
            )->fetchAll();
        });
    }
}

if (hasPermission('purchasing.view')) {
    $branchClause = $branchId !== null ? " AND branch_id = ?" : "";
    $branchParam = $branchId !== null ? [$branchId] : [];
    $stmt = $pdo->prepare("SELECT COUNT(*) c FROM purchase_orders WHERE status IN ('draft','ordered','partially_received')$branchClause");
    $stmt->execute($branchParam);
    $pendingPOs = $stmt->fetch()['c'];
}

$revenueChange = $lastMonthRevenue > 0 ? round((($monthRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100) : null;

require_once __DIR__ . '/../includes/header.php';
?>

<div class="widget-grid">
  <?php if (hasPermission('inventory.view')): ?>
  <div class="widget">
    <div class="widget-top">
      <div>
        <span class="widget-label">Active products</span>
        <span class="widget-value"><?= (int)$totalProducts ?></span>
      </div>
      <div class="widget-icon"><?= icon('inventory', 16) ?></div>
    </div>
  </div>
  <div class="widget <?= $lowStock > 0 ? 'widget-warning' : 'widget-success' ?>">
    <div class="widget-top">
      <div>
        <span class="widget-label">Low stock alerts</span>
        <span class="widget-value"><?= (int)$lowStock ?></span>
      </div>
      <div class="widget-icon"><?= icon($lowStock > 0 ? 'warning' : 'box-check', 16) ?></div>
    </div>
  </div>
  <?php endif; ?>

  <?php if (hasPermission('orders.view')): ?>
  <div class="widget">
    <div class="widget-top">
      <div>
        <span class="widget-label">Orders today</span>
        <span class="widget-value"><?= (int)$todayOrders ?></span>
      </div>
      <div class="widget-icon"><?= icon('orders', 16) ?></div>
    </div>
  </div>
  <div class="widget widget-accent">
    <div class="widget-top">
      <div>
        <span class="widget-label">Revenue this month</span>
        <span class="widget-value"><?= formatMoney($monthRevenue) ?></span>
        <?php if ($revenueChange !== null): ?>
          <div class="widget-trend <?= $revenueChange >= 0 ? 'up' : 'down' ?>">
            <?= icon('trend', 12) ?> <?= $revenueChange >= 0 ? '+' : '' ?><?= $revenueChange ?>% vs last month
          </div>
        <?php endif; ?>
      </div>
      <div class="widget-icon"><?= icon('trend', 16) ?></div>
    </div>
  </div>
  <?php endif; ?>

  <?php if (hasPermission('purchasing.view')): ?>
  <div class="widget">
    <div class="widget-top">
      <div>
        <span class="widget-label">Open purchase orders</span>
        <span class="widget-value"><?= (int)$pendingPOs ?></span>
      </div>
      <div class="widget-icon"><?= icon('purchasing', 16) ?></div>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php if (hasPermission('orders.view') || hasPermission('inventory.view')): ?>
<div class="chart-grid">
  <?php if (hasPermission('orders.view')): ?>
  <section class="panel chart-card">
    <h2><?= $branchId === null && $branchRevenue ? 'Revenue by branch — this month' : 'Revenue — last 14 days' ?></h2>
    <div class="chart-wrap">
      <div class="chart-skeleton"></div>
      <canvas id="revenueChart" height="90"></canvas>
    </div>
  </section>
  <?php endif; ?>

  <?php if (hasPermission('inventory.view') && $categoryBreakdown): ?>
  <section class="panel chart-card">
    <h2>Stock by category<?= $branchId !== null ? ' — ' . clean(getBranchName($branchId, $pdo)) : '' ?></h2>
    <div class="chart-wrap">
      <div class="chart-skeleton"></div>
      <canvas id="categoryChart" height="90"></canvas>
    </div>
  </section>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="panel-grid">

  <?php if (hasPermission('inventory.view')): ?>
  <section class="panel">
    <h2>Items needing restock</h2>
    <?php if ($lowStockItems): ?>
      <div class="data-table-wrap">
      <table class="data-table" data-has-server-search="true">
        <thead><tr><th>Product</th><th>SKU</th><?= $branchId === null ? '<th>Branch</th>' : '' ?><th>Qty left</th><th>Reorder at</th></tr></thead>
        <tbody>
          <?php foreach ($lowStockItems as $item): ?>
          <tr>
            <td><?= clean($item['name']) ?></td>
            <td class="mono"><?= clean($item['sku']) ?></td>
            <?= $branchId === null ? '<td>' . clean($item['branch_name']) . '</td>' : '' ?>
            <td class="mono num-danger"><?= (int)$item['quantity'] ?></td>
            <td class="mono"><?= (int)$item['reorder_level'] ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    <?php else: ?>
      <p class="empty-state">Every product is above its reorder level.</p>
    <?php endif; ?>
  </section>
  <?php endif; ?>

  <?php if (hasPermission('orders.view')): ?>
  <section class="panel">
    <h2>Recent orders</h2>
    <?php if ($recentOrders): ?>
      <div class="data-table-wrap">
      <table class="data-table" data-has-server-search="true">
        <thead><tr><th>Order #</th><?= $branchId === null ? '<th>Branch</th>' : '' ?><th>Customer</th><th>Total</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach ($recentOrders as $o): ?>
          <tr>
            <td class="mono"><?= clean($o['order_number']) ?></td>
            <?= $branchId === null ? '<td>' . clean($o['branch_name']) . '</td>' : '' ?>
            <td><?= clean($o['customer_name'] ?? 'Walk-in') ?></td>
            <td class="mono"><?= formatMoney($o['total_amount']) ?></td>
            <td><span class="badge badge-<?= clean($o['status']) ?>"><?= clean($o['status']) ?></span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    <?php else: ?>
      <p class="empty-state">No orders yet — create one from the Orders page.</p>
    <?php endif; ?>
  </section>
  <?php endif; ?>

</div>

<?php if (hasPermission('orders.view') || (hasPermission('inventory.view') && $categoryBreakdown)): ?>
<script defer>
function hideChartSkeleton(canvasId) {
  const canvas = document.getElementById(canvasId);
  const wrap = canvas && canvas.closest('.chart-wrap');
  const sk = wrap && wrap.querySelector('.chart-skeleton');
  if (sk) sk.remove();
}
</script>
<script defer src="<?= BASE_URL ?>assets/js/vendor/chart.umd.min.js"></script>
<?php endif; ?>

<?php if (hasPermission('orders.view')): ?>
<script defer>
(function () {
  <?php if ($branchId === null && $branchRevenue): ?>
  const labels = <?= json_encode(array_column($branchRevenue, 'branch')) ?>;
  const values = <?= json_encode(array_map('floatval', array_column($branchRevenue, 'revenue'))) ?>;
  new Chart(document.getElementById('revenueChart'), {
    type: 'bar',
    data: { labels: labels, datasets: [{ label: 'Revenue (XAF)', data: values, backgroundColor: '#2F6FED', borderRadius: 5, maxBarThickness: 46 }] },
    options: {
      plugins: { legend: { display: false } },
      scales: {
        x: { grid: { display: false }, ticks: { font: { family: 'Inter', size: 11 } } },
        y: { grid: { color: '#EEF0F3' }, ticks: { font: { family: 'IBM Plex Mono', size: 10 }, callback: (v) => v >= 1000 ? (v/1000) + 'k' : v } }
      }
    }
  });
  hideChartSkeleton('revenueChart');
  <?php else: ?>
  const labels = <?= json_encode(array_column($revenueTrend, 'label')) ?>;
  const values = <?= json_encode(array_column($revenueTrend, 'value')) ?>;
  new Chart(document.getElementById('revenueChart'), {
    type: 'line',
    data: {
      labels: labels,
      datasets: [{
        label: 'Revenue (XAF)', data: values, borderColor: '#2F6FED', backgroundColor: 'rgba(47,111,237,0.08)',
        fill: true, tension: 0.35, pointRadius: 0, borderWidth: 2
      }]
    },
    options: {
      plugins: { legend: { display: false } },
      scales: {
        x: { grid: { display: false }, ticks: { font: { family: 'Inter', size: 11 } } },
        y: { grid: { color: '#EEF0F3' }, ticks: { font: { family: 'IBM Plex Mono', size: 10 }, callback: (v) => v >= 1000 ? (v/1000) + 'k' : v } }
      }
    }
  });
  hideChartSkeleton('revenueChart');
  <?php endif; ?>
})();
</script>
<?php endif; ?>

<?php if (hasPermission('inventory.view') && $categoryBreakdown): ?>
<script defer>
(function () {
  new Chart(document.getElementById('categoryChart'), {
    type: 'doughnut',
    data: {
      labels: <?= json_encode(array_column($categoryBreakdown, 'category')) ?>,
      datasets: [{
        data: <?= json_encode(array_map('intval', array_column($categoryBreakdown, 'units'))) ?>,
        backgroundColor: ['#2F6FED', '#7C5CFC', '#1F9D63', '#D97706', '#DC2626', '#98A2B3'],
        borderWidth: 0
      }]
    },
    options: {
      cutout: '68%',
      plugins: { legend: { position: 'bottom', labels: { font: { family: 'Inter', size: 11 }, boxWidth: 10, padding: 12 } } }
    }
  });
  hideChartSkeleton('categoryChart');
})();
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
