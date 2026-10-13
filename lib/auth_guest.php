<?php
// =============================================================================
// lib/auth_guest.php — Sessions guest (visiteurs temporaires 20 min)
// =============================================================================
require_once __DIR__ . '/../config/secrets.php';
require_once __DIR__ . '/db.php';          // idempotent — safe si déjà chargé
require_once __DIR__ . '/db_helpers.php';

/**
 * Hasher l'IP pour RGPD — on ne stocke jamais l'IP brute
 */
function guest_hash_ip(string $ip): string {
  return hash_hmac('sha256', $ip, GUEST_HMAC_SECRET);
}

/**
 * Lire la config guest depuis settings.json
 */
function guest_settings(): array {
  static $s = null;
  if ($s !== null) { return $s; }
  $file = __DIR__ . '/../config/settings.json';
  $s    = json_decode(file_get_contents($file), true)['guest'] ?? [];
  return $s;
}

function user_settings(): array {
  static $s = null;
  if ($s !== null) { return $s; }
  $file = __DIR__ . '/../config/settings.json';
  $s    = json_decode(file_get_contents($file), true)['user'] ?? [];
  return $s;
}

/**
 * Vérifier si une IP est bloquée (option B — blocage post-expiration)
 */
function guest_ip_is_blocked(string $ip): bool {
  $ipHash = guest_hash_ip($ip);
  $row = db_row(
    'SELECT blocked_until FROM agenia_guest_sessions
     WHERE ip_hash = ? AND blocked_until IS NOT NULL AND blocked_until > NOW()
     ORDER BY blocked_until DESC LIMIT 1',
    [$ipHash]
  );
  return $row !== null;
}

function user_ip_is_blocked(string $ip): bool {
  $ipHash = guest_hash_ip($ip);
  $row = db_row(
    'SELECT blocked_until FROM agenia_users_sessions
     WHERE ip_hash = ? AND blocked_until IS NOT NULL AND blocked_until > NOW()
     ORDER BY blocked_until DESC LIMIT 1',
    [$ipHash]
  );
  return $row !== null;
}

/**
 * Créer une nouvelle session guest
 * @return array ['token' => string, 'expires_at' => int] ou ['error' => string]
 */
function guest_create(string $ip, ?int $subscriberId = null): array {
  $settings = guest_settings();
  $ttl      = (int)($settings['ttl']         ?? GUEST_TTL);
  $blockMin = (int)($settings['blockMinutes'] ?? GUEST_BLOCK_MIN);

  // Vérifier blocage IP
  if (guest_ip_is_blocked($ip)) {
    return ['error' => 'ip_blocked'];
  }

  $ipHash    = guest_hash_ip($ip);
  $raw       = bin2hex(random_bytes(32));           // token brut — envoyé au client
  $tokenHash = hash_hmac('sha256', $raw, GUEST_HMAC_SECRET);
  $expiresAt = date('Y-m-d H:i:s', time() + $ttl);

  db_query(
    'INSERT INTO agenia_guest_sessions
       (token_hash, ip_hash, expires_at, subscriber_id)
     VALUES (?, ?, ?, ?)',
    [$tokenHash, $ipHash, $expiresAt, $subscriberId]
  );

  return [
    'token'      => $raw,
    'expires_at' => time() + $ttl,
    'ttl'        => $ttl,
  ];
}

function session_user_create(string $ip, ?int $subscriberId = null): array {
  
  try {
    //code... $settings = user_settings();
  $ttl      = (int)($settings['ttl']         ?? SESSION_TTL);
  $blockMin = (int)($settings['blockMinutes'] ?? USER_BLOCK_MIN);

  // Vérifier blocage IP
  if (user_ip_is_blocked($ip)) {
    return ['error' => 'ip_blocked'];
  }

  $ipHash    = guest_hash_ip($ip);
  $raw       = bin2hex(random_bytes(32));           // token brut — envoyé au client
  $tokenHash = hash_hmac('sha256', $raw, GUEST_HMAC_SECRET);
  $expiresAt = date('Y-m-d H:i:s', time() + $ttl);

  db_query(
    'INSERT INTO agenia_users_sessions
       (token_hash, ip_hash, expires_at, subscriber_id)
     VALUES (?, ?, ?, ?)',
    [$tokenHash, $ipHash, $expiresAt, $subscriberId]
  );

  return [
    'token'      => $raw,
    'expires_at' => time() + $ttl,
    'ttl'        => $ttl,
  ];
  } catch (\Throwable $th) {
    echo($subscriberId); echo k;
  }
 
}

/**
 * Valider un token guest — retourne les données de session ou false
 */
