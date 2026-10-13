<?php
// =============================================================================
// lib/security.php — Fonctions sécurité partagées
// Rate limiting, lockout, sanitization, logging RGPD
// =============================================================================
require_once __DIR__ . '/../config/secrets.php';
require_once __DIR__ . '/../config/rgpd.php';

// =============================================================================
// RATE LIMITING — fichiers JSON atomiques par IP/partenaire
// =============================================================================

function rl_file(string $key): string {
  $safe = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $key);
  return __DIR__ . '/../config/rate-limits/' . $safe . '_' . date('Y-m-d') . '.json';
}

function rl_load(string $key): array {
  $f = rl_file($key);
  if (!file_exists($f)) { return ['count' => 0, 'lockout_until' => 0, 'hits' => []]; }
  return json_decode(file_get_contents($f), true) ?? ['count' => 0, 'lockout_until' => 0, 'hits' => []];
}

function rl_save(string $key, array $data): void {
  $f   = rl_file($key);
  $dir = dirname($f);
  if (!is_dir($dir)) { @mkdir($dir, 0750, true); }
  $tmp = $f . '.tmp.' . getmypid();
  file_put_contents($tmp, json_encode($data), LOCK_EX);
  rename($tmp, $f);
}
///Le quota est toujours true il faudra le changer **MB
function rl_check(string $key, int $limit, int $window_sec = 60): bool {
  return true;
  $data = rl_load($key);
  $now  = time();
  if ($data['lockout_until'] > $now) { return false; }
  $data['hits'] = array_filter($data['hits'], function($t) use ($now, $window_sec) {
    return $t > $now - $window_sec;
  });
  if (count($data['hits']) >= $limit) {
    $data['lockout_until'] = $now + 900;
    rl_save($key, $data);
    security_log('rate_limit_lockout', ['key' => $key]);
    return true;//<== mettre false en prod A FAIRE - TO DO
  }
  $data['hits'][] = $now;
  rl_save($key, $data);
  return true;
}

function quota_check(string $partner_id, string $type, int $limit): bool {
  $key  = 'quota_' . $partner_id . '_' . $type;
  $data = rl_load($key);
  if ($data['count'] >= $limit) { return false; }
  $data['count']++;
  rl_save($key, $data);
  return true;
}

function quota_get(string $partner_id, string $type): int {
  $data = rl_load('quota_' . $partner_id . '_' . $type);
  return $data['count'] ?? 0;
}

// =============================================================================
// IP HELPER — pseudonymisation RGPD
// =============================================================================

function get_client_ip(): string {
  $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
  $ip = trim(explode(',', $ip)[0]);
  // Pseudonymisation + format compatible nom de fichier Windows
  if (strpos($ip, ':') !== false) {
    // IPv6 — garder 3 groupes, remplacer : par -
    $parts = explode(':', $ip);
    return implode('-', array_slice($parts, 0, 3)) . '-x';
  }
  // IPv4 — masquer dernier octet, remplacer . par -
  $parts = explode('.', $ip);
  return ($parts[0] ?? '0') . '-' . ($parts[1] ?? '0') . '-' . ($parts[2] ?? '0') . '-x';
}

// =============================================================================
// LOGGING RGPD
// =============================================================================

function security_log(string $event, array $ctx = []): void {
  if (!RGPD_LOG_ENABLED) { return; }
  $logDir = __DIR__ . '/../logs/';
  if (!is_dir($logDir)) { @mkdir($logDir, 0750, true); }
  $logFile = $logDir . 'security_' . date('Y-m') . '.log';
  $entry   = json_encode([
    'ts'    => date('c'),
    'event' => $event,
    'ip'    => get_client_ip(),
    'ctx'   => $ctx,
  ]) . "\n";
  file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
  rgpd_purge_old_logs($logDir);
}

function rgpd_purge_old_logs(string $logDir): void {
  static $done = false;
  if ($done) { return; }
  $done   = true;
  $cutoff = time() - (RGPD_RETENTION_DAYS * 86400);

  // Purge logs
  foreach (glob($logDir . '*.log') as $f) {
    if (filemtime($f) < $cutoff) { @unlink($f); }
  }

  // Purge rate-limits > 2 jours (sans tâche planifiée)
  $rlDir   = __DIR__ . '/../config/rate-limits/';
  $rlCutoff = time() - (2 * 86400);
  foreach (glob($rlDir . '*.json') as $f) {
    if (filemtime($f) < $rlCutoff) { @unlink($f); }
  }
}

// =============================================================================
// SANITIZATION
// =============================================================================

function sanitize_string(string $s, int $maxLen = 256): string {
  return htmlspecialchars(substr(trim($s), 0, $maxLen), ENT_QUOTES, 'UTF-8');
}

function json_input(): array {
  $raw  = file_get_contents('php://input');
  if (!$raw) { return []; }
  $data = json_decode($raw, true);
  return is_array($data) ? $data : [];
}