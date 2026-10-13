<?php
function writeLog($message) {
 
    $date = date('Y-m-d');
   
    $logFile = 'logs/log_' . $date . '.log';
    
    // Ouvrir le fichier en mode append (+ si le fichier n'existe pas, il est créé)
    $file = fopen($logFile, 'a+');
    
    // Écrire le message dans le fichier
    fwrite($file, $message . PHP_EOL);
    
    // Fermer le fichier
    fclose($file);
}
// =============================================================================
// proxy-llm.php — Proxy LLM : construit le body complet côté serveur
// Le JS n'envoie que : messages[], image (optionnel), stream (bool)
// Toute la logique métier est ici — jamais exposée au client
// =============================================================================
require_once __DIR__ . '/check.php';
require_once __DIR__ . '/lib/debug.php';
require_once __DIR__ . '/config/secrets.php';
require_once __DIR__ . '/lib/audit.php'; // AGENIA-OBS v1

$settingsFile = __DIR__ . '/config/settings.json';
$settings     = [];
if (file_exists($settingsFile)) {
  $settings = json_decode(file_get_contents($settingsFile), true) ?? [];
}

// Clé API dynamique (provider cloud, ex: OpenRouter) — sinon fallback Ollama local
$overridesFile = __DIR__ . '/data/admin_sensitive.json';
$overrides = file_exists($overridesFile)
    ? (json_decode(file_get_contents($overridesFile), true) ?: [])
    : [];

$resolvedApiKey = !empty($overrides['llmKey'])
    ? $overrides['llmKey']
    : (defined('LLM_BEARER') ? LLM_BEARER : '');

// Résolution des paramètres : body JS > settings.json > secrets.php
$model        = $settings['llm']['model']        ?? LLM_MODEL;
$visionModel  = $settings['llm']['visionModel']  ?? (defined('LLM_MODEL_VISION') ? LLM_MODEL_VISION : 'llava');
$noThink      = $settings['llm']['noThink']      ?? (defined('LLM_NO_THINK') ? LLM_NO_THINK : false);
$keepAlive    = defined('LLM_KEEP_ALIVE') ? LLM_KEEP_ALIVE : -1;

// ── Provider actif depuis settings.json ──────────────────────────────────────
$providerActive = $settings['providers']['llm']['active'] ?? 'ollama';
$providerList   = $settings['providers']['llm']['list']   ?? [];
$providerCfg    = $providerList[$providerActive]           ?? [];
$providerUrl    = $providerCfg['url']                      ?? $settings['llm']['url'] ?? LLM_URL;
$providerLlmModel = $overrides['llmModel'] ?? '';
$model = $providerLlmModel;

// ── Désactiver tout buffer (streaming IIS) ───────────────────────────────────
@ini_set('output_buffering',        'Off');
@ini_set('zlib.output_compression', 'Off');
@ini_set('implicit_flush',          '1');
@ini_set('default_socket_timeout',  '300');
ob_implicit_flush(true);
if (ob_get_level()) { ob_end_clean(); }
set_time_limit(0);  // illimité — le LLM peut prendre du temps

header('Content-Type: application/x-ndjson');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('X-Accel-Buffering: no');
header('X-Content-Type-Options: nosniff');
// IIS — désactiver la compression dynamique sur cette réponse
header('Content-Encoding: none');

// ── Lecture payload JS ────────────────────────────────────────────────────────
$raw     = file_get_contents('php://input');
$payload = json_decode($raw, true);

// AGENIA-OBS : resolution corrigee. Reglages admin (data/admin_sensitive.json) > settings.json > defaut.
// Les surcharges envoyees par le navigateur ne sont acceptees que pour un admin (evite qu'un
// utilisateur choisisse une URL arbitraire cote serveur).
$isAdminCaller = !empty($GLOBALS['isAdmin']) || !empty($GLOBALS['isSuperAdmin']);
$llmProvider = (string)($overrides['llmProvider'] ?? ($settings['providers']['llm']['active'] ?? 'ollama'));
if ($isAdminCaller && !empty($payload['_llm_provider'])) { $llmProvider = (string)$payload['_llm_provider']; }
$providerActive = $llmProvider;
$providerCfg = $providerList[$llmProvider] ?? [];
$llmUrl = $providerUrl = (string)($providerCfg['url'] ?? ($overrides['llmUrl'] ?? ($settings['llm']['url'] ?? LLM_URL)));
if ($isAdminCaller && !empty($payload['_llm_url'])) { $llmUrl = $providerUrl = (string)$payload['_llm_url']; }
$providerLlmModel = (string)($overrides['llmModel'] ?? ($settings['llm']['model'] ?? ''));
if ($isAdminCaller && !empty($payload['_llm_model'])) { $providerLlmModel = (string)$payload['_llm_model']; }
if (trim($providerLlmModel) === '') { $providerLlmModel = 'AssistantQ14bI:latest'; }
$model = $providerLlmModel;
$resolvedApiKey = (string)($overrides['llmKey'] ?? '');
if ($isAdminCaller && !empty($payload['_llm_key'])) { $resolvedApiKey = (string)$payload['_llm_key']; }

