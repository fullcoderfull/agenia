<?php
// =============================================================================
// setup-generate.php — Genere config/secrets.php avec cles uniques
// Usage : php setup-generate.php mondomaine.com
// =============================================================================

$domain = $argv[1] ?? '';
if (!$domain) {
  echo "Usage : php setup-generate.php mondomaine.com\n";
  exit(1);
}

$masterKey  = bin2hex(random_bytes(32));
$hmacSecret = bin2hex(random_bytes(32));
$signature  = hash_hmac('sha256', $domain, $masterKey);

$content = '<?php
// =============================================================================
// config/secrets.php -- GENERE par setup -- NE JAMAIS COMMITTER
// =============================================================================

// -- Verrouillage instance --
define(\'LICENSE_DOMAIN\',     \'' . $domain . '\');
define(\'LICENSE_SIGNATURE\',  \'' . $signature . '\');
define(\'LICENSE_MASTER_KEY\', \'' . $masterKey . '\');

// -- Sessions --
define(\'HMAC_SECRET\',  \'' . $hmacSecret . '\');
define(\'SESSION_TTL\',  3600);

// -- LLM --
define(\'LLM_URL\',    \'http://172.17.164.70:11434\');
define(\'LLM_MODEL\',  \'AssistantQ14bI:latest\');
define(\'LLM_KEY\',    \'\');

// -- TTS --
define(\'TTS_URL\',    \'http://172.17.164.70:8880\');
define(\'TTS_MODEL\',  \'kokoro\');
define(\'TTS_VOICE\',  \'ff_siwis(0.9)+im_nicola(0.3)+af_nicole(0.3)\');

// -- STT --
define(\'STT_URL\',    \'http://172.17.164.70:8990\');
define(\'STT_MODEL\',  \'large-v3\');

// -- Authentification --
define(\'AUTH_BACKEND\', \'file\');

// -- MySQL (si AUTH_BACKEND === \'mysql\') --
define(\'DB_HOST\', \'127.0.0.1\');
define(\'DB_NAME\', \'voxmind\');
define(\'DB_USER\', \'voxmind_app\');
define(\'DB_PASS\', \'CHANGEME\');

// -- UI --
define(\'UI_LANG\', \'fr\');

// -- Debug --
define(\'DEBUG_LOG\', false);
';

// Backup si existe
$target = __DIR__ . '/config/secrets.php';
if (file_exists($target)) {
  copy($target, $target . '.bak');
  echo "[BACKUP] secrets.php.bak\n";
}

file_put_contents($target, $content);

echo "\n";
echo "[OK] secrets.php genere\n";
echo "  Domaine    : $domain\n";
echo "  Signature  : " . substr($signature, 0, 16) . "...\n";
echo "  Master Key : ********** (dans secrets.php)\n";
echo "\n";