<?php
/**
 * TDS School Management Software — License Key Admin
 *
 * Upload this folder to your website (e.g. public_html/license-admin/) on Hostinger.
 * Private data (secret signing key, password, issued licenses) is stored OUTSIDE public_html
 * in a folder named "tds-license-data", so it can never be downloaded from the web.
 *
 * Requires PHP 8.1+ with the sodium extension (enabled by default on Hostinger).
 */
declare(strict_types=1);

session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict', 'secure' => !empty($_SERVER['HTTPS'])]);
session_start();
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow');

// ---------- storage (outside the web root) ----------
$docRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__), '/\\');
$DATA = dirname($docRoot) . DIRECTORY_SEPARATOR . 'tds-license-data';
if (!is_dir($DATA) && !@mkdir($DATA, 0700, true)) {
    exit('Cannot create the private data folder: ' . htmlspecialchars($DATA));
}
$F_CONFIG = "$DATA/config.json";
$F_KEYS = "$DATA/signing-key.json";
$F_LIC = "$DATA/licenses.json";
$F_LOCK = "$DATA/login-attempts.json";

function readJson(string $f, $default) { return is_file($f) ? (json_decode((string) file_get_contents($f), true) ?? $default) : $default; }
function writeJson(string $f, $data): void { file_put_contents($f, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX); @chmod($f, 0600); }
function h($s): string { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function b64e(string $b): string { return rtrim(strtr(base64_encode($b), '+/', '-_'), '='); }
function csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(16)); }
function checkCsrf(): void { if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) { http_response_code(400); exit('Session expired. Go back and try again.'); } }

if (!function_exists('sodium_crypto_sign_keypair')) {
    exit('The PHP "sodium" extension is not enabled. In Hostinger hPanel → Advanced → PHP Configuration → PHP Extensions, enable "sodium".');
}

$config = readJson($F_CONFIG, []);
$keys = readJson($F_KEYS, []);
$msg = null; $err = null; $newKey = null;
$action = $_POST['action'] ?? null;

// ---------- first-time setup ----------
if (!$config) {
    if ($action === 'setup') {
        checkCsrf();
        $p = (string) ($_POST['password'] ?? '');
        if (strlen($p) < 10) {
            $err = 'Password must be at least 10 characters.';
        } elseif ($p !== ($_POST['password2'] ?? '')) {
            $err = 'Passwords do not match.';
        } else {
            if (!$keys) {
                $pair = sodium_crypto_sign_keypair();
                $keys = [
                    'public' => base64_encode(sodium_crypto_sign_publickey($pair)),
                    'secret' => base64_encode(sodium_crypto_sign_secretkey($pair)),
                    'created' => date('c'),
                ];
                writeJson($F_KEYS, $keys);
            }
            $config = ['password' => password_hash($p, PASSWORD_DEFAULT), 'created' => date('c')];
            writeJson($F_CONFIG, $config);
            session_regenerate_id(true);
            $_SESSION['auth'] = true;
            header('Location: ?welcome=1');
            exit;
        }
    }
    page('Set up', function () use ($err) { ?>
        <div class="card narrow">
            <h1>Set up License Admin</h1>
            <p class="muted">Choose a strong password. A new signing key will be created and stored privately on your hosting.</p>
            <?php if ($err): ?><div class="alert err"><?= h($err) ?></div><?php endif; ?>
            <form method="post">
                <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="action" value="setup">
                <label>Admin password (min 10 characters)<input type="password" name="password" required minlength="10" autofocus></label>
                <label>Confirm password<input type="password" name="password2" required></label>
                <button>Create</button>
            </form>
        </div>
    <?php });
    exit;
}

// ---------- login / logout ----------
if ($action === 'logout') { checkCsrf(); session_destroy(); header('Location: ./'); exit; }

