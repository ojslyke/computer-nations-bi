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

/**
 * Simple DB-backed rate limiter for abuse-prone endpoints (login, password
 * reset). Returns true if the identifier (usually the requester's IP) is
 * still within the allowed number of attempts for this bucket in the
 * trailing window; false if they've hit the limit and should be blocked.
 */
function rateLimitCheck($pdo, $bucket, $identifier, $maxAttempts, $windowSeconds) {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) c FROM rate_limit_attempts WHERE bucket = ? AND identifier = ? AND created_at > (NOW() - INTERVAL ? SECOND)"
    );
    $stmt->execute([$bucket, $identifier, $windowSeconds]);
    return (int)$stmt->fetch()['c'] < $maxAttempts;
}

function rateLimitRecord($pdo, $bucket, $identifier) {
    $pdo->prepare("INSERT INTO rate_limit_attempts (bucket, identifier) VALUES (?, ?)")
        ->execute([$bucket, $identifier]);
    // Opportunistic cleanup so this table never grows unbounded — cheap,
    // and fine to skip most of the time (1 in ~50 requests).
    if (random_int(1, 50) === 1) {
        $pdo->exec("DELETE FROM rate_limit_attempts WHERE created_at < (NOW() - INTERVAL 1 DAY)");
    }
}

function requestIp() {
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

/**
 * Cache the result of an expensive (usually aggregate) query in APCu for
 * a short TTL — enough to take repeated dashboard loads off the database
 * without the numbers ever going stale for long. Falls back to just
 * running the callback every time if APCu isn't available (e.g. locally
 * without the extension), so nothing breaks without it.
 */
function cacheRemember($key, $ttlSeconds, callable $callback) {
    if (!function_exists('apcu_fetch')) {
        return $callback();
    }
    $found = false;
    $value = apcu_fetch($key, $found);
    if ($found) {
        return $value;
    }
    $value = $callback();
    apcu_store($key, $value, $ttlSeconds);
    return $value;
}

/**
 * Reads a .xlsx or .csv file into a plain array of rows (each row itself
 * an array of cell strings, 0-indexed by column, gaps preserved as '').
 * No external library — an .xlsx is just a zip of XML, which PHP's
 * bundled ZipArchive/SimpleXML already read natively.
 * Returns false if the file couldn't be parsed at all.
 */
function parseSpreadsheetFile($filePath, $originalFilename) {
    $ext = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
    if ($ext === 'csv') {
        return parseCsvToRows($filePath);
    }
    if ($ext === 'xlsx') {
        return parseXlsxToRows($filePath);
    }
    return false;
}

function parseCsvToRows($filePath) {
    $rows = [];
    $handle = fopen($filePath, 'r');
    if (!$handle) return false;
    while (($row = fgetcsv($handle)) !== false) {
        $rows[] = $row;
    }
    fclose($handle);
    return $rows;
}

function parseXlsxToRows($filePath) {
    if (!class_exists('ZipArchive')) return false;
    $zip = new ZipArchive();
    if ($zip->open($filePath) !== true) return false;

    $sharedStrings = [];
    $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($sharedXml !== false) {
        $xml = @simplexml_load_string($sharedXml);
        if ($xml !== false) {
            foreach ($xml->si as $si) {
                if (isset($si->t)) {
                    $sharedStrings[] = (string)$si->t;
                } else {
                    $text = '';
                    foreach ($si->r as $r) { $text .= (string)$r->t; }
                    $sharedStrings[] = $text;
                }
            }
        }
    }

    // Find the first worksheet — try the conventional path, then fall back
    // to scanning the archive for any worksheet if that exact name isn't used.
    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    if ($sheetXml === false) {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (preg_match('#^xl/worksheets/sheet\d+\.xml$#', $name)) {
                $sheetXml = $zip->getFromName($name);
                break;
            }
        }
    }
    $zip->close();
    if ($sheetXml === false) return false;

    $xml = @simplexml_load_string($sheetXml);
    if ($xml === false || !isset($xml->sheetData)) return false;

    $rows = [];
    foreach ($xml->sheetData->row as $row) {
        $rowData = [];
        foreach ($row->c as $cell) {
            $ref = (string)$cell['r'];
            preg_match('/([A-Z]+)(\d+)/', $ref, $m);
            $colLetters = $m[1] ?? '';
            $colIndex = 0;
            for ($i = 0; $i < strlen($colLetters); $i++) {
                $colIndex = $colIndex * 26 + (ord($colLetters[$i]) - ord('A') + 1);
            }
            $colIndex--;
            if ($colIndex < 0) continue;

            $type = (string)$cell['t'];
            $value = isset($cell->v) ? (string)$cell->v : '';
            if ($type === 's') {
                $value = $sharedStrings[(int)$value] ?? '';
            } elseif ($type === 'inlineStr' && isset($cell->is->t)) {
                $value = (string)$cell->is->t;
            }
            $rowData[$colIndex] = $value;
        }
        if ($rowData) {
            $maxCol = max(array_keys($rowData));
            $fullRow = [];
            for ($c = 0; $c <= $maxCol; $c++) {
                $fullRow[] = $rowData[$c] ?? '';
            }
            $rows[] = $fullRow;
        } else {
            $rows[] = [];
        }
    }
    return $rows;
}

/**
 * Resize (if needed) and re-compress an uploaded product photo before
 * saving it — keeps product images from bloating page weight. Caller has
 * already verified $tmpPath is a genuine image via getimagesize(); $type
 * is the IMAGETYPE_* constant it returned.
 */
function compressAndSaveProductImage($tmpPath, $type, $destPath, $maxDimension = 1000) {
    $image = match ($type) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($tmpPath),
        IMAGETYPE_PNG  => @imagecreatefrompng($tmpPath),
        IMAGETYPE_WEBP => @imagecreatefromwebp($tmpPath),
        default        => false,
    };
    if (!$image) {
        return false; // fall back to a plain copy if GD couldn't decode it
    }

    $width = imagesx($image);
    $height = imagesy($image);
    if ($width > $maxDimension || $height > $maxDimension) {
        $ratio = min($maxDimension / $width, $maxDimension / $height);
        $newWidth = (int)round($width * $ratio);
        $newHeight = (int)round($height * $ratio);
        $resized = imagecreatetruecolor($newWidth, $newHeight);
        if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_WEBP) {
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
        }
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($image);
        $image = $resized;
    }

    $saved = match ($type) {
        IMAGETYPE_JPEG => imagejpeg($image, $destPath, 82),
        IMAGETYPE_PNG  => imagepng($image, $destPath, 7),
        IMAGETYPE_WEBP => imagewebp($image, $destPath, 82),
        default        => false,
    };
    imagedestroy($image);
    return $saved;
}
