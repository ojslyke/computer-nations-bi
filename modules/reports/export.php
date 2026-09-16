<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/rbac.php';
requirePermission('reports.export');

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to'] ?? date('Y-m-d');

$stmt = $pdo->prepare(
    "SELECT p.sku, p.name, SUM(oi.quantity) AS units_sold, SUM(oi.subtotal) AS revenue
     FROM order_items oi
     JOIN orders o ON o.id = oi.order_id
     JOIN products p ON p.id = oi.product_id
     WHERE o.status = 'completed' AND DATE(o.created_at) BETWEEN ? AND ?
     GROUP BY oi.product_id
     ORDER BY units_sold DESC"
);
$stmt->execute([$from, $to]);
$rows = array_map(fn($r) => [$r['sku'], $r['name'], $r['units_sold'], $r['revenue']], $stmt->fetchAll());

exportCsv(
    "sales_report_{$from}_to_{$to}.csv",
    ['SKU', 'Product', 'Units sold', 'Revenue (XAF)'],
    $rows
);