if (empty($_SESSION['auth'])) {
    $lock = readJson($F_LOCK, ['count' => 0, 'until' => 0]);
    if ($action === 'login') {
        checkCsrf();
        if ($lock['until'] > time()) {
            $err = 'Too many wrong attempts. Try again after ' . ceil(($lock['until'] - time()) / 60) . ' minute(s).';
        } elseif (password_verify((string) ($_POST['password'] ?? ''), $config['password'])) {
            writeJson($F_LOCK, ['count' => 0, 'until' => 0]);
            session_regenerate_id(true);
            $_SESSION['auth'] = true;
            header('Location: ./');
            exit;
        } else {
            sleep(2);
            $lock['count']++;
            if ($lock['count'] >= 5) { $lock = ['count' => 0, 'until' => time() + 900]; }
            writeJson($F_LOCK, $lock);
            $err = 'Wrong password.';
        }
    }
    page('Login', function () use ($err) { ?>
        <div class="card narrow">
            <h1>License Admin</h1>
            <?php if ($err): ?><div class="alert err"><?= h($err) ?></div><?php endif; ?>
            <form method="post">
                <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="action" value="login">
                <label>Password<input type="password" name="password" required autofocus></label>
                <button>Login</button>
            </form>
        </div>
    <?php });
    exit;
}

// ---------- logged in ----------
$licenses = readJson($F_LIC, []);

if ($action === 'generate') {
    checkCsrf();
    $id = strtoupper(trim((string) ($_POST['installation_id'] ?? '')));
    $school = trim((string) ($_POST['school'] ?? ''));
    $plan = (string) ($_POST['plan'] ?? '1y');
    $plans = ['1y' => ['yearly', '+1 year'], '2y' => ['2 years', '+2 years'], '3y' => ['3 years', '+3 years'], 'life' => ['lifetime', null], 'custom' => ['custom', null]];

    if (!preg_match('/^TDS(-[2-9A-HJ-NP-Z]{4}){3}$/', $id)) {
        $err = 'Installation ID looks wrong. It should look like TDS-7K2P-QX9M-4HDA.';
    } elseif ($school === '') {
        $err = 'Enter the school name.';
    } elseif (!isset($plans[$plan])) {
        $err = 'Choose a plan.';
    } else {
        $exp = null;
        if ($plan === 'custom') {
            $d = DateTime::createFromFormat('Y-m-d', (string) ($_POST['custom_date'] ?? ''));
            if (!$d) { $err = 'Choose the expiry date.'; } else { $exp = $d->format('Y-m-d'); }
        } elseif ($plans[$plan][1]) {
            $exp = (new DateTime('today'))->modify($plans[$plan][1])->format('Y-m-d');
        }
        if (!$err) {
            $payload = ['id' => $id, 'school' => $school, 'plan' => $plans[$plan][0], 'exp' => $exp, 'iat' => date('Y-m-d')];
            $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $sig = sodium_crypto_sign_detached($json, base64_decode($keys['secret']));
            $newKey = 'TDS1.' . b64e($json) . '.' . b64e($sig);
            $licenses[] = $payload + [
                'phone' => trim((string) ($_POST['phone'] ?? '')),
                'amount' => trim((string) ($_POST['amount'] ?? '')),
                'notes' => trim((string) ($_POST['notes'] ?? '')),
                'key' => $newKey,
                'created' => date('Y-m-d H:i'),
            ];
            writeJson($F_LIC, $licenses);
            $msg = 'License key created for ' . $school . '.';
        }
    }
}

if ($action === 'password') {
    checkCsrf();
    $p = (string) ($_POST['new_password'] ?? '');
    if (!password_verify((string) ($_POST['current_password'] ?? ''), $config['password'])) { $err = 'Current password is wrong.'; }
    elseif (strlen($p) < 10) { $err = 'New password must be at least 10 characters.'; }
    else { $config['password'] = password_hash($p, PASSWORD_DEFAULT); writeJson($F_CONFIG, $config); $msg = 'Password changed.'; }
}

