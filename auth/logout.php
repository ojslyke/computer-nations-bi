<?php
require_once __DIR__ . '/../config/config.php';

logActivity('logout', 'User logged out');

$_SESSION = [];
session_destroy();

header('Location: ' . BASE_URL . 'auth/login.php');
exit;
