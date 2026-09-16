<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/rbac.php';
require_once __DIR__ . '/includes/branch_context.php';
requireLogin();

setCurrentBranch($_GET['branch'] ?? null, $pdo);

$back = $_SERVER['HTTP_REFERER'] ?? (BASE_URL . 'dashboard/index.php');
header('Location: ' . $back);
exit;