if (($_GET['download'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="tds-licenses-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Created', 'School', 'Phone', 'Installation ID', 'Plan', 'Expires', 'Amount', 'Notes', 'Key']);
    foreach ($licenses as $l) { fputcsv($out, [$l['created'], $l['school'], $l['phone'], $l['id'], $l['plan'], $l['exp'] ?? 'Lifetime', $l['amount'], $l['notes'], $l['key']]); }
    exit;
}
if (($_GET['download'] ?? '') === 'backup') {
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="tds-license-backup-' . date('Y-m-d') . '.json"');
    echo json_encode(['signing_key' => $keys, 'licenses' => $licenses], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$q = trim((string) ($_GET['q'] ?? ''));
$list = array_reverse($licenses);
if ($q !== '') {
    $list = array_filter($list, fn ($l) => stripos($l['school'] . ' ' . $l['id'] . ' ' . $l['phone'], $q) !== false);
}

page('Licenses', function () use ($keys, $list, $msg, $err, $newKey, $q, $licenses) {
    $last = $newKey ? end($licenses) : null; ?>
    <header class="top">
        <strong>TDS License Admin</strong>
        <span class="muted"><?= count($licenses) ?> license(s) issued</span>
        <form method="post" class="inline"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="action" value="logout"><button class="link">Logout</button></form>
    </header>

    <?php if (isset($_GET['welcome'])): ?>
        <div class="alert ok"><strong>Setup complete.</strong> Copy the public key below into GitHub (step shown below), then build the installer. Also download a backup and keep it safe.</div>
    <?php endif; ?>
    <?php if ($msg): ?><div class="alert ok"><?= h($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert err"><?= h($err) ?></div><?php endif; ?>

    <?php if ($newKey): ?>
        <div class="card highlight">
            <h2>New license key</h2>
            <p class="muted"><?= h($last['school']) ?> · <?= h($last['id']) ?> · <?= h(ucfirst($last['plan'])) ?> · valid till <?= h($last['exp'] ? date('d M Y', strtotime($last['exp'])) : 'Lifetime') ?></p>
            <textarea id="newkey" rows="3" readonly><?= h($newKey) ?></textarea>
            <div class="row">
                <button type="button" onclick="copyText('newkey', this)">Copy key</button>
                <?php $wa = "Your TDS School Management Software license key:\n\n" . $newKey . "\n\nOpen the software → License → paste this key → Activate."; ?>
                <a class="btn green" target="_blank" rel="noopener" href="https://wa.me/<?php $ph = preg_replace('/\D/', '', $last['phone']); echo h(strlen($ph) === 10 ? '91' . $ph : $ph); ?>?text=<?= rawurlencode($wa) ?>">Send on WhatsApp</a>
            </div>
        </div>
    <?php endif; ?>

    <div class="grid">
        <div class="card">
            <h2>Create license key</h2>
            <form method="post">
                <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="action" value="generate">
                <label>Installation ID (from the school's License screen)<input name="installation_id" placeholder="TDS-XXXX-XXXX-XXXX" required style="text-transform:uppercase;font-family:monospace"></label>
                <label>School name<input name="school" required></label>
                <label>School phone / WhatsApp<input name="phone" placeholder="98XXXXXXXX"></label>
                <label>Plan
                    <select name="plan" onchange="document.getElementById('cd').style.display = this.value === 'custom' ? 'block' : 'none'">
                        <option value="1y">1 Year</option><option value="2y">2 Years</option><option value="3y">3 Years</option><option value="life">Lifetime</option><option value="custom">Custom expiry date</option>
                    </select>
                </label>
                <label id="cd" style="display:none">Expiry date<input type="date" name="custom_date"></label>
                <div class="row2">
                    <label>Amount received (₹)<input name="amount"></label>
                    <label>Notes<input name="notes" placeholder="UPI ref, etc."></label>
                </div>
                <button>Create key</button>
            </form>
        </div>
        <div class="card">
            <h2>Public key (for the installer)</h2>
            <p class="muted small">Add this in GitHub → your repository → Settings → Secrets and variables → Actions → <b>Variables</b> → New variable named <code>TDS_LICENSE_PUBLIC_KEY</code>.</p>
            <textarea id="pk" rows="2" readonly><?= h($keys['public']) ?></textarea>
            <button type="button" onclick="copyText('pk', this)">Copy public key</button>
            <h2 style="margin-top:22px">Backup</h2>
            <p class="muted small">The backup contains your <b>secret signing key</b>. If it is lost, keys for existing installs cannot be created any more. Keep it in a safe place (not in email or WhatsApp), and never share it.</p>
            <div class="row"><a class="btn" href="?download=backup">Download backup</a> <a class="btn light" href="?download=csv">Export licenses (CSV)</a></div>
            <details style="margin-top:18px"><summary>Change password</summary>
                <form method="post" style="margin-top:10px">
                    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="action" value="password">
                    <label>Current password<input type="password" name="current_password" required></label>
                    <label>New password<input type="password" name="new_password" required minlength="10"></label>
                    <button>Change</button>
                </form>
            </details>
        </div>
    </div>

    <div class="card">
        <div class="row" style="justify-content:space-between"><h2>Issued licenses</h2>
            <form method="get" class="inline"><input name="q" value="<?= h($q) ?>" placeholder="Search school, ID, phone"> <button class="light">Search</button></form>
        </div>
        <div style="overflow-x:auto">
        <table>
            <thead><tr><th>Created</th><th>School</th><th>Phone</th><th>Installation ID</th><th>Plan</th><th>Valid till</th><th>Amount</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($list as $i => $l): $expired = $l['exp'] && $l['exp'] < date('Y-m-d'); ?>
                <tr>
                    <td><?= h($l['created']) ?></td><td><?= h($l['school']) ?><?php if ($l['notes']): ?><div class="muted small"><?= h($l['notes']) ?></div><?php endif; ?></td>
                    <td><?= h($l['phone']) ?></td><td class="mono"><?= h($l['id']) ?></td><td><?= h(ucfirst($l['plan'])) ?></td>
                    <td class="<?= $expired ? 'red' : '' ?>"><?= h($l['exp'] ? date('d M Y', strtotime($l['exp'])) : 'Lifetime') ?></td>
                    <td><?= h($l['amount']) ?></td>
                    <td><textarea id="k<?= $i ?>" class="hidden"><?= h($l['key']) ?></textarea><button class="light small" type="button" onclick="copyText('k<?= $i ?>', this)">Copy key</button></td>
                </tr>
            <?php endforeach; if (!$list): ?><tr><td colspan="8" class="muted" style="text-align:center;padding:20px">No licenses yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
<?php });

// ---------- layout ----------
function page(string $title, callable $body): void { ?>
<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow">
<title><?= h($title) ?> · TDS License Admin</title>
<style>
  :root { --navy:#14213d; --amber:#e9a23b; }
  * { box-sizing:border-box } body { margin:0; font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif; background:#f4f6fa; color:#1f2937; font-size:15px; }
  .wrap { max-width:1100px; margin:0 auto; padding:16px; }
  .top { display:flex; gap:12px; align-items:center; background:var(--navy); color:#fff; padding:12px 16px; border-radius:10px; margin-bottom:16px; }
  .top .muted { color:#cbd5e1 } .top form { margin-left:auto } .top .link { color:#fff }
  .card { background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:18px; margin-bottom:16px; }
  .card.narrow { max-width:420px; margin:10vh auto; } .card.highlight { border:2px solid var(--amber); }
  .grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; } @media (max-width:800px) { .grid { grid-template-columns:1fr } }
  h1 { font-size:22px; margin:0 0 8px } h2 { font-size:17px; margin:0 0 12px }
  label { display:block; font-size:13px; font-weight:600; margin-bottom:10px } input, select, textarea { display:block; width:100%; margin-top:4px; padding:9px 10px; border:1px solid #d1d5db; border-radius:8px; font:inherit; font-weight:400 }
  textarea { font-family:ui-monospace,monospace; font-size:12px; word-break:break-all }
  button, .btn { background:var(--navy); color:#fff; border:0; padding:9px 16px; border-radius:8px; font:inherit; font-weight:600; cursor:pointer; text-decoration:none; display:inline-block; margin-top:6px }
  .btn.green { background:#16a34a } button.light, .btn.light { background:#e5e7eb; color:#111 } button.small { padding:5px 10px; font-size:13px; margin:0 } button.link { background:none; padding:0; margin:0; text-decoration:underline }
  .row { display:flex; gap:8px; flex-wrap:wrap; align-items:center } .row2 { display:grid; grid-template-columns:1fr 1fr; gap:10px } .inline { display:flex; gap:6px; align-items:center } .inline input { margin:0 }
  .alert { padding:12px 14px; border-radius:8px; margin-bottom:14px } .ok { background:#dcfce7 } .err { background:#fee2e2 }
  .muted { color:#6b7280 } .small { font-size:13px } .mono { font-family:ui-monospace,monospace; font-size:13px } .red { color:#dc2626; font-weight:600 } .hidden { display:none }
  table { width:100%; border-collapse:collapse; font-size:14px } th, td { text-align:left; padding:8px; border-bottom:1px solid #eef0f4; vertical-align:top } th { font-size:12px; text-transform:uppercase; color:#6b7280 }
</style></head>
<body><div class="wrap"><?php $body(); ?></div>
<script>
function copyText(id, btn) { const el = document.getElementById(id); navigator.clipboard.writeText(el.value || el.textContent).then(() => { const t = btn.textContent; btn.textContent = 'Copied!'; setTimeout(() => btn.textContent = t, 1500); }); }
</script>
</body></html>
<?php }
