<?php
/**
 * Database connection (PDO)
 *
 * On XAMPP, these four constants just default to the usual local setup.
 * On a host like Railway that injects MySQL connection details as
 * environment variables (MYSQLHOST/MYSQLPORT/MYSQLUSER/MYSQLPASSWORD/
 * MYSQLDATABASE), those take over automatically — nothing to edit.
 */
define('DB_HOST', getenv('MYSQLHOST') ?: 'localhost');
define('DB_PORT', getenv('MYSQLPORT') ?: '3306');
define('DB_NAME', getenv('MYSQLDATABASE') ?: 'computer_nations_bi');
define('DB_USER', getenv('MYSQLUSER') ?: 'root');
define('DB_PASS', getenv('MYSQLPASSWORD') !== false ? getenv('MYSQLPASSWORD') : '');

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die('Database connection failed. Check config/database.php — ' . htmlspecialchars($e->getMessage()));
}
