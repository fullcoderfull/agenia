<?php
// =============================================================================
// partner-auth.php — Authentification partenaire
// GET  : page de login partenaire
// POST : vérif mot de passe → JWT 24h → redirect index.php?token=JWT
// =============================================================================
error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/logs/boot_' . date('Y-m-d') . '.log');

require_once __DIR__ . '/config/secrets.php';
require_once __DIR__ . '/lib/license.php';
license_enforce();
require_once __DIR__ . '/lib/debug.php';
require_once __DIR__ . '/lib/security.php';  // charge config/rgpd.php via /../
require_once __DIR__ . '/lib/jwt.php';

$partners = require __DIR__ . '/config/partners.php';
$error    = '';
$ip       = get_client_ip();

// ── Vérif partenaire déjà connecté ─────────────────────────────────────────
if (isset($_COOKIE['vox_partner'])) {
  $pid = $_COOKIE['vox_partner_id'] ?? '';
  if ($pid && isset($partners[$pid])) {
    $payload = jwt_decode($_COOKIE['vox_partner'], $partners[$pid]['secret']);
    if ($payload) { header('Location: /index.php'); exit; }
  }
}

// ── POST — traitement login ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Rate limit : 5 tentatives/10min par IP
  if (!rl_check('partner_login_' . $ip, 5, 600)) {
    security_log('partner_brute_force', ['ip' => $ip]);
    http_response_code(429);
    $error = 'Trop de tentatives. Réessayez dans 15 minutes.';
  } else {
    $pid  = sanitize_string($_POST['partner_id'] ?? '', 64);
    $pass = $_POST['password'] ?? '';

    // Délai constant anti-timing
    usleep(300000);

    if ($pid && isset($partners[$pid])
        && $partners[$pid]['active']
        && $partners[$pid]['expires'] >= date('Y-m-d')
        && password_verify($pass, $partners[$pid]['password'])) {

      $partner = $partners[$pid];
      $nonce   = bin2hex(random_bytes(16));
      $payload = [
        'sub'     => $pid,
        'name'    => $partner['name'],
        'vision'  => $partner['vision'],
        'quota'   => $partner['quota_day'],
        'nonce'   => $nonce,
        'iat'     => time(),
        'exp'     => time() + 86400,  // 24h
      ];

      $token = jwt_encode($payload, $partner['secret']);

      // Cookie sécurisé
      setcookie('vox_partner',    $token, ['expires' => time() + 86400, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Strict']);
      setcookie('vox_partner_id', $pid,   ['expires' => time() + 86400, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Strict']);

      security_log('partner_login', ['partner' => $pid]);
      header('Location: /index.php'); exit;

    } else {
      security_log('partner_login_fail', ['partner' => $pid]);
      $error = 'Identifiants incorrects.';
    }
  }
}
?>
<!DOCTYPE html>
<html lang="<?= UI_LANG ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>VoxMind — Accès Partenaire</title>
<style>
  :root{--bg:#0a0a0f;--surface:#12121a;--border:#2a2a3e;--accent:#7c3aed;--accent2:#06b6d4;--text:#e8e8f0;--text-muted:#6b6b88;--red:#ef4444;--mono:'Courier New',Courier,monospace;--sans:system-ui,-apple-system,sans-serif;--radius:8px}
  *,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
  html,body{height:100%;background:var(--bg);color:var(--text);font-family:var(--sans);display:flex;align-items:center;justify-content:center}
  .card{width:100%;max-width:400px;padding:40px 32px;background:var(--surface);border:1px solid var(--border);border-radius:12px}
  .logo{font-family:var(--mono);font-size:20px;font-weight:700;color:var(--accent2);letter-spacing:.1em;margin-bottom:8px;text-align:center}
  .logo span{color:var(--accent)}
  .sub{text-align:center;font-family:var(--mono);font-size:10px;color:var(--text-muted);margin-bottom:28px}
  label{display:block;font-size:12px;color:var(--text-muted);margin-bottom:6px;font-family:var(--mono)}
  input{width:100%;background:var(--bg);border:1px solid var(--border);border-radius:var(--radius);color:var(--text);font-size:14px;padding:10px 14px;outline:none;margin-bottom:16px;transition:border-color .2s}
  input:focus{border-color:var(--accent)}
  button{width:100%;padding:12px;background:var(--accent);border:none;border-radius:var(--radius);color:#fff;font-family:var(--mono);font-size:13px;letter-spacing:.08em;cursor:pointer;transition:background .2s;margin-top:8px}
  button:hover{background:#6d28d9}
  .error{color:var(--red);font-family:var(--mono);font-size:11px;margin-bottom:12px;padding:8px;border:1px solid var(--red);border-radius:var(--radius);background:rgba(239,68,68,.08)}
  .rgpd{margin-top:24px;font-size:10px;color:var(--text-muted);font-family:var(--mono);line-height:1.6;text-align:center}
  .rgpd a{color:var(--accent2);text-decoration:none}
</style>
</head>
<body>
<div class="card">
  <div class="logo">VOX<span>MIND</span></div>
  <div class="sub">// ACCÈS PARTENAIRE</div>
  <?php if ($error): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <form method="POST" autocomplete="off">
    <label>Identifiant partenaire</label>
    <input type="text" name="partner_id" required autofocus autocomplete="off" maxlength="64">
    <label>Mot de passe</label>
    <input type="password" name="password" required autocomplete="current-password">
    <button type="submit">→ CONNEXION</button>
  </form>
  <div class="rgpd">
    Vos données de connexion sont traitées conformément au<br>
    <a href="/rgpd.php">Règlement Général sur la Protection des Données</a><br>
    Contact DPO : <a href="mailto:<?= RGPD_CONTACT_EMAIL ?>"><?= RGPD_CONTACT_EMAIL ?></a>
    — <?= RGPD_CONTACT_TEL ?>
  </div>
</div>
</body>
</html>