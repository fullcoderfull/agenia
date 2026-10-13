<?php
// =============================================================================
// lib/auth_file.php — Backend FILE (config/users.php)
// Lockout via fichiers JSON dans config/rate-limits/
// =============================================================================
require_once __DIR__ . '/security.php';

function auth_file_check(string $user, string $pass): string|false {
  $users = require __DIR__ . '/../config/users.php';
  if (!isset($users[$user])) { return false; }
  if (!password_verify($pass, $users[$user]['password'])) { return false; }
  return $users[$user]['role'] ?? 'user';
}

function auth_file_get(string $user): ?array {
  $users = require __DIR__ . '/../config/users.php';
  return $users[$user] ?? null;
}

function auth_file_is_locked(string $user): bool {
  $key  = 'lockout_' . $user;
  $data = rl_load($key);
  return $data['lockout_until'] > time();
}

function auth_file_log_attempt(string $user, bool $success): void {
  $key  = 'lockout_' . $user;
  $data = rl_load($key);

  if ($success) {
    // Reset après succès
    $data = ['count' => 0, 'lockout_until' => 0, 'hits' => []];
    rl_save($key, $data);
    return;
  }

  // Incrémenter les échecs
  $data['hits'][] = time();
  $data['hits']   = array_filter($data['hits'], function($t){ return $t > time() - 900; });

  // Lockout progressif
  $count = count($data['hits']);
  if ($count >= 3 && $count < 5) { $data['lockout_until'] = time() + 60; }
  elseif ($count >= 5 && $count < 8) { $data['lockout_until'] = time() + 900; }
  elseif ($count >= 8 && $count < 12) { $data['lockout_until'] = time() + 3600; }
  elseif ($count >= 12) { $data['lockout_until'] = time() + 86400; }

  rl_save($key, $data);
  security_log('auth_fail', ['user' => $user, 'count' => $count]);
}