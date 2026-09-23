<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/rbac.php';
requirePermission('audit.export');

$branchId = currentBranchId();

if (!empty($_GET['audit'])) {
    // Single audit
    $auditId = (int)$_GET['audit'];
    $stmt = $pdo->prepare(
        "SELECT sa.audit_number, sa.created_at, b.name AS branch_name, u.full_name AS conductor_name,
                p.name AS product_name, p.sku, sai.system_quantity, sai.counted_quantity, sai.variance
         FROM stock_audit_items sai
         JOIN stock_audits sa ON sa.id = sai.audit_id
         JOIN branches b ON b.id = sa.branch_id
         JOIN users u ON u.id = sa.conducted_by
         JOIN products p ON p.id = sai.product_id
         WHERE sai.audit_id = ? ORDER BY p.name"
    );
    $stmt->execute([$auditId]);
    $rows = $stmt->fetchAll();
    $filename = 'stock-audit-' . $auditId . '.csv';
} else {
    // Date range across every audit (scoped to the user's branch unless HQ)
    $from = $_GET['from'] ?? date('Y-m-01');
    $to   = $_GET['to'] ?? date('Y-m-d');
    $sql = "SELECT sa.audit_number, sa.created_at, b.name AS branch_name, u.full_name AS conductor_name,
                   p.name AS product_name, p.sku, sai.system_quantity, sai.counted_quantity, sai.variance
            FROM stock_audit_items sai
            JOIN stock_audits sa ON sa.id = sai.audit_id
            JOIN branches b ON b.id = sa.branch_id
            JOIN users u ON u.id = sa.conducted_by
            JOIN products p ON p.id = sai.product_id
            WHERE DATE(sa.created_at) BETWEEN ? AND ?";
    $params = [$from, $to];
    if ($branchId !== null) {
        $sql .= " AND sa.branch_id = ?";
        $params[] = $branchId;
    }
    $sql .= " ORDER BY sa.created_at DESC, p.name";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    $filename = "stock-audits-$from-to-$to.csv";
}

$csvRows = [];
foreach ($rows as $r) {
    $csvRows[] = [
        $r['audit_number'], date('Y-m-d H:i', strtotime($r['created_at'])), $r['branch_name'], $r['conductor_name'],
        $r['product_name'], $r['sku'], $r['system_quantity'], $r['counted_quantity'], $r['variance'],
        $r['variance'] > 0 ? 'Excess' : ($r['variance'] < 0 ? 'Shortage' : 'Matched'),
    ];
}

exportCsv($filename, ['Audit #', 'Date', 'Branch', 'Conducted by', 'Product', 'SKU', 'System Qty', 'Counted Qty', 'Variance', 'Result'], $csvRows);
