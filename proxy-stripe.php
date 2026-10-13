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
$port   = defined('BUSINESS_API_PORT') ? BUSINESS_API_PORT : 5005;
$secret = defined('BUSINESS_SECRET')   ? BUSINESS_SECRET   : '';

$payload   = file_get_contents('php://input');
$signature = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

$payload_data = json_decode($payload, true);



$event_type = $payload_data['type'];
// $event_id = $payload_data['id'];
// $data = $payload_data['data']['object'];
// $data_type = gettype($data);

debug_log('STRIPE_PROXY', 'PAYLOAD', [
    'RETURN STRIPE TYPE' => $payload_data,
    'payload_size' => strlen($payload),
]);

// ── Validation basique ────────────────────────────────────────────────────────
if (empty($payload)) {
    http_response_code(401);
    echo json_encode(['error' => 'Payload vide']);
    exit;
}

if (empty($signature)) {
    debug_log('STRIPE_PROXY', 'NO_SIGNATURE');
    http_response_code(403);
    echo json_encode(['error' => 'Stripe-Signature manquant']);
    exit;
}

if ( $event_type == "checkout.session.completed"){

    
    $url    = 'http://172.17.164.70:' . $port . '/stripe/webhook';
    $url    = 'http://localhost:' . $port . '/stripe/webhook';

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

}
else {
    debug_log('ERREUR STRIPE_PROXY', 'NON WEBHOOK', [
    'RETURN STRIPE TYPE' => $event_type,
    'payload_size' => strlen($payload),
]);
}


http_response_code(200);
