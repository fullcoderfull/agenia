<?php
// =============================================================================
// proxy-stt.php — Proxy STT (Faster-STT)
// Le JS envoie : fichier audio multipart
// Modèle, langue et URL restent côté serveur
// =============================================================================
require_once __DIR__ . '/check.php';
require_once __DIR__ . '/lib/debug.php';
require_once __DIR__ . '/config/secrets.php';

set_time_limit(120);
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405); echo json_encode(['error' => 'Method Not Allowed']); exit;
}

if (empty($_FILES['file'])) {
  http_response_code(400); echo json_encode(['error' => 'Fichier audio manquant']); exit;
}

// Chargement settings admin
$settings = [];
$sf = __DIR__ . '/config/settings.json';
if (file_exists($sf)) { $settings = json_decode(file_get_contents($sf), true) ?? []; }

$file     = $_FILES['file'];
$tmpPath  = $file['tmp_name'];
$origName = basename($file['name']);
$mime     = $file['type'] ?: 'audio/webm';

// Résolution config : settings > secrets
$sttModel = $settings['stt']['model'] ?? (defined('STT_MODEL') ? STT_MODEL : 'large-v3');

// Overrides cloud depuis le panel admin (envoyés dans le body FormData)
$overrideUrl    = isset($_POST['_stt_url'])    ? trim($_POST['_stt_url'])    : '';
$overrideBearer = isset($_POST['_stt_bearer']) ? trim($_POST['_stt_bearer']) : '';
$overrideModel  = isset($_POST['model'])       ? trim($_POST['model'])       : $sttModel;

if ($overrideUrl)    { $sttUrl    = $overrideUrl; } else { $sttUrl = defined('STT_URL') ? STT_URL : 'http://172.17.164.70:8990'; }
if ($overrideBearer) { $sttBearer = $overrideBearer; } else { $sttBearer = defined('STT_BEARER') ? STT_BEARER : ''; }
$sttModel = $overrideModel;
$sttLang  = $settings['stt']['lang']  ?? STT_LANG;

// Construction multipart
$boundary = '----VoxMindSTT' . bin2hex(random_bytes(8));
$body     = '';

$body .= "--{$boundary}\r\n";
$body .= "Content-Disposition: form-data; name=\"file\"; filename=\"{$origName}\"\r\n";
$body .= "Content-Type: {$mime}\r\n\r\n";
$body .= file_get_contents($tmpPath) . "\r\n";

foreach (['model' => $sttModel, 'language' => $sttLang, 'response_format' => 'json'] as $k => $v) {
  $body .= "--{$boundary}\r\n";
  $body .= "Content-Disposition: form-data; name=\"{$k}\"\r\n\r\n";
  $body .= $v . "\r\n";
}
$body .= "--{$boundary}--\r\n";

$headers = ["Content-Type: multipart/form-data; boundary={$boundary}"];
if ($sttBearer) { $headers[] = 'Authorization: Bearer ' . $sttBearer; }

$ctx = stream_context_create(['http' => [
  'method'        => 'POST',
  'header'        => implode("\r\n", $headers),
  'content'       => $body,
  'timeout'       => 120,
  'ignore_errors' => true,
]]);

$response = @file_get_contents($sttUrl . '/v1/audio/transcriptions', false, $ctx);

if ($response === false) {
  http_response_code(502); echo json_encode(['error' => 'STT inaccessible']); exit;
}

preg_match('/HTTP\/\S+\s+(\d+)/', $http_response_header[0] ?? '', $m);
http_response_code(isset($m[1]) ? (int)$m[1] : 200);
echo $response;
