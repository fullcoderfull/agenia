<?php 
// =============================================================================
// config/secrets.php — GÉNÉRÉ par setup.sh — NE JAMAIS COMMITTER
// =============================================================================

// ── Verrouillage d'instance ──────────────────────────────────────────────────
define('LICENSE_DOMAIN',     'agent.datacorners.com');
define('LICENSE_SIGNATURE',  '9425c775e6aecf3c51b2394ba5a162b58440dd734972a36447b81d43529ed47a');
define('LICENSE_MASTER_KEY', '2e038359ff77711bad827c6f18396a9de9fda951689dfe228b6dc69a163eb4c5');

// ── Sessions ────────────────────────────────────────────────────────────────
define('HMAC_SECRET',  '897188ef1ae3ffe4ec33684cd9521f4a60fd92b8e9f9e14d6f1c5dbed2be67e7');
define('GUEST_HMAC_SECRET',  '897188ef1ae3ffe4ec33684cd9521f4a60fd92b8e9f9e14d6f1c5dbed2be67e7');

define('SESSION_TTL',  3600);
define('GUEST_TTL',  360);
define('GUEST_BLOCK_MIN',  360);
define('USER_BLOCK_MIN',  3600);
// ── LLM (Ollama local par défaut) ───────────────────────────────────────────
define('LLM_URL',    'http://172.17.164.70:11434');
define('LLM_MODEL',  'AssistantQ14bI:latest');
define('LLM_KEY',    '');
define('LLM_BEARER',    '');
define('TTS_BEARER',    '');
define('STT_BEARER',    '');

// ── TTS ─────────────────────────────────────────────────────────────────────
define('TTS_URL',    'http://172.17.164.70:8880');
define('TTS_MODEL',  'kokoro');
define('TTS_VOICE',  'af_sky');

// ── STT ─────────────────────────────────────────────────────────────────────
define('STT_URL',    'http://172.17.164.70:8990');
define('STT_MODEL',  'AssistantQ14bI:latest');

// ── Authentification ────────────────────────────────────────────────────────
define('AUTH_BACKEND', 'file');  // 'file' ou 'mysql'

// ── MySQL (si AUTH_BACKEND === 'mysql') ──────────────────────────────────────
define('DB_HOST', '172.17.164.70');
define('DB_NAME', 'waitlist_datacorners.com');
define('DB_USER', 'waitlist_user');
define('DB_PASS', 'ZquSD252VGT9MhZ');

// ── UI ──────────────────────────────────────────────────────────────────────
define('UI_LANG', 'fr');

// ── Debug ───────────────────────────────────────────────────────────────────
define('DEBUG_LOG', false);

define('BUSINESS_SECRET', 'CHANGE_ME_32_CHARS_MIN');

