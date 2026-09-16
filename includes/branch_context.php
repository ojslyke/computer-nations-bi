<?php
/**
 * Branch context
 * ---------------
 * A user with branch_id set on their account is pinned to that one
 * branch everywhere (their home branch) — typical for branch staff.
 * A user with branch_id = NULL (Admin/Manager/HQ roles) can see and
 * switch between every branch, including an "All branches" aggregate
 * view for read-only pages like the dashboard and reports.
 */

function userHomeBranchId() {
    return $_SESSION['user']['branch_id'] ?? null;
}

function isMultiBranchUser() {
    return userHomeBranchId() === null;
}

/** The branch currently being viewed/worked in. NULL means "All branches" (HQ users only). */
function currentBranchId() {
    if (!isMultiBranchUser()) {
        return userHomeBranchId();
    }
    return array_key_exists('current_branch_id', $_SESSION) ? $_SESSION['current_branch_id'] : null;
}

function setCurrentBranch($branchId, $pdo) {
    if (!isMultiBranchUser()) return; // restricted users can't switch
    if ($branchId === null || $branchId === '' || $branchId === 'all') {
        $_SESSION['current_branch_id'] = null;
        return;
    }
    $stmt = $pdo->prepare("SELECT id FROM branches WHERE id = ? AND is_active = 1");
    $stmt->execute([(int)$branchId]);
    if ($stmt->fetch()) {
        $_SESSION['current_branch_id'] = (int)$branchId;
    }
}

/**
 * For transactional pages (adjusting stock, creating an order, receiving
 * a PO, dispatching a transfer) that need exactly one concrete branch —
 * "All branches" isn't a valid target for an action. Falls back to the
 * user's first accessible branch and tells the caller so it can inform
 * the user, rather than silently guessing.
 */
function requireActiveBranch($pdo) {
    $id = currentBranchId();
    if ($id !== null) return $id;

    $first = $pdo->query("SELECT id FROM branches WHERE is_active = 1 ORDER BY id LIMIT 1")->fetch();
    return $first ? (int)$first['id'] : null;
}

function getBranchName($branchId, $pdo) {
    static $cache = [];
    if ($branchId === null) return 'All branches';
    if (!isset($cache[$branchId])) {
        $stmt = $pdo->prepare("SELECT name FROM branches WHERE id = ?");
        $stmt->execute([$branchId]);
        $cache[$branchId] = $stmt->fetch()['name'] ?? 'Unknown branch';
    }
    return $cache[$branchId];
}

/** "Branch name — Town" label, useful once a town has more than one branch */
function getBranchLabel($branchId, $pdo) {
    static $cache = [];
    if ($branchId === null) return 'All branches';
    if (!isset($cache[$branchId])) {
        $stmt = $pdo->prepare("SELECT b.name, t.name AS town FROM branches b JOIN towns t ON t.id = b.town_id WHERE b.id = ?");
        $stmt->execute([$branchId]);
        $row = $stmt->fetch();
        $cache[$branchId] = $row ? $row['name'] . ' — ' . $row['town'] : 'Unknown branch';
    }
    return $cache[$branchId];
}

/** Active branches grouped by town: ['Douala' => [branch rows...], 'Yaoundé' => [...]] */
function listBranchesByTown($pdo, $activeOnly = true) {
    $sql = "SELECT b.id, b.name, t.id AS town_id, t.name AS town_name
            FROM branches b JOIN towns t ON t.id = b.town_id" .
           ($activeOnly ? " WHERE b.is_active = 1" : "") .
           " ORDER BY t.name, b.name";
    $rows = $pdo->query($sql)->fetchAll();
    $grouped = [];
    foreach ($rows as $r) {
        $grouped[$r['town_name']][] = $r;
    }
    return $grouped;
}

/**
 * For read-only stock viewing ONLY (e.g. the Inventory list) — every user,
 * even one pinned to a single branch, can look up stock at any branch via
 * ?view_branch=ID or ?view_branch=all. This never changes their real
 * session branch context, so every action page (orders, adjustments,
 * receiving) still strictly uses currentBranchId()/requireActiveBranch().
 */
function resolveViewBranchId($pdo) {
    if (!isset($_GET['view_branch'])) {
        return currentBranchId();
    }
    $requested = $_GET['view_branch'];
    if ($requested === 'all') {
        return null;
    }
    $stmt = $pdo->prepare("SELECT id FROM branches WHERE id = ? AND is_active = 1");
    $stmt->execute([(int)$requested]);
    return $stmt->fetch() ? (int)$requested : currentBranchId();
}
