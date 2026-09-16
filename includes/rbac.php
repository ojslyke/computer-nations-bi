<?php
/**
 * Role-Based Access Control
 * -------------------------
 * On login we load the user's full permission list (an array of slugs like
 * 'inventory.edit') into $_SESSION['permissions']. Every protected page then
 * just asks hasPermission('slug') — nobody hardcodes role names in modules.
 */

function loadPermissionsForUser($userId, $pdo) {
    $stmt = $pdo->prepare(
        "SELECT p.slug
         FROM permissions p
         JOIN role_permissions rp ON rp.permission_id = p.id
         JOIN users u ON u.role_id = rp.role_id
         WHERE u.id = ?"
    );
    $stmt->execute([$userId]);
    return array_column($stmt->fetchAll(), 'slug');
}

function hasPermission($slug) {
    return in_array($slug, $_SESSION['permissions'] ?? [], true);
}

/** Call at the top of every page that requires a logged-in user */
function requireLogin() {
    if (!isLoggedIn()) {
        setFlash('error', 'Please log in to continue.');
        redirect('auth/login.php');
    }
    enforceWorkingHours();
}

/**
 * Every role except Admin is signed out automatically the moment the
 * clock passes working hours — even mid-session — so nobody keeps
 * working (or the system keeps being accessible) after 6pm WAT.
 */
function enforceWorkingHours() {
    $roleName = $_SESSION['user']['role_name'] ?? '';
    if ($roleName === 'Admin') return;
    if (isWithinWorkingHours()) return;

    unset($_SESSION['user_id'], $_SESSION['user'], $_SESSION['permissions'], $_SESSION['current_branch_id']);
    setFlash('error', workingHoursMessage());
    redirect('auth/login.php');
}

/** Call at the top of every page that requires a specific privilege */
function requirePermission($slug) {
    requireLogin();
    if (!hasPermission($slug)) {
        http_response_code(403);
        require __DIR__ . '/../403.php';
        exit;
    }
}
