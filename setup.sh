#!/bin/bash
# =============================================================================
# VoxMind — Setup script pour self-hosting
# Génère la master key, la signature et le secrets.php initial
# =============================================================================

echo ""
echo "  ╔═══════════════════════════════════════╗"
echo "  ║       AGENT PRO — Self-Host Setup       ║"
echo "  ╚═══════════════════════════════════════╝"
echo ""

# 1. Domaine
read -p "  Domaine (ex: agent.mondomaine.com) : " DOMAIN
if [ -z "$DOMAIN" ]; then
  echo "  ❌ Domaine requis"; exit 1
fi

# 2. Master Key — 64 chars aléatoires
MASTER_KEY=$(openssl rand -hex 32)
echo "  ✅ Master key générée"

# 3. Signature
SIGNATURE=$(echo -n "$DOMAIN" | openssl dgst -sha256 -hmac "$MASTER_KEY" | awk '{print $2}')
echo "  ✅ Signature : $SIGNATURE"

# 4. HMAC Secret pour les sessions
HMAC_SECRET=$(openssl rand -hex 32)

# 5. Écrire secrets.php
if [ -f "config/secrets.php" ]; then
  cp config/secrets.php config/secrets.php.bak
  echo "  📋 Backup : config/secrets.php.bak"
fi

cat > config/secrets.php << PHPEOF
<?php
// =============================================================================
// config/secrets.php — GÉNÉRÉ par setup.sh — NE JAMAIS COMMITTER
// =============================================================================

// ── Verrouillage d'instance ──────────────────────────────────────────────────
define('LICENSE_DOMAIN',     '$DOMAIN');
define('LICENSE_SIGNATURE',  '$SIGNATURE');
define('LICENSE_MASTER_KEY', '$MASTER_KEY');

// ── Sessions ────────────────────────────────────────────────────────────────
define('HMAC_SECRET',  '$HMAC_SECRET');
define('GUEST_HMAC_SECRET',  '$HMAC_SECRET');

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

PHPEOF

echo ""
echo "  ╔═══════════════════════════════════════╗"
echo "  ║          Setup terminé ✅              ║"
echo "  ╠═══════════════════════════════════════╣"
echo "  ║  Domaine    : $DOMAIN"
echo "  ║  Master Key : **********  (dans secrets.php)"
echo "  ║  Signature  : ${SIGNATURE:0:16}..."
echo "  ╠═══════════════════════════════════════╣"
echo "  ║  Prochaines étapes :                  ║"
echo "  ║  1. Éditez config/users.php           ║"
echo "  ║  2. Configurez votre LLM/TTS/STT      ║"
echo "  ║  3. Redémarrez IIS/Nginx              ║"
echo "  ╚═══════════════════════════════════════╝"
echo ""