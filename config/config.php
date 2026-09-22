<?php
/**
 * Global site configuration — included at the top of every page.
 */
$isHttpsRequest = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

if (session_status() === PHP_SESSION_NONE) {
    // On a real deployment (HTTPS, behind Railway's proxy) mark the session
    // cookie Secure so it's never sent over plain HTTP; harmless locally.
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $isHttpsRequest,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

date_default_timezone_set('Africa/Douala');

define('SITE_NAME', 'Computer Nations');

// BASE_URL is detected automatically so the same code works unchanged on
// XAMPP (served under /computer-nations-bi/) and on a host like Railway
// (served at the domain root, behind a TLS-terminating proxy). Set
// APP_BASE_URL as an environment variable to override this if ever needed.
if (getenv('APP_BASE_URL')) {
    define('BASE_URL', rtrim(getenv('APP_BASE_URL'), '/') . '/');
} else {
    $scheme = $isHttpsRequest ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    // config.php always lives at <project_root>/config/config.php, so the
    // project root is one level up from here — compare that against the
    // document root to work out whether the app sits in a subfolder.
    $projectRoot = str_replace('\\', '/', dirname(__DIR__));
    $documentRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    $basePath = '';
    if ($documentRoot !== '' && str_starts_with($projectRoot, $documentRoot)) {
        $basePath = trim(substr($projectRoot, strlen($documentRoot)), '/');
    }
    define('BASE_URL', $scheme . '://' . $host . ($basePath !== '' ? '/' . $basePath : '') . '/');
}

// Where uploaded product images are stored (must be writable)
define('UPLOAD_DIR', __DIR__ . '/../uploads/products/');
define('UPLOAD_URL', BASE_URL . 'uploads/products/');

// Working hours enforcement (West African Time — the timezone set above).
// Only the Admin role is exempt; every other role can only log in and stay
// logged in between these hours.
define('WORKING_HOURS_START', 6);   // 6am
define('WORKING_HOURS_END', 18);    // 6pm

// The "From" address used for password-reset emails. XAMPP's PHP does not
// send mail out of the box — see the README for how to configure sendmail
// or SMTP so these actually deliver.
define('MAIL_FROM_ADDRESS', 'no-reply@computernations.local');
define('MAIL_FROM_NAME', SITE_NAME);

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/branch_context.php';
