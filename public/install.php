<?php
/**
 * TDS School Management installer (runs before Laravel is configured).
 * Checks the server, writes .env, then hands over to /install/finish which creates the tables and the owner login.
 * This file deletes itself when installation finishes.
 */
declare(strict_types=1);

$base = realpath(__DIR__ . '/..');
$envFile = $base . '/.env';
$private = $base . '/storage/app/private';

function h($s): string { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function envq(string $v): string { return '"' . str_replace(['\\', '"', "\n", '$'], ['\\\\', '\\"', '', '\\$'], $v) . '"'; }

if (is_file($private . '/installed.flag')) {
    http_response_code(404);
    exit('Already installed.');
}

$checks = [
    'PHP 8.3 or newer (now ' . PHP_VERSION . ')' => version_compare(PHP_VERSION, '8.3.0', '>='),
    'pdo_mysql extension' => extension_loaded('pdo_mysql'),
    'sodium extension' => extension_loaded('sodium'),
    'mbstring extension' => extension_loaded('mbstring'),
    'openssl extension' => extension_loaded('openssl'),
    'fileinfo extension' => extension_loaded('fileinfo'),
    'curl extension' => extension_loaded('curl'),
    'storage folder writable' => is_writable($base . '/storage'),
    'bootstrap/cache writable' => is_writable($base . '/bootstrap/cache'),
    'app folder writable (for .env)' => is_writable($base),
];
$allOk = !in_array(false, $checks, true);
$error = null;
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$siteUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
$f = $_POST + ['db_host' => 'localhost', 'db_port' => '3306', 'app_url' => $siteUrl, 'mail_host' => 'smtp.hostinger.com', 'mail_port' => '465'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $allOk) {
    $driver = ($_POST['db_driver'] ?? 'mysql') === 'sqlite' ? 'sqlite' : 'mysql'; // sqlite only for local testing
    try {
        if (strlen($f['admin_password'] ?? '') < 10) { throw new RuntimeException('Admin password must be at least 10 characters.'); }
        if (($f['admin_password'] ?? '') !== ($f['admin_password2'] ?? '')) { throw new RuntimeException('Admin passwords do not match.'); }
        if (!filter_var($f['admin_email'] ?? '', FILTER_VALIDATE_EMAIL)) { throw new RuntimeException('Enter a valid admin email.'); }
        if (!filter_var($f['app_url'] ?? '', FILTER_VALIDATE_URL)) { throw new RuntimeException('Enter the website address, e.g. https://yourdomain.com'); }

        if ($driver === 'mysql') {
            new PDO("mysql:host={$f['db_host']};port={$f['db_port']};dbname={$f['db_name']};charset=utf8mb4", $f['db_user'], $f['db_pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
        } else {
            touch($base . '/database/database.sqlite');
        }

        $token = bin2hex(random_bytes(20));
        $lines = [
            'APP_NAME=' . envq('TDS School Management Software'),
            'APP_ENV=production',
            'APP_KEY=base64:' . base64_encode(random_bytes(32)),
            'APP_DEBUG=false',
            'APP_URL=' . envq(rtrim($f['app_url'], '/')),
            'APP_TIMEZONE=Asia/Kolkata',
            'APP_LOCALE=en',
            'APP_FALLBACK_LOCALE=en',
            '',
            'LOG_CHANNEL=daily',
            'LOG_LEVEL=warning',
            '',
            'DB_CONNECTION=' . $driver,
        ];
        if ($driver === 'mysql') {
            $lines = array_merge($lines, [
                'DB_HOST=' . envq($f['db_host']), 'DB_PORT=' . envq($f['db_port']), 'DB_DATABASE=' . envq($f['db_name']),
                'DB_USERNAME=' . envq($f['db_user']), 'DB_PASSWORD=' . envq($f['db_pass']),
            ]);
        }
        $lines = array_merge($lines, [
            '',
            'SESSION_DRIVER=file',
            'SESSION_LIFETIME=240',
            'SESSION_SECURE_COOKIE=' . ($scheme === 'https' ? 'true' : 'false'),
            'CACHE_STORE=file',
            'QUEUE_CONNECTION=sync',
            '',
            'MAIL_MAILER=' . (empty($f['mail_user']) ? 'log' : 'smtp'),
            'MAIL_HOST=' . envq($f['mail_host'] ?? ''),
            'MAIL_PORT=' . envq($f['mail_port'] ?? '465'),
            'MAIL_SCHEME=' . (($f['mail_port'] ?? '465') === '465' ? 'smtps' : 'smtp'),
            'MAIL_USERNAME=' . envq($f['mail_user'] ?? ''),
            'MAIL_PASSWORD=' . envq($f['mail_pass'] ?? ''),
            'MAIL_FROM_ADDRESS=' . envq($f['mail_user'] ?: $f['admin_email']),
            'MAIL_FROM_NAME=' . envq('TDS School Management Software'),
            '',
            'INSTALL_TOKEN=' . $token,
        ]);
        file_put_contents($envFile, implode("\n", $lines) . "\n");
        @chmod($envFile, 0600);

        if (!is_dir($private)) { mkdir($private, 0700, true); }
        file_put_contents($private . '/install-admin.json', json_encode([
            'name' => trim($f['admin_name'] ?? 'Admin'),
            'email' => trim($f['admin_email']),
            'phone' => trim($f['admin_phone'] ?? ''),
            'password_hash' => password_hash($f['admin_password'], PASSWORD_BCRYPT),
        ]));
        foreach (glob($base . '/bootstrap/cache/config.php') ?: [] as $c) { @unlink($c); }

        header('Location: ' . rtrim($f['app_url'], '/') . '/install/finish?token=' . $token);
        exit;
    } catch (Throwable $e) {
        $error = $e instanceof PDOException ? 'Could not connect to the database: ' . $e->getMessage() : $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>Install · TDS School Management</title>
<style>
  body { margin:0; font-family:system-ui,"Segoe UI",Roboto,sans-serif; background:#14213d; color:#1f2937; }
  .box { max-width:720px; margin:40px auto; background:#fff; border-radius:14px; padding:28px; }
  h1 { margin:0 0 4px; color:#14213d; } h2 { font-size:16px; margin:24px 0 10px; color:#14213d; }
  .muted { color:#6b7280; font-size:14px; } .grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
  label { display:block; font-size:13px; font-weight:600; } input { width:100%; box-sizing:border-box; margin-top:4px; padding:9px 10px; border:1px solid #d1d5db; border-radius:8px; font:inherit; }
  button { margin-top:22px; width:100%; padding:13px; border:0; border-radius:10px; background:#e9a23b; color:#14213d; font-weight:700; font-size:16px; cursor:pointer; }
  ul.checks { list-style:none; padding:0; margin:0; font-size:14px; } ul.checks li { padding:3px 0; } .ok { color:#16a34a } .bad { color:#dc2626 }
  .err { background:#fee2e2; padding:12px; border-radius:8px; margin-top:14px; font-size:14px; }
  @media (max-width:600px) { .grid { grid-template-columns:1fr; } }
</style></head>
<body><div class="box">
  <h1>Install TDS School Management</h1>
  <p class="muted">One-time setup of your website. Create a MySQL database in Hostinger hPanel → Databases first.</p>
  <h2>1. Server check</h2>
  <ul class="checks"><?php foreach ($checks as $name => $ok): ?><li class="<?= $ok ? 'ok' : 'bad' ?>"><?= $ok ? '✔' : '✘' ?> <?= h($name) ?></li><?php endforeach; ?></ul>
  <?php if (!$allOk): ?><p class="err">Fix the items marked ✘ (hPanel → Advanced → PHP Configuration) and reload this page.</p><?php endif; ?>
  <?php if ($error): ?><p class="err"><?= h($error) ?></p><?php endif; ?>
  <?php if ($allOk): ?>
  <form method="post" autocomplete="off">
    <h2>2. Website address</h2>
    <label>Website URL<input name="app_url" value="<?= h($f['app_url']) ?>" required></label>
    <h2>3. MySQL database (from hPanel → Databases)</h2>
    <div class="grid">
      <label>Database name<input name="db_name" value="<?= h($f['db_name'] ?? '') ?>" placeholder="u123456789_tds"></label>
      <label>Database user<input name="db_user" value="<?= h($f['db_user'] ?? '') ?>" placeholder="u123456789_tds"></label>
      <label>Database password<input type="password" name="db_pass" value=""></label>
      <label>Host<input name="db_host" value="<?= h($f['db_host']) ?>"></label>
    </div>
    <input type="hidden" name="db_port" value="<?= h($f['db_port']) ?>">
    <?php if (isset($_GET['sqlite'])): ?><input type="hidden" name="db_driver" value="sqlite"><?php endif; ?>
    <h2>4. Your owner (super admin) login</h2>
    <div class="grid">
      <label>Your name<input name="admin_name" value="<?= h($f['admin_name'] ?? '') ?>" required></label>
      <label>Mobile<input name="admin_phone" value="<?= h($f['admin_phone'] ?? '') ?>"></label>
      <label>Email (login)<input type="email" name="admin_email" value="<?= h($f['admin_email'] ?? '') ?>" required></label>
      <span></span>
      <label>Password (min 10)<input type="password" name="admin_password" minlength="10" required></label>
      <label>Confirm password<input type="password" name="admin_password2" required></label>
    </div>
    <h2>5. Email for "forgot password" (optional)</h2>
    <p class="muted">Create an email like no-reply@yourdomain.com in hPanel → Emails, then enter it here. You can leave this empty.</p>
    <div class="grid">
      <label>Email address<input name="mail_user" value="<?= h($f['mail_user'] ?? '') ?>"></label>
      <label>Email password<input type="password" name="mail_pass" value=""></label>
      <label>SMTP host<input name="mail_host" value="<?= h($f['mail_host']) ?>"></label>
      <label>SMTP port<input name="mail_port" value="<?= h($f['mail_port']) ?>"></label>
    </div>
    <button>Install</button>
  </form>
  <?php endif; ?>
</div></body></html>
