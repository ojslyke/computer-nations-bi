<?php
require_once __DIR__ . '/config/config.php';

if (isLoggedIn()) {
    redirect('dashboard/index.php');
} else {
    redirect('auth/login.php');
}
