<?php
// =============================================================================
// user-init.php — Crée une session guest et pose le cookie vox_guest
// Version : V1.0
// Modifié : 2025-06-11
// Prérequis : agenia_tables.sql exécuté + secrets.php configuré
// =============================================================================
error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/logs/boot_' . date('Y-m-d') . '.log');

require_once __DIR__ . '/config/secrets.php';
require_once __DIR__ . '/lib/debug.php';      // ← nécessaire pour debug_log
require_once __DIR__ . '/lib/security.php';
require_once __DIR__ . '/lib/db.php';         // ← avant auth_guest
require_once __DIR__ . '/lib/db_helpers.php'; // ← avant auth_guest
require_once __DIR__ . '/lib/auth_guest.php';

debug_log('USER_INIT', 'START', ['uri' => $_SERVER['REQUEST_URI'] ?? '']);

// ── IP réelle ─────────────────────────────────────────────────────────────────
$ip = get_client_ip();

// ── Rate limit ────────────────────────────────────────────────────────────────
if (!rl_check('guest_init_' . $ip, 3, 600)) {
    debug_log('USER_INIT', 'RATE_LIMIT', ['ip' => $ip]);
    header('Location: /login.php?error=rate_limit');
    exit;
}

// ── IP bloquée ────────────────────────────────────────────────────────────────
try {
    if (guest_ip_is_blocked($ip)) {
        $settings = json_decode(file_get_contents(__DIR__ . '/config/settings.json'), true);
        $blockMin = (int)($settings['guest']['blockMinutes'] ?? 60);
        debug_log('USER_INIT', 'IP_BLOCKED', ['ip' => $ip, 'blockMin' => $blockMin]);
        header('Location: /login.php?error=blocked&wait=' . $blockMin);
        exit;
    }
} catch (Exception $e) {
    // Table absente ou BDD inaccessible — laisser passer pour le message d'erreur clair
    debug_log('USER_INIT', 'DB_CHECK_FAILED', ['err' => $e->getMessage()]);
}
///////////////////////CONNECTION ACTIVE
try {
    $db = db();
    
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM agenia_users_sessions WHERE expires_at > NOW()");

    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $activeSessions = $result['count'];
    
    $settings = json_decode(file_get_contents(__DIR__ . '/config/settings.json'), true);
    $maxConnections = (int)($settings['user']['maxConnections'] ?? 5); // Définissez la limite de connexions max

    if ($activeSessions >= $maxConnections) {
        debug_log('USER_INIT', 'MAX_CONNECTIONS_REACHED', ['activeSessions' => $activeSessions, 'maxConnections' => $maxConnections]);
        header('Location: /login.php?error=max_connections_reached');
        exit;
    }
} catch (Exception $e) {
    debug_log('USER_INIT', 'CONNECTION_CHECK_FAILED', ['err' => $e->getMessage()]);
    header('Location: /login.php?error=db_error');
    exit;
}
/////////////////////////////////////////

// ── Token d'invitation optionnel ──────────────────────────────────────────────
$subscriberId = null;
$inviteRaw    = isset($_GET['invite']) ? trim($_GET['invite']) : '';

if ($inviteRaw !== '') {
    try {
        $subId = guest_validate_invite($inviteRaw);
        if ($subId === false) {
            debug_log('USER_INIT', 'INVITE_INVALID', ['token' => substr($inviteRaw, 0, 8)]);
            header('Location: /login.php?error=invite_invalid');
            exit;
        }
        $subscriberId = $subId;
        debug_log('USER_INIT', 'INVITE_OK', ['subscriber' => $subscriberId]);
    } catch (Exception $e) {
        debug_log('USER_INIT', 'INVITE_ERROR', ['err' => $e->getMessage()]);
        header('Location: /login.php?error=invite_invalid');
        exit;
    }
}

// ── Créer la session ──────────────────────────────────────────────────────────
try {
    $result = guest_create($ip, $subscriberId);
} catch (Exception $e) {
    debug_log('USER_INIT', 'CREATE_EXCEPTION', ['err' => $e->getMessage()]);
    header('Location: /login.php?error=db_error');
    exit;
}

debug_log('USER_INIT', 'CREATE_RESULT', [
    'ok'    => !isset($result['error']),
    'error' => $result['error'] ?? null,
]);

if (isset($result['error'])) {
    header('Location: /login.php?error=' . urlencode($result['error']));
    exit;
}

// ── Poser le cookie vox_guest ─────────────────────────────────────────────────
setcookie('vox_guest', $result['token'], [
    'expires'  => $result['expires_at'],
    'path'     => '/',
    'secure'   => true,
    'httponly' => true,
    'samesite' => 'Strict',
]);

debug_log('USER_INIT', 'COOKIE_SET', [
    'expires_in' => $result['ttl'],
    'subscriber' => $subscriberId,
]);

// ── Redirect vers l'app ───────────────────────────────────────────────────────
header('Location: /index.php');
exit;