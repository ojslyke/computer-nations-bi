<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/rbac.php';
requirePermission('inventory.delete');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/inventory/index.php');
}
csrfCheck();

$id = (int)$_POST['id'];
$stmt = $pdo->prepare("SELECT name FROM products WHERE id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch();

if ($product) {
    $pdo->prepare("DELETE FROM products WHERE id = ?")->execute([$id]);
    logActivity('inventory.delete', "Deleted product: {$product['name']} (ID $id)");
    setFlash('success', "{$product['name']} was deleted.");
} else {
    setFlash('error', 'Product not found.');
}

redirect('modules/inventory/index.php');
