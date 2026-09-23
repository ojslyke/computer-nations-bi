<?php
require_once __DIR__ . '/config/config.php';
http_response_code(404);
$pageTitle = 'Page not found';
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
<title>Page not found — <?= SITE_NAME ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
</head>
<body>
<div class="error-screen">
  <div class="error-card">
    <h1>404</h1>
    <p>That page doesn't exist.</p>
    <p class="muted">It may have moved, or the link might be out of date.</p>
    <a href="<?= BASE_URL ?><?= isLoggedIn() ? 'dashboard/index.php' : 'auth/login.php' ?>" class="btn btn-primary"><?= isLoggedIn() ? 'Back to dashboard' : 'Go to login' ?></a>
  </div>
</div>
</body>
</html>
