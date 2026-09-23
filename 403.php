<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
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
<title>Access denied — <?= SITE_NAME ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
</head>
<body>
<div class="error-screen">
  <div class="error-card">
    <h1>403</h1>
    <p>Your account doesn't have permission to view this page.</p>
    <p class="muted">If you believe this is a mistake, ask an administrator to review your role's privileges.</p>
    <a href="<?= BASE_URL ?>dashboard/index.php" class="btn btn-primary">Back to dashboard</a>
  </div>
</div>
</body>
</html>
