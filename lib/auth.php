<?php
// =============================================================================
// lib/auth.php — Interface unifiée d'authentification
// Bascule file ↔ mysql via AUTH_BACKEND dans config/secrets.php
// =============================================================================
require_once __DIR__ . '/../config/secrets.php';

if (!defined('AUTH_BACKEND')) { define('AUTH_BACKEND', 'file'); }

// Charger le backend approprié
if (AUTH_BACKEND === 'mysql') {
  require_once __DIR__ . '/auth_mysql.php';
} else {
  require_once __DIR__ . '/auth_file.php';
}

/**
 * Vérifier identifiants — délègue au backend
 * @return string|false  rôle de l'utilisateur ou false
 */
function auth_check_password(string $user, string $pass): string|false {
  if (auth_is_locked($user)) { return false; }
  $result = (AUTH_BACKEND === 'mysql')
    ? auth_mysql_check($user, $pass)
    : auth_file_check($user, $pass);
  auth_log_attempt($user, $result !== false);
  return $result;
}

function auth_get_user(string $user): ?array {
  return (AUTH_BACKEND === 'mysql')
    ? auth_mysql_get($user)
    : auth_file_get($user);
}

function auth_is_locked(string $user): bool {
  return (AUTH_BACKEND === 'mysql')
    ? auth_mysql_is_locked($user)
    : auth_file_is_locked($user);
}

function auth_log_attempt(string $user, bool $success): void {
  if (AUTH_BACKEND === 'mysql') {
    auth_mysql_log_attempt($user, $success);
  } else {
    auth_file_log_attempt($user, $success);
  }
}

function auth_create_user(string $user, string $pass, string $role = 'user'): bool {
  return (AUTH_BACKEND === 'mysql')
    ? auth_mysql_create($user, $pass, $role)
    : false;  // file mode = lecture seule, on n'écrit pas dans users.php
}