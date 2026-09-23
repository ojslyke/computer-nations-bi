<?php
/**
 * Global site configuration — included at the top of every page.
 */

// APP_ENV=production is set explicitly in the Docker image (see Dockerfile);
// local XAMPP runs have no such variable, so they default to 'local'.
define('APP_ENV', getenv('APP_ENV') ?: 'local');
define('IS_PRODUCTION', APP_ENV === 'production');

if (IS_PRODUCTION) {
    // Never leak stack traces / file paths to a visitor — log the real
    // error server-side instead and show a generic page.
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL);
    ini_set('log_errors', '1');

    set_exception_handler(function ($e) {
        error_log('Uncaught exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        http_response_code(500);
        require __DIR__ . '/../500.php';
        exit;
    });
    set_error_handler(function ($severity, $message, $file, $line) {
        if (!(error_reporting() & $severity)) return false;
        error_log("PHP error [$severity]: $message in $file:$line");
        return true; // suppress default HTML output of the error itself
    });
} else {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}

// A conservative set of security headers on every response. The CSP allows
// the specific external hosts this app actually loads from (Google Fonts,
// the Chart.js CDN) and 'unsafe-inline' for script/style — the app uses
// inline <script> blocks throughout rather than a bundler, so a stricter
// policy would break real functionality; this still blocks loading from
// anywhere else, which is the main point of a CSP.
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:; connect-src 'self'");
if (IS_PRODUCTION) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

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
