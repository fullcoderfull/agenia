<?php
// =============================================================================
// lib/license.php — Verrouillage d'instance par domaine signé HMAC
// Le code ne fonctionne que si le domaine correspond à la signature
// =============================================================================
require_once __DIR__ . '/../config/secrets.php';

if (!defined('LICENSE_DOMAIN'))    { define('LICENSE_DOMAIN',    'vovox.mondomaine.com'); }
if (!defined('LICENSE_SIGNATURE')) { define('LICENSE_SIGNATURE', ''); }
if (!defined('LICENSE_MASTER_KEY')){ define('LICENSE_MASTER_KEY','changeme_master_key_owner_only'); }

/**
 * Vérifier que l'instance tourne bien sur le domaine autorisé
 * @return bool true si valide, false sinon (le code refuse de fonctionner)
 */
function license_check(): bool {
  $expected = hash_hmac('sha256', LICENSE_DOMAIN, LICENSE_MASTER_KEY);
  if (!hash_equals($expected, LICENSE_SIGNATURE)) {
    return false;
  }
  $currentHost = $_SERVER['HTTP_HOST'] ?? '';
  $currentHost = preg_replace('/:\d+$/', '', $currentHost);  // retirer port
  return strcasecmp($currentHost, LICENSE_DOMAIN) === 0;
}

/**
 * Bloquer l'application si la licence n'est pas valide
 * Affiche une page neutre — pas d'info sur la nature du blocage
 */
function license_enforce(): void {
  if (license_check()) { return; }
  http_response_code(403);
  header('Content-Type: text/html; charset=UTF-8');
  echo '<!DOCTYPE html><html><head><title>403</title></head><body style="background:#0a0a0f;color:#9090aa;font-family:monospace;display:flex;align-items:center;justify-content:center;height:100vh;margin:0"><div>Service unavailable</div></body></html>';
  exit;
}

/**
 * Helper réservé au propriétaire — génère une signature pour un domaine
 * Utilisation CLI : php -r "require 'lib/license.php'; echo license_sign('mon-domaine.com');"
 */
function license_sign(string $domain): string {
  return hash_hmac('sha256', $domain, LICENSE_MASTER_KEY);
}