<?php
// =============================================================================
// checkout.php — Redirection vers Stripe Checkout
// Version : V1.1
// Modifié : 2025-06-13
// =============================================================================
error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/logs/boot_' . date('Y-m-d') . '.log');

require_once __DIR__ . '/check.php';
require_once __DIR__ . '/lib/debug.php';
require_once __DIR__ . '/business/BusinessClient.php';

$lang  = defined('UI_LANG') ? UI_LANG : 'fr';
$isFr  = $lang === 'fr';
$plan  = $_GET['plan'] ?? 'pro_monthly';

if (!in_array($plan, ['pro_monthly', 'pro_yearly'])) { $plan = 'pro_monthly'; }

$userId = $GLOBALS['authUser'] ?? '';
$email  = '';

// ── Récupérer l'email ─────────────────────────────────────────────────────────

// 0. Depuis checkout-email.php (GET)
if (!empty($_GET['email']) && filter_var($_GET['email'], FILTER_VALIDATE_EMAIL)) {
    $email = trim($_GET['email']);
}

if (empty($email)) {
    try {
        // 1. Depuis waitlist_subscribers
        $row   = db_row('SELECT email FROM waitlist_subscribers WHERE id = ? OR email = ? LIMIT 1', [$userId, $userId]);
        $email = $row['email'] ?? '';

        // 2. Depuis users.php
        if (empty($email)) {
            $_vox_users = require __DIR__ . '/config/users.php';
            $email = $_vox_users[$userId]['email'] ?? '';
        }
    } catch (Exception $e) {
        debug_log('CHECKOUT', 'DB_ERROR', ['err' => $e->getMessage()]);
    }
}

// 3. Guest sans email → formulaire email
if (empty($email) && ($GLOBALS['isGuest'] ?? false)) {
    header('Location: /checkout-email.php?plan=' . $plan);
    exit;
}

// 4. userId est un email
if (empty($email) && filter_var($userId, FILTER_VALIDATE_EMAIL)) {
    $email = $userId;
}

// ── Créer la session Stripe ───────────────────────────────────────────────────
$host       = $_SERVER['HTTP_HOST'] ?? '';
$successUrl = 'https://' . $host . '/checkout-success.php';
$cancelUrl  = 'https://' . $host . '/index.php';

debug_log('CHECKOUT', 'START', ['user' => $userId, 'email' => $email, 'plan' => $plan]);

$result = business()->createCheckout($userId, $email, $plan, $successUrl, $cancelUrl);

if (!isset($result['ok']) || !$result['ok'] || empty($result['url'])) {
    debug_log('CHECKOUT', 'ERROR', ['result' => $result]);
    header('Location: /index.php?error=checkout_failed');
    exit;
}

debug_log('CHECKOUT', '<<REDIRECT<<', ['session' => $result['sessionId'] ?? '', 'url' => $result['url']]);




header('Location: ' . $result['url']);
exit;