<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/rbac.php';
requirePermission('inventory.view');

$products = $pdo->query(
    "SELECT p.sku, p.name, c.name AS category, s.name AS supplier, p.cost_price, p.selling_price, p.status,
            b.name AS branch, COALESCE(bs.quantity,0) AS quantity, COALESCE(bs.reorder_level, p.default_reorder_level) AS reorder_level
     FROM products p
     LEFT JOIN categories c ON c.id = p.category_id
     LEFT JOIN suppliers s ON s.id = p.supplier_id
     CROSS JOIN branches b
     LEFT JOIN branch_stock bs ON bs.product_id = p.id AND bs.branch_id = b.id
     WHERE b.is_active = 1
     ORDER BY p.name, b.name"
)->fetchAll();

$rows = array_map(fn($p) => [
    $p['sku'], $p['name'], $p['category'] ?? '', $p['supplier'] ?? '', $p['branch'],
    $p['cost_price'], $p['selling_price'], $p['quantity'], $p['reorder_level'], $p['status'],
], $products);

exportCsv(
    'inventory_' . date('Y-m-d') . '.csv',
    ['SKU', 'Name', 'Category', 'Supplier', 'Branch', 'Cost price', 'Selling price', 'Quantity', 'Reorder level', 'Status'],
    $rows
);
