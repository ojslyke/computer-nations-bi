<?php
/**
 * General-purpose helper functions used across the whole app.
 */

function clean($value) {
    return htmlspecialchars(trim($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function redirect($path) {
    header('Location: ' . BASE_URL . ltrim($path, '/'));
    exit;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function currentUser() {
    return $_SESSION['user'] ?? null;
}

/** Store a one-time message to show after a redirect (login errors, "saved" banners, etc.) */
function setFlash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash() {
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/** Simple CSRF protection for forms */
function csrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfCheck() {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        http_response_code(403);
        die('Invalid or expired form submission. Go back and try again.');
    }
}

/** Record an action in activity_logs for the audit trail */
function logActivity($action, $description = '', $userId = null) {
    global $pdo;
    $userId = $userId ?? ($_SESSION['user_id'] ?? null);
    if ($userId === null) return; // no actor to attribute this to (e.g. before login)
    $stmt = $pdo->prepare(
        "INSERT INTO activity_logs (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)"
    );
    $stmt->execute([$userId, $action, $description, $_SERVER['REMOTE_ADDR'] ?? '']);
}

function formatMoney($amount) {
    return number_format((float)$amount, 0) . ' XAF';
}

function generateOrderNumber() {
    return 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
}

function generatePoNumber() {
    return 'PO-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
}

function generateSavNumber() {
    return 'SAV-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
}

/** Human-friendly relative time for the activity log ("5 minutes ago") */
function timeAgo($datetime) {
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . ' min ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hr ago';
    if ($diff < 604800) return floor($diff / 86400) . ' day' . (floor($diff / 86400) > 1 ? 's' : '') . ' ago';
    return date('d M Y', strtotime($datetime));
}

/** Output a PHP array of rows as a downloadable CSV and stop execution */
function exportCsv($filename, array $headers, array $rows) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fputcsv($out, $headers);
    foreach ($rows as $row) {
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}

/**
 * Send a plain-text email via PHP's built-in mail(). Returns true/false —
 * callers should not surface the raw result to the person for security-
 * sensitive flows like password reset (avoids confirming account details).
 * Requires sendmail/SMTP to be configured in php.ini; see the README.
 */
function sendAppEmail($to, $subject, $body) {
    $headers = "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM_ADDRESS . ">\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    return @mail($to, $subject, $body, $headers);
}

/**
 * Working-hours gate: every role except Admin can only use the system
 * between WORKING_HOURS_START and WORKING_HOURS_END, server local time
 * (West African Time, set via date_default_timezone_set()).
 */
function isWithinWorkingHours() {
    $hour = (int)date('G');
    return $hour >= WORKING_HOURS_START && $hour < WORKING_HOURS_END;
}

function workingHoursMessage() {
    return sprintf(
        'Access is only available between %s and %s (West African Time).',
        date('g:i A', mktime(WORKING_HOURS_START, 0, 0)),
        date('g:i A', mktime(WORKING_HOURS_END, 0, 0))
    );
}
