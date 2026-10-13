<?php
// =============================================================================
// config/secrets.php — GÉNÉRÉ par setup.sh — NE JAMAIS COMMITTER
// =============================================================================

// ── Verrouillage d'instance ──────────────────────────────────────────────────
define('LICENSE_DOMAIN',     'CHANGEME');
define('LICENSE_SIGNATURE',  'CHANGEME');
define('LICENSE_MASTER_KEY', 'CHANGEME');

// ── Sessions ────────────────────────────────────────────────────────────────
define('HMAC_SECRET',  'CHANGEME');
define('GUEST_HMAC_SECRET',  'CHANGEME');

define('SESSION_TTL',  3600);
define('GUEST_TTL',  360);
define('GUEST_BLOCK_MIN',  360);
define('USER_BLOCK_MIN',  3600);
// ── LLM (Ollama local par défaut) ───────────────────────────────────────────
define('LLM_URL',    'CHANGEME');
define('LLM_MODEL',  'CHANGEME');
define('LLM_KEY',    'CHANGEME');
define('LLM_BEARER',    'CHANGEME');
define('TTS_BEARER',    'CHANGEME');
define('STT_BEARER',    'CHANGEME');

// ── TTS ─────────────────────────────────────────────────────────────────────
define('TTS_URL',    'CHANGEME');
define('TTS_MODEL',  'CHANGEME');
define('TTS_VOICE',  'CHANGEME');

// ── STT ─────────────────────────────────────────────────────────────────────
define('STT_URL',    'CHANGEME');
define('STT_MODEL',  'CHANGEME');

// ── Authentification ────────────────────────────────────────────────────────
define('AUTH_BACKEND', 'CHANGEME');  // 'file' ou 'mysql'

// ── MySQL (si AUTH_BACKEND === 'mysql') ──────────────────────────────────────
define('DB_HOST', 'CHANGEME');
define('DB_NAME', 'CHANGEME');
define('DB_USER', 'CHANGEME');
define('DB_PASS', 'CHANGEME');

// ── UI ──────────────────────────────────────────────────────────────────────
define('UI_LANG', 'CHANGEME');

// ── Debug ───────────────────────────────────────────────────────────────────
define('DEBUG_LOG', false);

define('BUSINESS_SECRET', 'CHANGEME');

