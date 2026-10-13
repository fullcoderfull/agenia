<?php
// =============================================================================
// logout.php — Déconnexion sécurisée
// Supprime tous les cookies de session et redirige vers login.php
// =============================================================================
error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/logs/boot_' . date('Y-m-d') . '.log');

require_once __DIR__ . '/config/secrets.php';
require_once __DIR__ . '/lib/debug.php';
require_once __DIR__ . '/lib/security.php';
require_once __DIR__ . '/lib/db.php';          // idempotent — safe si déjà chargé
require_once __DIR__ . '/lib/db_helpers.php';
require_once __DIR__ . '/lib/auth_guest.php';

$ip   = get_client_ip();
$user = '';

// Identifier l'utilisateur depuis le cookie avant suppression
if (isset($_COOKIE['vox'])) {
  $decoded = base64_decode($_COOKIE['vox'], true);
  if ($decoded) {
    $parts = explode('|', $decoded);
    if (count($parts) === 3) { $user = $parts[0]; }
  }

  $ipHash    = guest_hash_ip($ip);
  $raw       = bin2hex(random_bytes(32));           // token brut — envoyé au client
  $tokenHash = hash_hmac('sha256', $raw, GUEST_HMAC_SECRET);
  $expiresAt = date('Y-m-d H:i:s', time() + 0);

  db_query(
    'UPDATE agenia_users_sessions set
      expires_at= ? WHERE ip_Hash= ?',
    [$expiresAt,$ipHash]
  );

} elseif (isset($_COOKIE['vox_guest'])) {
  $user = 'guest';
  $ipHash    = guest_hash_ip($ip);
  $raw       = bin2hex(random_bytes(32));           // token brut — envoyé au client
  $tokenHash = hash_hmac('sha256', $raw, GUEST_HMAC_SECRET);
  $expiresAt = date('Y-m-d H:i:s', time() + 0);

  db_query(
    'UPDATE agenia_guest_sessions set
      expires_at= ? WHERE ip_Hash= ?',
    [$expiresAt,$ipHash]
  );

} elseif (isset($_COOKIE['vox_partner'])) {
  $user = 'partner_' . ($_COOKIE['vox_partner_id'] ?? '?');
}

debug_log('LOGOUT', 'START', ['user' => $user, 'ip' => $ip]);

// ── Expirer tous les cookies de session ───────────────────────────────────────
$cookieOptions = [
  'expires'  => time() - 3600,
  'path'     => '/',
  'secure'   => true,
  'httponly' => true,
  'samesite' => 'Strict',
];

foreach (['vox', 'vox_guest', 'vox_partner', 'vox_partner_id'] as $name) {
  if (isset($_COOKIE[$name])) {
    setcookie($name, '', $cookieOptions);

    debug_log('LOGOUT', 'COOKIE_CLEARED', ['name' => $name]);
  }
}

// ── Log sécurité ──────────────────────────────────────────────────────────────
security_log('logout', ['user' => $user]);
debug_log('LOGOUT', 'DONE', ['user' => $user, 'redirect' => '/login.php']);

// ── Redirect ──────────────────────────────────────────────────────────────────
$host = $_SERVER['HTTP_HOST'] ?? '';
header('Location: https://' . $host . '/login.php');
exit;