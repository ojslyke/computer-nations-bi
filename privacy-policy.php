<?php
require_once __DIR__ . '/config/config.php';
$pageTitle = 'Privacy Policy';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<script>
(function () {
  try {
    var saved = localStorage.getItem('cn-theme');
    if (saved === 'dark' || saved === 'light') {
      document.documentElement.setAttribute('data-theme', saved);
    }
  } catch (e) {}
})();
</script>
<title>Privacy Policy — <?= SITE_NAME ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
</head>
<body>
<div class="legal-page">
  <div class="legal-card">
    <h1>Privacy Policy</h1>
    <p class="muted">Last updated: <?= date('d F Y') ?></p>

    <h2>What this system stores</h2>
    <p><?= SITE_NAME ?> BI is an internal business management tool. It stores information needed to run day-to-day operations: staff accounts (name, email, phone, role), branch and stock records, customer and supplier contact details, orders, purchases, and an activity log of who did what.</p>

    <h2>Who can see what</h2>
    <p>Access is controlled by role. Staff can only see and act on the data their role and branch assignment permit. Administrators can see activity across the system for oversight and support.</p>

    <h2>How data is protected</h2>
    <p>Passwords are hashed and never stored in plain text. Connections to this site are encrypted. Access to sensitive actions (managing users, viewing financials, adjusting stock) is restricted by role, enforced on the server — not just hidden in the interface.</p>

    <h2>Data retention</h2>
    <p>Operational records (orders, stock movements, activity logs) are kept for as long as the business needs them for record-keeping and audit purposes. An administrator can remove an account or a data record on request.</p>

    <h2>Contact</h2>
    <p>Questions about data handling in this system should go to your system administrator.</p>

    <a href="<?= BASE_URL ?>auth/login.php" class="btn btn-secondary">Back to login</a>
  </div>
</div>
</body>
</html>
