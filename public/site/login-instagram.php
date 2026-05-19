<?php
session_start();

$defaultRedirectUri = 'https://skipbyte.id/auth/instagram/callback.php';
$config = [
  'client_id' => getenv('INSTAGRAM_APP_ID') ?: getenv('META_APP_ID') ?: '',
  'client_secret' => getenv('INSTAGRAM_APP_SECRET') ?: getenv('META_APP_SECRET') ?: '',
  'redirect_uri' => getenv('INSTAGRAM_REDIRECT_URI') ?: $defaultRedirectUri,
  'scopes' => getenv('INSTAGRAM_AUTH_SCOPES') ?: 'instagram_business_basic,instagram_business_content_publish',
];

$configFile = dirname(__DIR__) . '/config/instagram.php';
if (is_file($configFile)) {
  $loadedConfig = include $configFile;
  if (is_array($loadedConfig)) $config = array_merge($config, $loadedConfig);
}

$message = '';
$error = '';
$token = null;

function e($value) {
  return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function mask_value($value) {
  $text = (string) $value;
  if ($text === '') return 'empty';
  return substr($text, 0, 6) . '...' . substr($text, -4);
}

function build_auth_url($config) {
  $params = [
    'client_id' => $config['client_id'],
    'redirect_uri' => $config['redirect_uri'],
    'response_type' => 'code',
    'scope' => $config['scopes'],
    'state' => bin2hex(random_bytes(16)),
  ];
  $_SESSION['instagram_oauth_state'] = $params['state'];
  return 'https://www.instagram.com/oauth/authorize?' . http_build_query($params);
}

function curl_form($url, $fields) {
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query($fields),
    CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 60,
  ]);
  $raw = curl_exec($ch);
  $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $curlError = curl_error($ch);
  curl_close($ch);
  if ($raw === false) throw new Exception('Instagram OAuth curl error: ' . $curlError);
  $data = json_decode($raw, true);
  if (!is_array($data)) throw new Exception('Instagram OAuth returned non JSON response: ' . substr($raw, 0, 300));
  if ($status < 200 || $status >= 300) {
    $detail = $data['error_message'] ?? $data['error_description'] ?? $data['message'] ?? json_encode($data);
    throw new Exception('Instagram OAuth failed: ' . $detail);
  }
  return $data;
}

function curl_json_get($url, $params) {
  $fullUrl = $url . '?' . http_build_query($params);
  $ch = curl_init($fullUrl);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 60,
  ]);
  $raw = curl_exec($ch);
  $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $curlError = curl_error($ch);
  curl_close($ch);
  if ($raw === false) throw new Exception('Instagram Graph curl error: ' . $curlError);
  $data = json_decode($raw, true);
  if (!is_array($data)) throw new Exception('Instagram Graph returned non JSON response.');
  if ($status < 200 || $status >= 300) {
    $detail = $data['error']['message'] ?? $data['error_message'] ?? json_encode($data);
    throw new Exception('Instagram Graph failed: ' . $detail);
  }
  return $data;
}

function token_export_text($token) {
  if (!is_array($token)) return '';
  $pairs = [
    'INSTAGRAM_IG_USER_ID' => $token['user_id'] ?? $token['id'] ?? '',
    'INSTAGRAM_ACCESS_TOKEN' => $token['access_token'] ?? '',
  ];
  $lines = [];
  foreach ($pairs as $key => $value) {
    if ($value !== '') $lines[] = $key . '=' . $value;
  }
  return implode("\n", $lines);
}

