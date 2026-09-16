<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/rbac.php';
requirePermission('orders.view');

$orders = $pdo->query(
    "SELECT o.order_number, o.created_at, c.name AS customer, u.full_name AS staff, b.name AS branch, o.status, o.total_amount
     FROM orders o
     LEFT JOIN customers c ON c.id = o.customer_id
     JOIN users u ON u.id = o.user_id
     JOIN branches b ON b.id = o.branch_id
     ORDER BY o.created_at DESC"
)->fetchAll();

$rows = array_map(fn($o) => [
    $o['order_number'], $o['created_at'], $o['customer'] ?? 'Walk-in', $o['staff'], $o['branch'], $o['status'], $o['total_amount'],
], $orders);

exportCsv(
    'orders_' . date('Y-m-d') . '.csv',
    ['Order #', 'Date', 'Customer', 'Staff', 'Branch', 'Status', 'Total (XAF)'],
    $rows
);
