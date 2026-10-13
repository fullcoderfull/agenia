<?php
error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('error_log', dirname(__DIR__) . '/logs/boot_' . date('Y-m-d') . '.log');
require_once dirname(__DIR__) . '/config/secrets.php';
require_once dirname(__DIR__) . '/lib/license.php';
license_enforce();
require_once dirname(__DIR__) . '/lib/auth.php';
require_once dirname(__DIR__) . '/lib/db.php';

// Déjà connecté → redirect (HMAC, format identique à check.php)
if (isset($_COOKIE['vox'])) {
  $d = base64_decode($_COOKIE['vox'], true);
  if ($d && count($p = explode('|', $d)) === 3) {
    [$u, $exp, $sig] = $p;
    if (time() < (int)$exp
        && hash_equals(hash_hmac('sha256', $u . '|' . $exp, HMAC_SECRET), $sig)) {
      header('Location: /index.php'); exit;
    }
  }
}
if (isset($_COOKIE['vox_guest'])) { header('Location: /index.php'); exit; }