if (isset($_GET['instagram_code'])) {
  try {
    if (!$config['client_id']) throw new Exception('INSTAGRAM_APP_ID belum dikonfigurasi.');
    if (!$config['client_secret']) throw new Exception('INSTAGRAM_APP_SECRET belum dikonfigurasi.');

    $code = trim($_GET['instagram_code']);
    $state = trim($_GET['instagram_state'] ?? '');
    if ($state && isset($_SESSION['instagram_oauth_state']) && !hash_equals($_SESSION['instagram_oauth_state'], $state)) {
      throw new Exception('State OAuth tidak cocok. Mulai login ulang.');
    }

    $shortToken = curl_form('https://api.instagram.com/oauth/access_token', [
      'client_id' => $config['client_id'],
      'client_secret' => $config['client_secret'],
      'grant_type' => 'authorization_code',
      'redirect_uri' => $config['redirect_uri'],
      'code' => $code,
    ]);

    $longToken = curl_json_get('https://graph.instagram.com/access_token', [
      'grant_type' => 'ig_exchange_token',
      'client_secret' => $config['client_secret'],
      'access_token' => $shortToken['access_token'] ?? '',
    ]);

    $profile = curl_json_get('https://graph.instagram.com/me', [
      'fields' => 'id,username,account_type',
      'access_token' => $longToken['access_token'] ?? '',
    ]);

    $token = [
      'access_token' => $longToken['access_token'] ?? '',
      'expires_in' => $longToken['expires_in'] ?? '',
      'id' => $profile['id'] ?? ($shortToken['user_id'] ?? ''),
      'username' => $profile['username'] ?? '',
      'account_type' => $profile['account_type'] ?? '',
    ];
    $message = 'Instagram token berhasil dibuat.';
  } catch (Exception $ex) {
    $error = $ex->getMessage();
  }
}

$authUrl = ($config['client_id'] && $config['redirect_uri']) ? build_auth_url($config) : '';
?>
<!doctype html>
<html lang="id">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Instagram Login - Clipper</title>
    <link rel="stylesheet" href="/site.css">
    <style>
      code, textarea { font-family: Consolas, monospace; font-size: 13px; }
      textarea { width: 100%; min-height: 120px; }
      .doc { max-width: 920px; }
      .notice { padding: 12px 14px; border: 1px solid #c9dfd4; background: #f1fbf5; }
      .error { padding: 12px 14px; border: 1px solid #f1b7b7; background: #fff4f4; }
    </style>
  </head>
  <body>
    <header class="siteHeader">
      <a class="brand" href="/"><span class="brandMark">CL</span><span>Clipper</span></a>
      <nav><a href="/privacy.php">Privacy</a><a href="/terms.php">Terms</a></nav>
    </header>
    <main class="doc">
      <h1>Instagram Login</h1>
      <p>Pakai halaman ini untuk membuat token Instagram API with Instagram Login.</p>

      <?php if ($message): ?><p class="notice"><?= e($message) ?></p><?php endif; ?>
      <?php if ($error): ?><p class="error"><?= e($error) ?></p><?php endif; ?>

      <h2>Konfigurasi</h2>
      <p><strong>Client ID:</strong> <code><?= e(mask_value($config['client_id'])) ?></code></p>
      <p><strong>Redirect URI:</strong> <code><?= e($config['redirect_uri']) ?></code></p>
      <p><strong>Scopes:</strong> <code><?= e($config['scopes']) ?></code></p>

      <?php if ($authUrl): ?>
        <p><a class="btn primary" href="<?= e($authUrl) ?>">Connect Instagram</a></p>
      <?php else: ?>
        <p class="error">INSTAGRAM_APP_ID atau INSTAGRAM_REDIRECT_URI belum dikonfigurasi.</p>
      <?php endif; ?>

      <?php if ($token): ?>
        <h2>Token</h2>
        <p><strong>Username:</strong> <code><?= e($token['username']) ?></code></p>
        <p><strong>IG User ID:</strong> <code><?= e($token['id']) ?></code></p>
        <p><strong>Account type:</strong> <code><?= e($token['account_type']) ?></code></p>
        <p><strong>Expires in:</strong> <code><?= e($token['expires_in']) ?></code></p>
        <textarea readonly><?= e(token_export_text($token)) ?></textarea>
      <?php endif; ?>

      <h2>Meta Dashboard</h2>
      <p>Tambahkan redirect URI ini di Instagram API setup:</p>
      <p><code><?= e($config['redirect_uri']) ?></code></p>
    </main>
  </body>
</html>
