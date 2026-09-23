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
<title>Something went wrong — <?= defined('SITE_NAME') ? SITE_NAME : 'Computer Nations' ?></title>
<link rel="stylesheet" href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>assets/css/style.css">
</head>
<body>
<div class="error-screen">
  <div class="error-card">
    <h1>500</h1>
    <p>Something went wrong on our end.</p>
    <p class="muted">The error's been logged. Try again in a moment, or contact an administrator if it keeps happening.</p>
    <a href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>dashboard/index.php" class="btn btn-primary">Back to dashboard</a>
  </div>
</div>
</body>
</html>