// ── Clé API — transmise par le frontend via X-Provider-Key ───────────────────
$providerKey = $resolvedApiKey; // AGENIA-OBS

$ah = $_SERVER['HTTP_X_PROVIDER_KEY'] ?? '';
if ($isAdminCaller && $ah && strlen($ah) > 8 && preg_match('/^[a-zA-Z0-9\-_\.]+$/', $ah) && strlen($ah) <= 200) {
  $providerKey = $ah;
}

// try {
//       // Charger les overrides admin (sauvegardés via le panel front → config_save_sensitive)
// $overridesFile = __DIR__ . '/data/admin_sensitive.json';
// $overrides = file_exists($overridesFile)
//     ? (json_decode(file_get_contents($overridesFile), true) ?: [])
//     : [];

// $providerUrl = $overrides['llmUrl']
//     ?? ($providerCfg['url'] ?? $settings['llm']['url'] ?? LLM_URL);

// $providerKey = $overrides['llmKey'] ?? '';

// $llmUrl = $providerUrl;
// $llmProvider =  $overrides['llmProvider'];
// } catch (\Throwable $th) {
//   //throw $th;
// }

// ── Surcharge URL depuis le frontend (champ éditable) ────────────────────────


// Quota partenaire
if ($GLOBALS['isPartner'] && $GLOBALS['partnerData']) {
  $pid   = $GLOBALS['partnerData']['id'];
  $quota = $GLOBALS['partnerData']['quota'];
  $partners = require __DIR__ . '/config/partners.php';
  if (!quota_check($pid, 'llm', $quota)) {
    http_response_code(429);
    echo json_encode(['error' => 'Quota LLM journalier atteint (' . $quota . ')']);
    exit;
  }
  security_log('partner_llm_call', ['partner' => $pid, 'used' => quota_get($pid, 'llm')]);
}

debug_request('LLM');

// ── Probe santé ───────────────────────────────────────────────────────────────
if (isset($_GET['probe'])) {
  header('Content-Type: application/json');
  if ($providerKey) {
    $h[] = 'Authorization: Bearer ' . $providerKey;
    $h[] = 'x-api-key: ' . $providerKey; 
  }
  
  $ctx = stream_context_create(['http' => [
    'method'        => 'GET',
    'header'        => implode("\r\n", $h),
    'timeout'       => 4,
    'ignore_errors' => true,
  ]]);
  $r = @file_get_contents($llmUrl. '/api/tags', false, $ctx);
  http_response_code($r !== false ? 200 : 502);
  echo $r !== false ? '{"ok":true}'  : '{"error":"unreachable","url":"' . htmlspecialchars($llmUrl) . '"}';
  exit;
}



if (!$payload || empty($payload['messages'])) {
  http_response_code(400);
  echo json_encode(['error' => 'Payload invalide']);
  exit;
}

$jsMessages = $payload['messages'];       // historique brut du JS
$imageB64   = $payload['image']  ?? null; // base64 JPEG optionnel
$stream     = $payload['stream'] ?? true;

// ── Chargement config admin (settings.json) ───────────────────────────────────
// La config admin est prioritaire sur secrets.php




// System prompt : body JS (admin panel) > settings.json > secrets.php
$systemPromptFromJs = trim((string)($overrides['systemPrompt'] ?? '')); // AGENIA-OBS : prompt du volet admin
$systemPrompt = $systemPromptFromJs
             ?: ($settings['llm']['systemPrompt'] ?? '')
             ?: (defined('LLM_SYSTEM_PROMPT') ? LLM_SYSTEM_PROMPT : '');

// ── Construction des messages ─────────────────────────────────────────────────
$messages = [];

// System prompt
if ($systemPrompt) {
  $messages[] = ['role' => 'system', 'content' => $systemPrompt];
}

if (!is_array($jsMessages) && !is_countable($jsMessages)) {
    $jsMessages = [$jsMessages]; // ou $jsMessages = [];
}

// Historique + image sur le dernier message si fournie
$lastIndex = count($jsMessages) - 1;
foreach ($jsMessages as $i => $msg) {
  $role    = $msg['role']    ?? 'user';
  $content = $msg['content'] ?? '';

  // Pas de préfixe /no_think — géré via options du body

  $entry = ['role' => $role, 'content' => $content];

  // Image sur le dernier message user
  if ($imageB64 && $i === $lastIndex && $role === 'user') {
    $entry['images'] = [$imageB64];
  }

  $messages[] = $entry;
}

