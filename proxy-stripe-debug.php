<?php
// =============================================================================
// proxy-stripe.php — Proxy webhook Stripe → Business API C#
// Version : V1.0
// Modifié : 2025-06-11
// Stripe envoie vers cette URL — on relaie vers le container C#
// Si le container est down → log + réponse 200 (Stripe ne retry pas en boucle)
// =============================================================================
error_reporting(0);
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/logs/boot_' . date('Y-m-d') . '.log');

require_once __DIR__ . '/config/secrets.php';
require_once __DIR__ . '/lib/debug.php';
require_once __DIR__ . '/lib/security.php';

header('Content-Type: application/json');

// ── Lire le payload brut — AVANT tout autre traitement ───────────────────────
$payload   = file_get_contents('php://input');
$signature = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

http_response_code(200);

debug_log('STRIPE_PROXY', 'RECEIVED', [
    'sig_present' => !empty($signature),
    'payload_size' => strlen($payload),
]);

// ── Validation basique ────────────────────────────────────────────────────────
if (empty($payload)) {
    http_response_code(400);
    echo json_encode(['error' => 'Payload vide']);
    exit;
}

if (empty($signature)) {
    debug_log('STRIPE_PROXY', 'NO_SIGNATURE');
    http_response_code(400);
    echo json_encode(['error' => 'Stripe-Signature manquant']);
    exit;
}

// ── Relayer vers C# API ───────────────────────────────────────────────────────
$port   = defined('BUSINESS_API_PORT') ? BUSINESS_API_PORT : 5005;
$secret = defined('BUSINESS_SECRET')   ? BUSINESS_SECRET   : '';
$url    = 'http://172.17.164.70:' . $port . '/stripe/webhook';

debug_log('WEBHOOK', 'FROM STRIPE', ['secret' => $secret,'session' => $result['sessionId'] ?? '', 'url' => $result['url']]);

$ctx = stream_context_create(['http' => [
    'method'        => 'POST',
    'header'        => implode("\r\n", [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $secret,
        'Stripe-Signature: ' . $signature,
    ]),
    'content'       => $payload,
    'timeout'       => 10,
    'ignore_errors' => true,
]]);

$response = @file_get_contents($url, false, $ctx);
$temp_file = tempnam(sys_get_temp_dir(), 'http_context');
file_put_contents($temp_file, serialize($ctx)); 
exec("php -r '\$ctx = " . addslashes(serialize($ctx)) . "; file_get_contents(\"" . addslashes($url) . "\", false, \$ctx);' > /dev/null 2>&1 &");
http_response_code(200);

// ── Container down — log et répondre 200 à Stripe ────────────────────────────
// Stripe considère un 200 comme succès — on évite les retries en cascade
if ($response === false) {
    debug_log('STRIPE_PROXY', 'CONTAINER_DOWN', [
        'url'     => $url,
        'payload' => substr($payload, 0, 200),
    ]);
    // Sauvegarder le webhook pour traitement différé
    _stripe_save_pending($payload, $signature);
    // Répondre 200 à Stripe pour stopper les retries
    http_response_code(200);
    echo json_encode(['ok' => true, 'queued' => true]);
    exit;
}

// ── Relayer la réponse du C# ──────────────────────────────────────────────────
$decoded = json_decode($response, true);
$status  = ($decoded && isset($decoded['ok']) && $decoded['ok']) ? 200 : 400;

debug_log('STRIPE_PROXY', 'RELAYED', [
    'status'  => $status,
    'event'   => $decoded['event'] ?? 'unknown',
]);

http_response_code($status);
echo $response;

// =============================================================================
// Sauvegarder les webhooks en attente si container down
// Traitement différé possible via cron ou au redémarrage du container
// =============================================================================
function _stripe_save_pending(string $payload, string $signature): void {
    $dir  = __DIR__ . '/logs/stripe-pending/';
    if (!is_dir($dir)) { @mkdir($dir, 0750, true); }
    $file = $dir . 'webhook_' . date('Ymd_His') . '_' . uniqid() . '.json';
    file_put_contents($file, json_encode([
        'ts'        => date('c'),
        'signature' => $signature,
        'payload'   => $payload,
    ]), LOCK_EX);
    debug_log('STRIPE_PROXY', 'PENDING_SAVED', ['file' => basename($file)]);
}