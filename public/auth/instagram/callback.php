<?php
$code = isset($_GET['code']) ? trim($_GET['code']) : '';
$state = isset($_GET['state']) ? trim($_GET['state']) : '';
$error = isset($_GET['error']) ? trim($_GET['error']) : '';
$errorDescription = isset($_GET['error_description']) ? trim($_GET['error_description']) : '';
$dashboardUrl = 'https://skipbyte.id/login-instagram.php';
$redirectUri = 'https://skipbyte.id/auth/instagram/callback.php';
$continueUrl = $code
  ? $dashboardUrl . '?instagram_code=' . rawurlencode($code) . ($state ? '&instagram_state=' . rawurlencode($state) : '')
  : $dashboardUrl;

if ($code && !$error) {
  header('Location: ' . $continueUrl, true, 302);
  exit;
}

function e($value) {
  return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Instagram Authorization</title>
  <style>
    body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #f5f7fb; color: #17202a; font-family: Arial, sans-serif; line-height: 1.5; }
    .box { width: min(820px, calc(100% - 32px)); padding: 26px; border: 1px solid #d9e2ea; border-radius: 8px; background: #fff; box-shadow: 0 18px 46px rgba(23, 32, 42, 0.12); }
    code { font-family: Consolas, monospace; font-size: 14px; }
    a { color: #148f86; font-weight: 700; }
  </style>
</head>
<body>
  <div class="box">
    <h1>Instagram Authorization</h1>
    <?php if ($error): ?>
      <p>Instagram authorization gagal.</p>
      <p><strong>Error:</strong> <code><?= e($error) ?></code></p>
      <?php if ($errorDescription): ?><p><strong>Detail:</strong> <?= e($errorDescription) ?></p><?php endif; ?>
      <p><a href="<?= e($dashboardUrl) ?>">Kembali ke Instagram Login</a></p>
    <?php elseif ($code): ?>
      <p>Authorization code diterima.</p>
      <p><a href="<?= e($continueUrl) ?>">Lanjutkan token exchange</a></p>
    <?php else: ?>
      <p>Callback aktif. Daftarkan URL ini di Meta Dashboard:</p>
      <p><code><?= e($redirectUri) ?></code></p>
      <p><a href="<?= e($dashboardUrl) ?>">Buka Instagram Login</a></p>
    <?php endif; ?>
  </div>
</body>
</html>