// ── Body final LLM ─────────────────────────────────────────────────────────
$body = [
  'model'      => $providerLlmModel,
  'messages'   => $messages,
  'stream'     => (bool)$stream,
  'keep_alive' => $keepAlive,
  'think' => $settings['llm']['noThink'],
  "max_tokens" => 2048,
  "temperature" => 0.7
];
debug_log('PROXY_LLM', 'IS STREAM MODE', ['stream' => $body['stream'], 'url' => $llmUrl]);
// Désactiver le thinking via options Ollama (Qwen3, DeepSeek-R1)
// Plus fiable que le préfixe texte /no_think
if ($noThink) {
  $body['options'] = ['think' => false];
}

$headers = [
  'Content-Type: application/json',
  'Accept: application/x-ndjson',
];

//writeLog("====\n".print_r($body,true));
if ($providerKey) { $headers[] = 'Authorization: Bearer ' . $providerKey; $headers[] = 'x-api-key: ' . $providerKey;}
debug_log('PROXY_LLM', 'MODEL_REQUEST', ['model' => $body['model'], 'url' => $llmUrl]);
$ctx = stream_context_create(['http' => [
  'method'        => 'POST',
  'header'        => implode("\r\n", $headers),
  'content'       => json_encode($body),
  'timeout'       => 300,
  'ignore_errors' => true,
]]);

//writeLog("=====>220 : $providerUrl");
//writeLog("=====>221 : $providerKey");
//writeLog("=====>220 : var_dump($ctx)" );

$chatPath = (strtolower($llmProvider) === 'ollama') ? '/api/chat' : '/v1/chat/completions';
// AGENIA-OBS plus de contenu journalise : writeLog("proxy-llm.php=====>225 : ".$llmUrl . $chatPath);
// AGENIA-OBS plus de contenu journalise : writeLog("proxy-llm.php=====>226 : ".$llmProvider);

// AGENIA-OBS : ce qui est REELLEMENT utilise, lu par api.php pour le journal
header('X-Agenia-Provider: ' . preg_replace('/[^a-z0-9_\-]/i', '', $llmProvider));
header('X-Agenia-Model: ' . preg_replace('/[^\w\.\-:\/]/', '', $providerLlmModel));
header('X-Agenia-Local: ' . (empty($providerList[$llmProvider]['requiresKey']) ? '1' : '0'));
$streamHandle = @fopen($llmUrl . $chatPath, 'r', false, $ctx);
if (!$streamHandle) {
  http_response_code(502);
  echo json_encode(['error' => 'LLM inaccessible']);
  exit;
}

if($llmProvider != 'ollama'){
  //var_dump(fopen("http://agenia.datacorners.com/mock-openrouter.php","r"));
   // $streamHandle = @fopen("http://agenia.datacorners.com/mock-openrouter.php", 'r', false, $ctx);

    //writeLog("proxy-llm.php=====>246 : ".$line);

    if (!$streamHandle) {
        die("stream failed");
    }
    $startTime = microtime(true);
    while (($line = fgets($streamHandle)) !== false) {
        
        
        
        if ($line === false) {
            continue;
        }
      // AGENIA-OBS plus de contenu journalise : writeLog("proxy-llm.php===START==>246 :".   $line);
       //    $line = "\\" . trim($line);
        
        if ($line === '') continue;

        if (!str_starts_with($line, 'data:')) {
         //return;
        }
        
        $payload = trim(substr($line, 5));
       
        $json = json_decode($payload, true);

        if ($payload === '[DONE]') {
              break;
          }

         $content = $json['choices'][0]['delta']['content'] ?? '';

       // debug_log('PROXY_LLM', 'RESPONSE CONTENT', ['body' => var_dump($body), 'content' => $content,'FULL $line' => $line]);
         
        if ($content !== '') {
        $payload1 = json_encode(['model' => $model, 'created_at' => gmdate('c'), 'message' => ['role' => 'assistant', 'content' => $content], 'done' => false], JSON_UNESCAPED_UNICODE); // AGENIA-OBS : JSON valide meme avec guillemets/retours ligne
         echo $payload1 . "\n";
        foreach (str_split($payload1) as $lettre) {
            ;//echo $lettre;
        }
        //echo "\n";
         flush();
                  
        }



    }
    $endTime = microtime(true);
    $duration = (int)(($endTime - $startTime) * 1_000_000); // microseconds

    

    $end = '{"model":"'.$model.'","created_at":"'.gmdate("c",(int)$endTime).'","message":{"role":"assistant","content":""},"done":true}';
    // AGENIA-OBS plus de contenu journalise : writeLog("proxy-llm.php===END==>294 :".   $end);
    $len = strlen($end);

        echo $end. "\n";
        flush();
    
     
}
// ── Stream vers le navigateur ─────────────────────────────────────────────────
// fgets lit une ligne JSON complète — préserve l'UTF-8 multi-bytes
else
  while (!feof($streamHandle)) {
    $chunk = fread($streamHandle, 1);
    if ($chunk === false || $chunk === '') { break; }
    echo $chunk;
    //writeLog("proxy-llm.php=====>291: ".$chunk);
    flush();
  }
fclose($streamHandle);
