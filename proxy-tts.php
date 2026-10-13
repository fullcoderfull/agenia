<?php
// =============================================================================
// proxy-tts.php — Proxy TTS OpenAI-compatible
// Le JS envoie : input (texte), lang (fr|en), format (optionnel)
// Les credentials, URL et voix restent côté serveur — jamais exposés au front
// =============================================================================
require_once __DIR__ . '/check.php';
require_once __DIR__ . '/lib/debug.php';
require_once __DIR__ . '/config/secrets.php';

// Quota TTS partenaire
if ($GLOBALS['isPartner'] && $GLOBALS['partnerData']) {
    $pid      = $GLOBALS['partnerData']['id'];
    $partners = require __DIR__ . '/config/partners.php';
    $quotaTts = $partners[$pid]['quota_tts'] ?? 100;
    if (!quota_check($pid, 'tts', $quotaTts)) {
        http_response_code(429);
        echo json_encode(['error' => 'Quota TTS journalier atteint (' . $quotaTts . ')']);
        exit;
    }
}

set_time_limit(60);
debug_request('TTS');

// ── Health check ──────────────────────────────────────────────────────────────
if (isset($_GET['health'])) {
    header('Content-Type: application/json');
    $h   = TTS_BEARER ? ['Authorization: Bearer ' . TTS_BEARER] : [];
    $ctx = stream_context_create(['http' => [
        'method'        => 'GET',
        'header'        => implode("\r\n", $h),
        'timeout'       => 4,
        'ignore_errors' => true,
    ]]);
    $r = @file_get_contents(TTS_URL . '/health', false, $ctx);
    http_response_code($r !== false ? 200 : 502);
    echo $r !== false ? '{"ok":true}' : '{"error":"unreachable"}';
    exit;
}

// ── Payload JS ────────────────────────────────────────────────────────────────
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (empty($data['input'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Paramètre input manquant']);
    exit;
}

// ── Chargement settings admin ─────────────────────────────────────────────────
$settings = [];
$sf = __DIR__ . '/config/settings.json';
if (file_exists($sf)) {
    $settings = json_decode(file_get_contents($sf), true) ?? [];
}

// ── Résolution de la voix ─────────────────────────────────────────────────────
// Le front envoie uniquement lang=fr|en (ou rien)
// Les identifiants de voix restent côté serveur dans settings.json / secrets.php
$lang = $data['lang'] ?? 'fr';   // fr = Célia, en = Alex

// Voix par langue — définies ici uniquement, jamais exposées au front
$voiceMap = [
    'fr' => $settings['tts']['voice']   ?? TTS_VOICE,   // ff_siwis(0.9)+... depuis settings
    'en' => $settings['tts']['voiceEn'] ?? 'am_adam',   // voix EN — à ajouter dans settings.json si besoin
];
$voice = $voiceMap[$lang] ?? $voiceMap['fr'];

// Fallback : si le front envoie voice explicitement (admin avancé) et que c'est autorisé
// On ignore — la voix est toujours résolue côté serveur


if (preg_match('/tool@/', ($data['input']))) {
     echo json_encode(['occurence tts trouvé' => "continuer"]);
}
// ── Construction du body TTS ──────────────────────────────────────────────────
$body = [
    'model'           => TTS_MODEL,
    'input'           => $data['input'],
    'voice'           => $voice,
    'response_format' => $data['format'] ?? ($settings['tts']['format'] ?? TTS_FORMAT),
    'think'           => false,
];

// Instruct : préfixe si activé dans settings
if (!empty($settings['tts']['instruct']) && !empty($settings['tts']['instructPrompt'])) {
    $body['input'] = $settings['tts']['instructPrompt'] . ' ' . $body['input'];
    //$body['input'] = preg_replace('/(tool@)/i', '', $body['input']);
    //$body['input'] = preg_replace('/(Tool@)/i', '', $body['input']);
}

debug_log('TTS', 'REQUEST', [
    'lang'   => $lang,
    'voice'  => $voice,
    'format' => $body['response_format'],
    'len'    => strlen($body['input']),
]);

// ── Appel TTS ─────────────────────────────────────────────────────────────────
$headers = ['Content-Type: application/json'];
if (TTS_BEARER) { $headers[] = 'Authorization: Bearer ' . TTS_BEARER; }

$ctx = stream_context_create(['http' => [
    'method'        => 'POST',
    'header'        => implode("\r\n", $headers),
    'content'       => json_encode($body),
    'timeout'       => 60,
    'ignore_errors' => true,
]]);

$response = @file_get_contents(TTS_URL . '/v1/audio/speech', false, $ctx);

if ($response === false) {
    http_response_code(502);
    echo json_encode(['error' => 'TTS inaccessible']);
    exit;
}

// ── Vérifier que la réponse est bien de l'audio ───────────────────────────────
// Certains serveurs retournent du JSON d'erreur avec un 200
$firstBytes = substr($response, 0, 4);
$isJson = ($firstBytes[0] === '{' || $firstBytes[0] === '[');
if ($isJson) {
    $errData = json_decode($response, true);
    $errMsg  = $errData['detail']['message'] ?? ($errData['error'] ?? 'Erreur TTS inconnue');
    debug_log('TTS', 'SERVER_ERROR', ['response' => substr($response, 0, 200)]);
    http_response_code(502);
    echo json_encode(['error' => $errMsg]);
    exit;
}

// ── Transmettre l'audio ───────────────────────────────────────────────────────
$contentType = 'audio/mpeg';
foreach ($http_response_header as $h) {
    if (stripos($h, 'Content-Type:') === 0) {
        $contentType = trim(substr($h, 13));
        break;
    }
}

header('Content-Type: ' . $contentType);
header('Cache-Control: no-cache');
echo $response;