function guest_validate(string $rawToken): array|false {
  if (strlen($rawToken) < 16) { return false; }

  $tokenHash = hash_hmac('sha256', $rawToken, GUEST_HMAC_SECRET);
  $row = db_row(
    'SELECT * FROM agenia_guest_sessions
     WHERE token_hash = ? AND expires_at > NOW()
     LIMIT 1',
    [$tokenHash]
  );

  if (!$row) { return false; }

  $settings    = guest_settings();
  $maxMessages = (int)($settings['maxMessages'] ?? 10);
  $maxPhotos   = (int)($settings['maxPhotos']   ?? 2);

  // Charger config prospect si lié à un subscriber
  $prospectConfig = null;
  if ($row['subscriber_id']) {
    $prospectConfig = guest_load_prospect_config((int)$row['subscriber_id']);
  }

  return [
    'id'             => $row['id'],
    'token_hash'     => $tokenHash,
    'messages_used'  => (int)$row['messages_used'],
    'photos_used'    => (int)$row['photos_used'],
    'max_messages'   => $maxMessages,
    'max_photos'     => $maxPhotos,
    'expires_at'     => strtotime($row['expires_at']),
    'subscriber_id'  => $row['subscriber_id'],
    'prospect_config'=> $prospectConfig,
  ];
}

/**
 * Incrémenter le compteur de messages d'une session
 * Retourne false si quota dépassé
 */
function guest_increment_message(int $sessionId): bool {
  $settings    = guest_settings();
  $maxMessages = (int)($settings['maxMessages'] ?? 10);

  $updated = db_query(
    'UPDATE agenia_guest_sessions
     SET messages_used = messages_used + 1
     WHERE id = ? AND messages_used < ?',
    [$sessionId, $maxMessages]
  )->rowCount();

  return $updated > 0;
}

/**
 * Incrémenter le compteur de photos d'une session
 * Retourne false si quota dépassé
 */
function guest_increment_photo(int $sessionId): bool {
  $settings  = guest_settings();
  $maxPhotos = (int)($settings['maxPhotos'] ?? 2);

  $updated = db_query(
    'UPDATE agenia_guest_sessions
     SET photos_used = photos_used + 1
     WHERE id = ? AND photos_used < ?',
    [$sessionId, $maxPhotos]
  )->rowCount();

  return $updated > 0;
}

/**
 * Marquer une session comme expirée et bloquer l'IP (option B)
 */
function guest_expire_and_block(int $sessionId, string $ip): void {
  $settings = guest_settings();
  $blockMin = (int)($settings['blockMinutes'] ?? GUEST_BLOCK_MIN);
  $ipHash   = guest_hash_ip($ip);
  $blockedUntil = date('Y-m-d H:i:s', time() + ($blockMin * 60));

  db_query(
    'UPDATE agenia_guest_sessions
     SET expires_at = NOW(), blocked_until = ?
     WHERE id = ?',
    [$blockedUntil, $sessionId]
  );
}

/**
 * Valider un token d'invitation (confirm_token de waitlist_subscribers)
 * Retourne subscriber_id ou false
 */
function guest_validate_invite(string $confirmToken): int|false {
  if (strlen($confirmToken) < 8) { return false; }

  $tokenHash = hash_hmac('sha256', $confirmToken, GUEST_HMAC_SECRET);

  $row = db_row(
    'SELECT id, uses, max_uses, expires_at, subscriber_id
     FROM agenia_invite_tokens
     WHERE token_hash = ?
       AND expires_at > NOW()
       AND uses < max_uses
     LIMIT 1',
    [$tokenHash]
  );

  if (!$row) { return false; }

  // Incrémenter le compteur d'usages
  db_query(
    'UPDATE agenia_invite_tokens SET uses = uses + 1 WHERE id = ?',
    [$row['id']]
  );

  return (int)$row['subscriber_id'];
}

/**
 * Créer un token d'invitation pour un subscriber
 * À appeler depuis l'interface admin
 */
function guest_create_invite(int $subscriberId): string {
  $raw       = bin2hex(random_bytes(32));
  $tokenHash = hash_hmac('sha256', $raw, GUEST_HMAC_SECRET);
  $expiresAt = date('Y-m-d H:i:s', time() + (INVITE_TTL_DAYS * 86400));

  // Désactiver les anciens tokens du même subscriber
  db_query(
    'UPDATE agenia_invite_tokens
     SET expires_at = NOW()
     WHERE subscriber_id = ? AND expires_at > NOW()',
    [$subscriberId]
  );

  db_query(
    'INSERT INTO agenia_invite_tokens
       (subscriber_id, token_hash, max_uses, expires_at)
     VALUES (?, ?, ?, ?)',
    [$subscriberId, $tokenHash, INVITE_MAX_USES, $expiresAt]
  );

  return $raw;
}

/**
 * Charger la config personnalisée d'un prospect
 * Priorité : BDD → config/guests.php (défaut)
 */
function guest_load_prospect_config(int $subscriberId): array {
  // 1. Chercher en BDD
  $row = db_row(
    'SELECT system_prompt, llm_config, modules
     FROM agenia_prospect_configs
     WHERE subscriber_id = ?',
    [$subscriberId]
  );

  // 2. Charger les défauts depuis config/guests.php
  $defaults = require __DIR__ . '/../config/guests.php';

  if (!$row) { return $defaults; }

  return array_merge($defaults, array_filter([
    'system_prompt' => $row['system_prompt'],
    'llm_config'    => $row['llm_config'] ? json_decode($row['llm_config'], true) : null,
    'modules'       => $row['modules']    ? json_decode($row['modules'],    true) : null,
  ]));
}