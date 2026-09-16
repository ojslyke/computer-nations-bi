<?php
requireLogin();
require_once __DIR__ . '/icons.php';

$user = currentUser();
$flash = getFlash();
$currentPage = basename($_SERVER['SCRIPT_NAME']);
$currentDir  = basename(dirname($_SERVER['SCRIPT_NAME']));
$activeBranchId = currentBranchId();

function navActive($dir, $matchDir) {
    return $dir === $matchDir ? 'nav-link active' : 'nav-link';
}

$branchesByTown = listBranchesByTown($pdo);

// Low-stock count powers the notification bell — scoped to the active branch,
// or aggregated across all branches when an HQ user has "All branches" selected.
$notifCount = 0;
$notifItems = [];
if (hasPermission('inventory.view')) {
    if ($activeBranchId !== null) {
        $stmt = $pdo->prepare(
            "SELECT p.id, p.name, p.sku, bs.quantity, bs.reorder_level FROM branch_stock bs
             JOIN products p ON p.id = bs.product_id
             WHERE bs.branch_id = ? AND bs.quantity <= bs.reorder_level AND p.status = 'active'
             ORDER BY bs.quantity ASC LIMIT 6"
        );
        $stmt->execute([$activeBranchId]);
        $notifItems = $stmt->fetchAll();
        $countStmt = $pdo->prepare(
            "SELECT COUNT(*) c FROM branch_stock bs JOIN products p ON p.id = bs.product_id
             WHERE bs.branch_id = ? AND bs.quantity <= bs.reorder_level AND p.status = 'active'"
        );
        $countStmt->execute([$activeBranchId]);
        $notifCount = $countStmt->fetch()['c'];
    } else {
        $notifItems = $pdo->query(
            "SELECT p.id, p.name, p.sku, bs.quantity, bs.reorder_level, b.name AS branch_name FROM branch_stock bs
             JOIN products p ON p.id = bs.product_id
             JOIN branches b ON b.id = bs.branch_id
             WHERE bs.quantity <= bs.reorder_level AND p.status = 'active'
             ORDER BY bs.quantity ASC LIMIT 6"
        )->fetchAll();
        $notifCount = $pdo->query(
            "SELECT COUNT(*) c FROM branch_stock bs JOIN products p ON p.id = bs.product_id
             WHERE bs.quantity <= bs.reorder_level AND p.status = 'active'"
        )->fetch()['c'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($pageTitle) ? clean($pageTitle) . ' — ' : '' ?><?= SITE_NAME ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
</head>
<body>

<div class="app-shell">

  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <span class="brand-mark">CN</span>
      <span class="brand-name">Computer Nations</span>
    </div>

    <div class="sidebar-scroll">
      <div class="nav-group-label">Overview</div>
      <nav class="sidebar-nav">
        <a href="<?= BASE_URL ?>dashboard/index.php" class="<?= navActive($currentDir, 'dashboard') ?>">
          <?= icon('dashboard') ?> Dashboard
        </a>
      </nav>

      <?php if (hasPermission('inventory.view') || hasPermission('stock.view') || hasPermission('categories.manage')): ?>
      <div class="nav-group-label">Catalog</div>
      <nav class="sidebar-nav">
        <?php if (hasPermission('inventory.view')): ?>
        <a href="<?= BASE_URL ?>modules/inventory/index.php" class="<?= navActive($currentDir, 'inventory') ?>">
          <?= icon('inventory') ?> Inventory
          <?php if ($notifCount > 0): ?><span class="nav-badge"><?= $notifCount ?></span><?php endif; ?>
        </a>
        <?php endif; ?>
        <?php if (hasPermission('categories.manage')): ?>
        <a href="<?= BASE_URL ?>modules/categories/index.php" class="<?= navActive($currentDir, 'categories') ?>">
          <?= icon('categories') ?> Categories
        </a>
        <?php endif; ?>
        <?php if (hasPermission('stock.view')): ?>
        <a href="<?= BASE_URL ?>modules/stock/index.php" class="<?= navActive($currentDir, 'stock') ?>">
          <?= icon('stock') ?> Stock movements
        </a>
        <?php endif; ?>
      </nav>
      <?php endif; ?>

      <?php if (hasPermission('transfers.view') || hasPermission('expeditions.view') || hasPermission('sav.view')): ?>
      <div class="nav-group-label">Warehouse</div>
      <nav class="sidebar-nav">
        <?php if (hasPermission('transfers.view')): ?>
        <a href="<?= BASE_URL ?>modules/transfers/index.php" class="<?= navActive($currentDir, 'transfers') ?>">
          <?= icon('transfer') ?> Transfers
        </a>
        <?php endif; ?>
        <?php if (hasPermission('expeditions.view')): ?>
        <a href="<?= BASE_URL ?>modules/expeditions/index.php" class="<?= navActive($currentDir, 'expeditions') ?>">
          <?= icon('purchasing') ?> Expedition
        </a>
        <?php endif; ?>
        <?php if (hasPermission('sav.view')): ?>
        <a href="<?= BASE_URL ?>modules/sav/index.php" class="<?= navActive($currentDir, 'sav') ?>">
          <?= icon('warning') ?> SAV
        </a>
        <?php endif; ?>
        <?php if (hasPermission('sav.manage')): ?>
        <a href="<?= BASE_URL ?>modules/technicians/index.php" class="<?= navActive($currentDir, 'technicians') ?>">
          <?= icon('suppliers') ?> Technicians
        </a>
        <?php endif; ?>
      </nav>
      <?php endif; ?>

      <?php if (hasPermission('orders.view') || hasPermission('customers.view')): ?>
      <div class="nav-group-label">Sales</div>
      <nav class="sidebar-nav">
        <?php if (hasPermission('orders.view')): ?>
        <a href="<?= BASE_URL ?>modules/orders/index.php" class="<?= navActive($currentDir, 'orders') ?>">
          <?= icon('orders') ?> Orders
        </a>
        <?php endif; ?>
        <?php if (hasPermission('customers.view')): ?>
        <a href="<?= BASE_URL ?>modules/customers/index.php" class="<?= navActive($currentDir, 'customers') ?>">
          <?= icon('customers') ?> Customers
        </a>
        <?php endif; ?>
      </nav>
      <?php endif; ?>

      <?php if (hasPermission('purchasing.view') || hasPermission('suppliers.view')): ?>
      <div class="nav-group-label">Purchasing</div>
      <nav class="sidebar-nav">
        <?php if (hasPermission('purchasing.view')): ?>
        <a href="<?= BASE_URL ?>modules/purchasing/index.php" class="<?= navActive($currentDir, 'purchasing') ?>">
          <?= icon('purchasing') ?> Purchase orders
        </a>
        <?php endif; ?>
        <?php if (hasPermission('suppliers.view')): ?>
        <a href="<?= BASE_URL ?>modules/suppliers/index.php" class="<?= navActive($currentDir, 'suppliers') ?>">
          <?= icon('suppliers') ?> Suppliers
        </a>
        <?php endif; ?>
      </nav>
      <?php endif; ?>

      <?php if (hasPermission('reports.view') || hasPermission('activity.view')): ?>
      <div class="nav-group-label">Insights</div>
      <nav class="sidebar-nav">
        <?php if (hasPermission('reports.view')): ?>
        <a href="<?= BASE_URL ?>modules/reports/index.php" class="<?= navActive($currentDir, 'reports') ?>">
          <?= icon('reports') ?> Reports
        </a>
        <?php endif; ?>
        <?php if (hasPermission('activity.view')): ?>
        <a href="<?= BASE_URL ?>modules/activity/index.php" class="<?= navActive($currentDir, 'activity') ?>">
          <?= icon('activity') ?> Activity log
        </a>
        <?php endif; ?>
      </nav>
      <?php endif; ?>

      <?php if (hasPermission('users.manage')): ?>
      <div class="nav-group-label">Administration</div>
      <nav class="sidebar-nav">
        <?php if (hasPermission('branches.manage')): ?>
        <a href="<?= BASE_URL ?>modules/branches/index.php" class="<?= navActive($currentDir, 'branches') ?>">
          <?= icon('suppliers') ?> Branches
        </a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>modules/users/index.php" class="<?= navActive($currentDir, 'users') ?>">
          <?= icon('users') ?> Users &amp; roles
        </a>
      </nav>
      <?php endif; ?>
    </div>

    <div class="sidebar-footer">
      <div class="user-chip">
        <div class="user-avatar"><?= strtoupper(substr($user['full_name'], 0, 1)) ?></div>
        <div>
          <div class="user-name"><?= clean($user['full_name']) ?></div>
          <div class="user-role"><?= clean($user['role_name']) ?></div>
        </div>
      </div>
      <a href="<?= BASE_URL ?>auth/logout.php" class="logout-link"><?= icon('logout', 16) ?> Log out</a>
    </div>
  </aside>

  <div class="main-area">
    <header class="topbar">
      <button id="sidebarToggle" class="icon-btn" aria-label="Toggle menu"><?= icon('menu', 20) ?></button>

      <div class="page-title-wrap">
        <?php if ($currentDir !== 'dashboard'): ?>
          <div class="breadcrumb"><?= ucfirst(str_replace('-', ' ', $currentDir)) ?></div>
        <?php endif; ?>
        <h1 class="page-title"><?= isset($pageTitle) ? clean($pageTitle) : 'Dashboard' ?></h1>
      </div>

      <form class="topbar-search" method="get" action="<?= BASE_URL ?>modules/inventory/index.php">
        <?= icon('search', 16) ?>
        <input type="text" name="q" placeholder="Search products by name or SKU...">
      </form>

      <?php if (isMultiBranchUser()): ?>
      <div class="dropdown">
        <button type="button" class="topbar-icon-btn dropdown-trigger" style="width:auto; padding:6px 10px; gap:6px; display:flex; align-items:center; border:1px solid var(--border); border-radius:var(--radius-sm);" aria-label="Switch branch">
          <?= icon('suppliers', 15) ?>
          <span class="small" style="font-weight:600;"><?= clean(getBranchLabel($activeBranchId, $pdo)) ?></span>
          <?= icon('chevron', 13) ?>
        </button>
        <div class="dropdown-panel">
          <div class="dropdown-header">Switch branch</div>
          <div class="dropdown-list">
            <a href="<?= BASE_URL ?>switch_branch.php?branch=all" class="dropdown-item" style="<?= $activeBranchId === null ? 'font-weight:600; color:var(--accent);' : '' ?>">All branches</a>
            <?php foreach ($branchesByTown as $townName => $townBranches): ?>
              <div class="muted small" style="padding:8px 16px 2px; font-weight:600;"><?= clean($townName) ?></div>
              <?php foreach ($townBranches as $b): ?>
                <a href="<?= BASE_URL ?>switch_branch.php?branch=<?= $b['id'] ?>" class="dropdown-item" style="<?= $activeBranchId == $b['id'] ? 'font-weight:600; color:var(--accent);' : '' ?>"><?= clean($b['name']) ?></a>
              <?php endforeach; ?>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <?php else: ?>
        <div class="small muted" style="white-space:nowrap;"><?= icon('suppliers', 14) ?> <?= clean(getBranchLabel($activeBranchId, $pdo)) ?></div>
      <?php endif; ?>

      <div class="topbar-actions">
        <?php if (hasPermission('inventory.view')): ?>
        <div class="dropdown">
          <button type="button" class="topbar-icon-btn dropdown-trigger" aria-label="Notifications">
            <?= icon('bell', 19) ?>
            <?php if ($notifCount > 0): ?><span class="dot-badge"></span><?php endif; ?>
          </button>
          <div class="dropdown-panel">
            <div class="dropdown-header">Low stock alerts</div>
            <div class="dropdown-list">
              <?php if ($notifItems): ?>
                <?php foreach ($notifItems as $item): ?>
                  <a href="<?= BASE_URL ?>modules/inventory/edit.php?id=<?= (int)$item['id'] ?>" class="dropdown-item">
                    <div class="dropdown-item-title"><?= clean($item['name']) ?></div>
                    <div class="muted small mono"><?= clean($item['sku']) ?> · <?= (int)$item['quantity'] ?> left (reorder at <?= (int)$item['reorder_level'] ?>)<?= isset($item['branch_name']) ? ' · ' . clean($item['branch_name']) : '' ?></div>
                  </a>
                <?php endforeach; ?>
              <?php else: ?>
                <div class="dropdown-empty">Every product is above its reorder level.</div>
              <?php endif; ?>
            </div>
            <?php if ($notifItems): ?>
            <div class="dropdown-footer">
              <a href="<?= BASE_URL ?>modules/inventory/index.php" class="link small">View all inventory</a>
            </div>
            <?php endif; ?>
          </div>
        </div>
        <?php endif; ?>

        <div class="dropdown">
          <button type="button" class="user-menu-trigger dropdown-trigger">
            <div class="user-avatar"><?= strtoupper(substr($user['full_name'], 0, 1)) ?></div>
            <?= icon('chevron', 14) ?>
          </button>
          <div class="dropdown-panel user-panel">
            <div class="dropdown-header"><?= clean($user['full_name']) ?><br><span class="muted" style="font-weight:400;"><?= clean($user['role_name']) ?></span></div>
            <a href="<?= BASE_URL ?>account.php" class="dropdown-item"><?= icon('settings', 16) ?> Account settings</a>
            <a href="<?= BASE_URL ?>auth/logout.php" class="dropdown-item"><?= icon('logout', 16) ?> Log out</a>
          </div>
        </div>
      </div>
    </header>

    <main class="content">
      <?php if ($flash): ?>
        <div id="flashData" data-type="<?= clean($flash['type']) ?>" data-message="<?= clean($flash['message']) ?>" style="display:none;"></div>
      <?php endif; ?>
