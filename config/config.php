<?php
/**
 * Global site configuration — included at the top of every page.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Africa/Douala');

define('SITE_NAME', 'Computer Nations');

// BASE_URL: change 'computer-nations-bi' if you rename the project folder
define('BASE_URL', 'http://localhost/computer-nations-bi/');

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
