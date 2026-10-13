<?php
// =============================================================================
// lib/auth_mysql.php — Backend MySQL
// =============================================================================
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';

function auth_mysql_check(string $user, string $pass): string|false {
  $stmt = db()->prepare('SELECT password, role, active FROM users WHERE username = :u LIMIT 1');
  $stmt->execute([':u' => $user]);
  $row = $stmt->fetch();
  if (!$row || !$row['active']) { return false; }
  if (!password_verify($pass, $row['password'])) { return false; }
  // Update last_login
  $up = db()->prepare('UPDATE users SET last_login = NOW() WHERE username = :u');
  $up->execute([':u' => $user]);
  return $row['role'];
}

function auth_mysql_get(string $user): ?array {
  $stmt = db()->prepare('SELECT username, role, active, created_at, last_login FROM users WHERE username = :u LIMIT 1');
  $stmt->execute([':u' => $user]);
  $row = $stmt->fetch();
  return $row ?: null;
}

function auth_mysql_is_locked(string $user): bool {
  $stmt = db()->prepare('SELECT locked_until FROM users WHERE username = :u LIMIT 1');
  $stmt->execute([':u' => $user]);
  $row = $stmt->fetch();
  if (!$row || !$row['locked_until']) { return false; }
  return strtotime($row['locked_until']) > time();
}

function auth_mysql_log_attempt(string $user, bool $success): void {
  if ($success) {
    $stmt = db()->prepare('UPDATE users SET fail_count = 0, locked_until = NULL WHERE username = :u');
    $stmt->execute([':u' => $user]);
    return;
  }

  // Incrémenter et appliquer lockout progressif
  $stmt = db()->prepare('UPDATE users SET fail_count = fail_count + 1, last_fail = NOW() WHERE username = :u');
  $stmt->execute([':u' => $user]);

  $stmt = db()->prepare('SELECT fail_count FROM users WHERE username = :u LIMIT 1');
  $stmt->execute([':u' => $user]);
  $count = (int)($stmt->fetch()['fail_count'] ?? 0);

  $lockSec = 0;
  if ($count >= 3 && $count < 5)  { $lockSec = 60; }
  elseif ($count >= 5 && $count < 8)  { $lockSec = 900; }
  elseif ($count >= 8 && $count < 12) { $lockSec = 3600; }
  elseif ($count >= 12) { $lockSec = 86400; }

  if ($lockSec > 0) {
    $stmt = db()->prepare('UPDATE users SET locked_until = DATE_ADD(NOW(), INTERVAL :s SECOND) WHERE username = :u');
    $stmt->execute([':u' => $user, ':s' => $lockSec]);
  }

  security_log('auth_fail_db', ['user' => $user, 'count' => $count]);
}

function auth_mysql_create(string $user, string $pass, string $role = 'user'): bool {
  $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);
  try {
    $stmt = db()->prepare('INSERT INTO users (username, password, role, active, created_at) VALUES (:u, :p, :r, 1, NOW())');
    $stmt->execute([':u' => $user, ':p' => $hash, ':r' => $role]);
    return true;
  } catch (PDOException $e) {
    return false;  // user déjà existant
  }
}