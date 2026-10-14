<?php
declare(strict_types=1);

//ob_start();                  // ← capture tout ce qui pourrait sortir
error_reporting(E_ALL); ini_set('display_errors', '0'); ini_set('log_errors', '1'); ini_set('error_log', __DIR__ . '/../logs/php_errors.log'); // AGENIA-OBS : erreurs journalisees, jamais affichees          // ← silence les warnings en production
// =============================================================================
// backend/api.php — Point d'entrée unique back-end
// Sécurité : check.php valide le rôle avant tout traitement
// Aucune info d'architecture, modèle ou clé n'est exposée au front
// =============================================================================


require_once __DIR__ . '/../check.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/db_helpers.php';
require_once __DIR__ . '/../lib/debug.php';
require_once __DIR__ . '/../lib/audit.php'; // AGENIA-OBS v1

// ── Headers sécurité ─────────────────────────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache');

// ── Méthode ──────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiError(405, '⚠ Méthode non autorisée.', false, 'llm');
}

// ── Lecture body ─────────────────────────────────────────────────────────────
$raw  = file_get_contents('php://input');

$body = json_decode($raw, true);
if ($body === null) {
    apiError(400, '⚠ Corps de requête JSON invalide- body null.', false, 'llm');
}

// ── Contexte auth injecté par check.php ──────────────────────────────────────
$authRole     = $GLOBALS['authRole']     ?? '';
$authUser     = $GLOBALS['authUser']     ?? '';
$isAdmin      = $GLOBALS['isAdmin']      ?? false;
$isSuperAdmin = $GLOBALS['isSuperAdmin'] ?? false;
$isGuest      = $GLOBALS['isGuest']      ?? false;
$isDemo       = $GLOBALS['isDemo']       ?? false;
$isPartner    = $GLOBALS['isPartner']    ?? false;

$action = $body['action'] ?? '';
$_llm_url= $body['_llm_url'] ?? 'http://172.17.164.70:11434';
$providerLLm = $body['providerLLm'] ?? 'ollama';


debug_log('API', 'ACTION', ['action' => $action, 'role' => $authRole, 'user' => $authUser]);

// ── Routing ──────────────────────────────────────────────────────────────────



switch ($action) {

    case 'probe':
        handleProbe();
        break;

    case 'test_connections':
        handleTestConnections();
        break;

    case 'llm_direct':
        handleLlmDirect($body);
        break;

    case 'llm_stream':
        handleLlmStream($body);
        break;

    // case 'ls':
    //     handleAgentRun($body);
    //     break;
    // case 'cat':
    //     handleAgentRun($body);
    //     break;

    case 'mcp':
        handleMcp($body);
        break;

    case 'config_load':
        handleConfigLoad();
        break;

    case 'config_load_sensitive':
        handleConfigLoadSensitive();
        break;

    case 'config_save_sensitive':
        handleConfigSaveSensitive($body);
        break;

    case 'provider_prefs_save':
        handleProviderPrefsSave($body);
        break;

    case 'provider_prefs_load':
        handleProviderPrefsLoad();
        break;

    case 'provider_models_list':
        handleProviderModelsList($body);
        break;

    case 'capacites_list':
        handleCapacitesList();
        break;

    case 'capacites_save':
        handleCapacitesSave($body);
        break;

    case 'agent_decision': // AGENIA-OBS
        handleAgentDecision($body);
        break;
    default:
        if ($authRole === 'admin' || $authRole === 'superadmin' || $isDemo) { // AGENIA-DEMO
            handleAgentRun($body);
            break;
        }
        apiError(400, '⚠ Action non reconnue ou non autorisée.', false, 'llm');
}

// =============================================================================
// HELPERS GLOBAUX
// =============================================================================

/** Erreur JSON normalisée — jamais de détail d'archi dans $message */
function apiError(int $code, string $message, bool $block = false, string $context = 'llm'): never
{
    http_response_code($code);
    echo json_encode([
        'error'   => true,
        'code'    => $code,
        'message' => $message,
        'block'   => $block,
        'context' => $context,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/** Interprète un code HTTP proxy en message lisible sans info d'archi */
function interpretHttpStatus(int $status): array
{
    return match(true) {
        $status === 401 => ['🔒 Session expirée — rechargez la page.', true],
        $status === 403 => ['🚫 Accès refusé.', true],
        $status === 404 => ['⚠ Service introuvable.', false],
        $status === 429 => ['⏳ Trop de requêtes — patientez quelques secondes.', false],
        $status >= 500  => ['⚠ Le service ne répond pas correctement.', false],
        default         => ['⚠ Erreur de communication (' . $status . ').', false],
    };
}

/** cURL interne — transmet les cookies de session pour que check.php valide l'auth */
function curlProxy(string $url, string $method = 'GET', ?string $postBody = null, array $extraHeaders = []): array
{
    global $authRole, $isDemo, $isGuest;
    // Construire la chaîne de cookies depuis la requête entrante
    $cookieParts = [];
    foreach ($_COOKIE as $k => $v) {
        $cookieParts[] = urlencode($k) . '=' . urlencode($v);
    }
    $cookieStr = implode('; ', $cookieParts);

    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $myTimeout = 30;

    if (!$isDemo) {
        debug_log('API.PHP', 'curlProxy', ['$myTimeout=' => $myTimeout]);
        $myTimeout = 300;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_USERAGENT=> 'datacorners-internal/1.0',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 300,
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_HTTPHEADER     => array_merge(
            ['Content-Type: application/json', 'Host: ' . $host],
            $extraHeaders,
            ['Host: agent.datacorners.com']
        ),
        CURLOPT_COOKIE         => $cookieStr,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    if ($postBody !== null && strtoupper($method) === 'POST') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postBody);
    }
    $response   = curl_exec($ch);
    $httpStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr    = curl_error($ch);
    curl_close($ch);
    return ['status' => $httpStatus, 'body' => $response ?: '', 'curl_err' => $curlErr];
}

/**
 * Chiffrement AES-256-CBC d'une clé API avant stockage en BDD.
 * Nécessite la variable d'environnement VOX_ENCRYPTION_KEY définie côté serveur.
 */
function encryptKey(string $plain): string
{
    $key = getenv('VOX_ENCRYPTION_KEY');
    if (!$key) {
        error_log('VOX_ENCRYPTION_KEY manquante — clé API non chiffrée correctement.');
        return '';
    }
    $iv = openssl_random_pseudo_bytes(16);
    $cipherText = openssl_encrypt($plain, 'aes-256-cbc', $key, 0, $iv);
    if ($cipherText === false) { return ''; }
    return base64_encode($iv . $cipherText);
}

/** Déchiffrement symétrique à encryptKey() */
function decryptKey(string $encrypted): string
{
    $key = getenv('VOX_ENCRYPTION_KEY');
    if (!$key) { return ''; }
    $data = base64_decode($encrypted, true);
    if ($data === false || strlen($data) < 16) { return ''; }
    $iv         = substr($data, 0, 16);
    $cipherText = substr($data, 16);
    $plain      = openssl_decrypt($cipherText, 'aes-256-cbc', $key, 0, $iv);
    return $plain !== false ? $plain : '';
}

// =============================================================================
// CAPACITÉS — vérification rôle vs BDD
// =============================================================================

/**
 * Vérifie si le rôle courant a la capacité demandée.
 * La BDD fait foi. Hierarchie : superadmin > admin > pro > free > guest
 */
function roleHasCapacity(string $slug, string $role): bool
{
    // Superadmin a tout
    if ($role === 'superadmin') { return true; }
    debug_log('SLOG', 'TEST capacites', ['ROLE' => $role, 'API FONCTION' => 'roleHasCapacity']);

    try {
        $row = db_row(
            'SELECT roles, active FROM capacites WHERE slug = ? LIMIT 1',
            [$slug]
        );
        if (!$row || !(bool)$row['active']) { return false; }

        $allowed = array_map('trim', explode(',', $row['roles']));

        // Hiérarchie : si un rôle inférieur est autorisé, les rôles supérieurs le sont aussi
        $hierarchy = ['guest', 'free', 'pro', 'admin', 'superadmin'];
        $roleIndex = array_search($role, $hierarchy);
        if ($roleIndex === false) { return false; }

        foreach ($allowed as $allowedRole) {
            $allowedIndex = array_search($allowedRole, $hierarchy);
            if ($allowedIndex !== false && $roleIndex >= $allowedIndex) {
                debug_log('PERMISSION', 'TEST capacites', ['ROLE' => $role, 'AS rights agent ?' => 'true']);
                return true;
            }
        }
        return false;
    } catch (\Exception $e) {
        debug_log('API', 'CAPACITY_ERROR', ['err' => $e->getMessage()]);
        return false;
    }
}

// =============================================================================
// ACTION : probe
// =============================================================================
function handleProbe(): void
{//echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);exit;
    $result = curlProxy('http://localhost/proxy-llm.php?probe=1', 'GET');
    if ($result['curl_err']) {
        apiError(503, '⚠ Le service LLM est inaccessible.', false, 'probe');
    }
    if ($result['status'] === 200) {
        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }
    [$msg, $block] = interpretHttpStatus($result['status']);
    apiError($result['status'], $msg, $block, 'probe');
}

// =============================================================================
// ACTION : test_connections
// BDD dot visible uniquement superadmin
// =============================================================================
function handleTestConnections(): void
{
    global $isAdmin, $isSuperAdmin;
    $results = [];

    // LLM
    $r1 = curlProxy('http://localhost/proxy-llm.php?probe=1', 'GET');
    $results[] = $r1['curl_err'] || $r1['status'] !== 200
        ? ['label' => 'LLM', 'status' => 'error', 'dot' => 'llm', 'msg' => $r1['curl_err'] ?: 'HTTP ' . $r1['status']]
        : ['label' => 'LLM', 'status' => 'ok',    'dot' => 'llm'];

    // Voix (TTS) — label public
    $r2 = curlProxy('http://localhost/proxy-tts.php?health=1', 'GET');
    if ($r2['curl_err']) {
        $results[] = ['label' => 'Voix', 'status' => 'error', 'dot' => 'tts', 'msg' => $r2['curl_err']];
    } elseif ($r2['status'] === 200 || $r2['status'] === 404) {
        $results[] = ['label' => 'Voix', 'status' => 'ok', 'dot' => 'tts'];
    } else {
        $results[] = ['label' => 'Voix', 'status' => 'warn', 'dot' => 'tts'];
    }

    // DATA — superadmin uniquement
    if ($isSuperAdmin) {
        $r3 = curlProxy('http://localhost/api/db-health.php', 'GET');
        if ($r3['curl_err']) {
            $results[] = ['label' => 'DATA', 'status' => 'error', 'dot' => 'db', 'msg' => $r3['curl_err']];
        } else {
            $dbData = json_decode($r3['body'], true);
            if ($dbData && isset($dbData['results'])) {
                foreach ($dbData['results'] as $key => $entry) {
                    $results[] = [
                        'label'  => $entry['label'] ?? $key,
                        'status' => $entry['status'] ?? 'error',
                        'dot'    => 'db',
                        'msg'    => isset($entry['tests']) ? implode(' ', $entry['tests']) : '',
                    ];
                }
            } else {
                $results[] = ['label' => 'DATA', 'status' => 'error', 'dot' => 'db'];
            }
        }
    }

    echo json_encode(['results' => $results], JSON_UNESCAPED_UNICODE);
    exit;
}


function handleLlmDirect(array $body): void
{
    $body['stream'] = false;

    $host        = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $cookieParts = [];
    foreach ($_COOKIE as $k => $v) {
        $cookieParts[] = urlencode($k) . '=' . urlencode($v);
    }
    

    
    $result = curlProxy(
        'http://localhost/proxy-llm.php',
        'POST',
        json_encode($body, JSON_UNESCAPED_UNICODE),
        []
    );

    if ($result['curl_err']) {
        apiError(503, '⚠ Le service LLM est inaccessible.', false, 'llm');
    }
    if ($result['status'] !== 200) {
        [$msg, $block] = interpretHttpStatus($result['status']);
        apiError($result['status'], $msg, $block, 'llm');
    }

    echo $result['body'];
    exit;
}
// =============================================================================
// ACTION : llm_stream
// =============================================================================
function handleLlmStream(array $body): void // AGENIA-LEAK v1 AGENIA-DEMO
{
    @ini_set('output_buffering', 'Off');
    @ini_set('zlib.output_compression', 'Off');
    @ini_set('implicit_flush', '1');
    ob_implicit_flush(true);
    if (ob_get_level()) { ob_end_clean(); }
    set_time_limit(0);

    header('Content-Type: application/x-ndjson; charset=utf-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('X-Accel-Buffering: no');

    global $authRole, $isDemo, $isGuest;

    $hasAudit = function_exists('audit_write');
    $trace    = $hasAudit ? audit_trace_id() : bin2hex(random_bytes(8));
    $t0       = microtime(true);
    $ttft     = null;
    $done     = [];

    echo json_encode(['trace' => $trace]) . "\n";
    flush();

    $body['stream'] = true;
    $payload = json_encode($body, JSON_UNESCAPED_UNICODE);
    $host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $cookieParts = [];
    foreach ($_COOKIE as $k => $v) { $cookieParts[] = urlencode($k) . '=' . urlencode($v); }

    // Marqueur interne de l'agent : JAMAIS renvoyé au navigateur (propriété intellectuelle).
    $TRIGGER      = '@terminal';
    $agentPattern = '/@terminal\s+action=(.+?)\s+objectif=(.+?)\s+mode=admin/is';

    $ctx = stream_context_create(['http' => [
        'method'        => 'POST',
        'header'        => implode("\r\n", [
            'Content-Type: application/json',
            'Host: ' . $host,
            'Cookie: ' . implode('; ', $cookieParts),
            'X-Trace-Id: ' . $trace,
        ]),
        'content'       => $payload,
        'timeout'       => 120,
        'ignore_errors' => true,
    ]]);

    $streamHandle = @fopen('http://localhost/proxy-llm.php', 'r', false, $ctx);
    if ($streamHandle === false) {
        if ($hasAudit) { audit_write('llm_call', ['status' => 'unreachable', 'duration_ms' => (int)round((microtime(true) - $t0) * 1000)]); }
        echo json_encode(['error' => true, 'message' => '⚠ Le service LLM est inaccessible.'], JSON_UNESCAPED_UNICODE) . "\n";
        flush();
        exit;
    }

    $streamStatus = 0;
    if (isset($http_response_header)) {
        foreach ($http_response_header as $hh) {
            if (preg_match('/^HTTP\/\S+ (\d+)/', $hh, $m)) { $streamStatus = (int)$m[1]; break; }
        }
    }

    $lineBuf    = '';     // tampon des lignes NDJSON reçues de proxy-llm
    $assistant  = '';     // texte assistant cumulé (jamais renvoyé tel quel)
    $emitted    = 0;      // caractères de $assistant déjà envoyés au navigateur
    $held       = false;  // true dès qu'un début de marqueur apparaît : on retient tout
    $agentFired = false;

    // Envoie le texte SÛR au navigateur, en gardant une queue qui pourrait devenir
    // le marqueur. Rien contenant le marqueur ne sort jamais.
    $flushSafe = function (bool $final) use (&$assistant, &$emitted, &$held, $TRIGGER) {
        if ($held) { return; }
        $pending = substr($assistant, $emitted);
        if ($pending === '') { return; }
        $keep = 0;
        if (!$final) {
            $max = min(strlen($TRIGGER) - 1, strlen($pending));
            for ($n = $max; $n > 0; $n--) {
                if (substr($pending, -$n) === substr($TRIGGER, 0, $n)) { $keep = $n; break; }
            }
        }
        $safe = $keep > 0 ? substr($pending, 0, strlen($pending) - $keep) : $pending;
        if ($safe !== '') {
            echo json_encode(['message' => ['role' => 'assistant', 'content' => $safe]], JSON_UNESCAPED_UNICODE) . "\n";
            flush();
            $emitted += strlen($safe);
        }
    };

    while (!feof($streamHandle)) {
        $chunk = fread($streamHandle, 8192);
        if ($chunk === false || $chunk === '') { continue; }
        $lineBuf .= $chunk;
        $nl = strrpos($lineBuf, "\n");
        if ($nl === false) { continue; }
        $complete = substr($lineBuf, 0, $nl);
        $lineBuf  = substr($lineBuf, $nl + 1);

        foreach (explode("\n", $complete) as $line) {
            $line = trim($line);
            if ($line === '') { continue; }
            $decoded = json_decode($line, true);
            if (!is_array($decoded)) { continue; }

            $token = $decoded['message']['content'] ?? '';
            if ($token !== '') {
                $assistant .= $token;
                if ($ttft === null) { $ttft = (int)round((microtime(true) - $t0) * 1000); }
            }
            if (!empty($decoded['done'])) {
                $done = [
                    'model'      => $decoded['model'] ?? null,
                    'tokens_in'  => $decoded['prompt_eval_count'] ?? null,
                    'tokens_out' => $decoded['eval_count'] ?? null, 'cost' => $decoded['agenia_cost'] ?? null, // AGENIA-COST
                ];
            }

            // Dès que le début du marqueur apparaît, on cesse d'émettre.
            if (!$agentFired && !$held && strpos($assistant, $TRIGGER) !== false) {
                $held = true;
            }
            // Invocation complète : on la traite côté serveur, jamais côté client.
            if (!$agentFired && $held && preg_match($agentPattern, $assistant, $mm)) {
                $agentFired = true;
                $action   = trim(preg_replace('/\s+/', ' ', $mm[1]));
                $objectif = trim(preg_replace('/\s+/', ' ', $mm[2]));

                echo json_encode(['clear' => true], JSON_UNESCAPED_UNICODE) . "\n";

                $hasCap = roleHasCapacity('terminal', $authRole);
                // Démo : on MONTRE la carte d'accord (l'exécution sera simulée). Invité : on refuse.
                $canSeeCard = $isDemo || ($hasCap && !$isGuest);
                if ($hasAudit) { audit_write('agent_proposed', ['command' => $action, 'goal' => $objectif, 'allowed' => $canSeeCard, 'demo' => $isDemo]); }

                if (!$canSeeCard) {
                    $lang = defined('UI_LANG') ? UI_LANG : 'fr';
                    $msg  = $lang === 'fr'
                        ? 'Pour utiliser le mode Agent, veuillez vous connecter avec un compte autorisé.'
                        : 'To use Agent mode, please sign in with an authorized account.';
                    echo json_encode(['message' => ['content' => $msg]], JSON_UNESCAPED_UNICODE) . "\n";
                    echo json_encode(['done' => true], JSON_UNESCAPED_UNICODE) . "\n";
                } else {
                    echo json_encode(['agent' => true, 'action' => $action, 'objectif' => $objectif], JSON_UNESCAPED_UNICODE) . "\n";
                }
                flush();
            }
        }

        if (!$agentFired) { $flushSafe(false); }
    }

    if (!$agentFired) { $flushSafe(true); }
    fclose($streamHandle);

    $audit = [
        'status'      => $streamStatus ?: 0,
        'model'       => $done['model'] ?? null,
        'provider'    => null,
        'local'       => null,
        'duration_ms' => (int)round((microtime(true) - $t0) * 1000),
        'ttft_ms'     => $ttft,
        'tokens_in'   => $done['tokens_in'] ?? null,
        'tokens_out'  => $done['tokens_out'] ?? null,
        'agent'       => $agentFired,
    ];
    foreach (($http_response_header ?? []) as $hh) {
        if (preg_match('/^X-Agenia-(Provider|Local|Model):\s*(.*)$/i', $hh, $m)) {
            $k = strtolower($m[1]); $v = trim($m[2]);
            if ($k === 'provider') { $audit['provider'] = $v; }
            elseif ($k === 'local') { $audit['local'] = ($v === '1'); }
            elseif ($k === 'model' && empty($audit['model'])) { $audit['model'] = $v; }
        }
    }
    foreach ([__DIR__, dirname(__DIR__)] as $agd) { if (is_file($agd . '/lib/llm-cost.php')) { require_once $agd . '/lib/llm-cost.php'; break; } } if (function_exists('agenia_llm_cost')) { $audit += agenia_llm_cost($audit, $done['cost'] ?? null); } // AGENIA-COST
    if ($hasAudit) { audit_write('llm_call', $audit); }

    unset($audit['model'], $audit['provider']); // le front n'affiche aucun nom de techno
    echo json_encode(['audit' => ['trace' => $trace] + $audit], JSON_UNESCAPED_UNICODE) . "\n";
    flush();

    if ($streamStatus && $streamStatus !== 200) {
        [$msg, $block] = interpretHttpStatus($streamStatus);
        echo json_encode([
            'error'   => true,
            'code'    => $streamStatus,
            'message' => $msg,
            'block'   => $block,
            'context' => 'llm',
        ], JSON_UNESCAPED_UNICODE) . "\n";
        flush();
    }
    exit;
}



// =============================================================================
// ACTION : agent_run
// =============================================================================
function handleAgentRun(array $body): void
{
    global $authRole, $isDemo, $isGuest;

    $action   = trim($body['action']   ?? '');
    $objectif = trim($body['objectif'] ?? '');
    // AGENIA-OBS : trace de la proposition acceptee + mesure
    $obsTrace = (isset($body['trace']) && audit_valid_trace((string)$body['trace'])) ? (string)$body['trace'] : audit_trace_id();
    $obsDecision = isset($body['trace']) ? 'approved' : 'direct';
    $obsT0 = microtime(true);
    if ($isDemo) { // AGENIA-DEMO : execution simulee, aucun acces au vrai terminal
        if (function_exists('audit_write')) { audit_write('agent_run', ['trace' => $obsTrace, 'command' => $action, 'goal' => $objectif, 'decision' => 'approved', 'status' => 'demo', 'duration_ms' => 0]); }
        $demo = "DEMONSTRATION - commande non executee sur le systeme.

$ " . $action . "
(resultat d'exemple)
total 8
drwxr-xr-x  rapports
-rw-r--r--  synthese.pdf
-rw-r--r--  donnees.csv";
        echo json_encode(['ok' => true, 'output' => $demo], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!$action) {
        $action = 'probe';
       
    }

    // Double vérification capacité — le front ne peut pas bypasser
    if (!roleHasCapacity('terminal', $authRole) || $isDemo || $isGuest) {
        audit_write('agent_run', ['trace' => $obsTrace, 'command' => $action, 'goal' => $objectif, 'decision' => $obsDecision, 'status' => 'refused_rights']);
        $lang = defined('UI_LANG') ? UI_LANG : 'fr';
        apiError(403,
            $lang === 'fr'
                ? 'Pour utiliser le mode Agent, veuillez vous connecter avec un compte autorisé.'
                : 'To use Agent mode, please sign in with an authorized account.',
            false, 'llm'
        );
    }

    // Déléguer à proxy-mcp.php
    $mcpBearer = defined('MCP_BEARER') ? MCP_BEARER : '';
    $payload   = json_encode(['action' => $action, 'tool' => 'tool@terminal'], JSON_UNESCAPED_UNICODE);
    $result    = curlProxy(
        'http://localhost/proxy-mcp.php',
        'POST',
        $payload,
        $mcpBearer ? ['Authorization: Bearer ' . $mcpBearer] : []
    );

    if ($result['curl_err']) {
        debug_log('API', 'ERREUR MCP', ['role' => $authRole, 'user' => $result['curl_err'] ?? '']);
        audit_write('agent_run', ['trace' => $obsTrace, 'command' => $action, 'goal' => $objectif, 'decision' => $obsDecision, 'status' => 'unreachable']);
        apiError(503, '⚠ Le service agent est inaccessible.', false, 'llm');
    }
    if ($result['status'] !== 200) {
        audit_write('agent_run', ['trace' => $obsTrace, 'command' => $action, 'goal' => $objectif, 'decision' => $obsDecision, 'status' => 'http_' . $result['status']]);
        apiError($result['status'], '⚠ Erreur du service agent.', false, 'llm');
    }

    $data = json_decode($result['body'], true);
    if ($data === null) {
        apiError(502, '⚠ Réponse agent invalide.', false, 'llm');
    }

    debug_log('API', 'AGENT_RUN_OK', ['role' => $authRole, 'user' => $GLOBALS['authUser'] ?? '']);
    audit_write('agent_run', ['trace' => $obsTrace, 'command' => $action, 'goal' => $objectif, 'decision' => $obsDecision, 'status' => 'ok', 'duration_ms' => (int)round((microtime(true) - $obsT0) * 1000), 'output_bytes' => strlen((string)($data['output'] ?? ''))]);

    echo json_encode(['ok' => true, 'output' => $data['output'] ?? ''], JSON_UNESCAPED_UNICODE);
    exit;
}

// =============================================================================
// ACTION : mcp
// =============================================================================
function handleMcp(array $body): void
{
    global $authRole, $isDemo, $isGuest;

    $tool   = $body['tool']   ?? '';
    $action = $body['action'] ?? '';

    if (!$tool || !$action) {
        apiError(400, '⚠ Paramètres manquants.', false, 'llm');
    }

    // Vérification capacité si tool@terminal
    if ($tool === 'tool@terminal') {
        if (!roleHasCapacity('terminal', $authRole) || $isDemo || $isGuest) {
            $lang = defined('UI_LANG') ? UI_LANG : 'fr';
            apiError(403,
                $lang === 'fr'
                    ? 'Pour utiliser le mode Agent, veuillez vous connecter avec un compte autorisé.'
                    : 'To use Agent mode, please sign in with an authorized account.',
                false, 'llm'
            );
        }
    }

    $mcpBearer = defined('MCP_BEARER') ? MCP_BEARER : '';
    $payload   = json_encode(['action' => $action, 'tool' => $tool], JSON_UNESCAPED_UNICODE);
    $result    = curlProxy(
        'http://localhost/proxy-mcp.php',
        'POST',
        $payload,
        $mcpBearer ? ['Authorization: Bearer ' . $mcpBearer] : []
    );

    if ($result['curl_err']) {
        apiError(503, '⚠ Le service est inaccessible.', false, 'llm');
    }
    if ($result['status'] !== 200) {
        apiError($result['status'], '⚠ Erreur de service.', false, 'llm');
    }
    $data = json_decode($result['body'], true);
    if ($data === null) {
        apiError(502, '⚠ Réponse invalide.', false, 'llm');
    }

    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// =============================================================================
// CONFIG — helpers
// =============================================================================

function loadSettingsJson(): array
{
    $sf = __DIR__ . '/../config/settings.json';
    if (!file_exists($sf)) {
        error_log("Le fichier $sf n'existe pas.");
        return [];
    }

    $content = file_get_contents($sf);
    if ($content === false) {
        error_log("Impossible de lire le contenu du fichier $sf.");
        return [];
    }

    $data = json_decode($content, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Erreur de décodage JSON dans le fichier $sf : " . json_last_error_msg());
        return [];
    }

    return $data ?: [];
}

function maskSecret(string $value): string
{
    if (strlen($value) <= 4) { return str_repeat('•', strlen($value)); }
    return str_repeat('•', max(4, strlen($value) - 4)) . substr($value, -4);
}

function sensitiveOverridePath(): string
{
    // Hors webroot recommandé en production
    return __DIR__ . '/../data/admin_sensitive.json';
}

function loadSensitiveOverrides(): array
{
    $path = sensitiveOverridePath();
    if (!file_exists($path)) { return []; }
    return json_decode(file_get_contents($path), true) ?: [];
}

// =============================================================================
// ACTION : config_load
// Retourne uniquement les clés UI — zéro info infra/modèle/clé
// Inclut les voix disponibles avec labels publics (Célia / Alex)
// =============================================================================

function handleConfigLoad(): void
{
    global $authRole, $isAdmin, $isSuperAdmin, $isDemo, $isGuest;

    // NE PAS appeler loadSensitiveOverrides() ici — aucune donnée sensible en front
    $defaults = loadSettingsJson();

    $llm    = $defaults['llm']          ?? [];
    $audio  = $defaults['audio']        ?? [];
    $tts    = $defaults['tts']          ?? [];
    $vision = $defaults['vision']       ?? [];
    $upload = $defaults['upload']       ?? [];
    $ttsHL  = $defaults['ttsHighlight'] ?? [];

    $ui = [
        'noThink'             => $llm['noThink']               ?? true,
        'streaming'           => $audio['streaming']            ?? true,
        'audioEnabled'        => $audio['enabled']              ?? true,
        'naturalConversation' => false,  // désactivé — loopback acoustique
        'firstChunkWords'     => $audio['firstChunkWords']      ?? 20,
        'sentenceMarkers'     => $audio['sentenceMarkers']      ?? ['.','!','?','…'],
        'pauseMarkers'        => $audio['pauseMarkers']         ?? [',',';',':'],
        'pauseMinWords'       => $audio['pauseMinWords']        ?? 20,
        'ttsTimeoutMs'        => $audio['ttsTimeoutMs']         ?? 8000,
        'ttsMinWordsStrong'   => $audio['ttsMinWordsStrong']    ?? 20,
        'ttsInstruct'         => $tts['instruct']               ?? false,
        'ttsInstructPrompt'   => $tts['instructPrompt']         ?? '',
        'replayAudio'         => $audio['replayAudio']          ?? false,
        'ttsHighlightEnabled'    => $ttsHL['enabled']    ?? true,
        'ttsHighlightColor'      => $ttsHL['color']      ?? 'rgba(124,58,237,0.35)',
        'ttsHighlightColorLight' => $ttsHL['colorLight'] ?? 'rgba(109,40,217,0.25)',
        'visionMode'      => $vision['mode']      ?? 'off',
        'visionThreshold' => $vision['threshold'] ?? 15,
        'visionPrompt'    => $vision['prompt']    ?? 'Décris brièvement ce que tu vois.',
        'visionSpeak'     => $vision['speak']     ?? true,
        'uploadEnabled'   => $upload['enabled']   ?? true,
    ];

    // Voix — labels publics uniquement, identifiants techniques jamais exposés
    $voices = [
        ['id' => 'fr', 'label' => 'Célia'],
        ['id' => 'en', 'label' => 'Alex'],
    ];

    // Providers — liste complète (label/url/requiresKey) + slug actif par type
    // Nécessaire pour peupler les <select> côté front (providers.js)
    $providersRaw = $defaults['providers'] ?? [];
    $providersOut = [];

    foreach (['llm', 'tts', 'stt'] as $type) {
        $p    = $providersRaw[$type] ?? [];
        $list = $p['list'] ?? [];

        $safeList = [];
        foreach ($list as $slug => $item) {
            $safeList[$slug] = [
                'label'       => $item['label'] ?? $slug,
                'requiresKey' => $item['requiresKey'] ?? false,
                'url'         => $item['url'] ?? '',
            ];
        }

        $providersOut[$type] = [
            'active' => $p['active'] ?? 'default',
            'list'   => $safeList,
        ];
    }

    // Capacités disponibles pour ce rôle — pour affichage modules
    $agentEnabled = !$isGuest && ($isDemo || roleHasCapacity('terminal', $authRole)); // AGENIA-DEMO : agent visible en demo

    echo json_encode([
        'ui'           => $ui,
        'voices'       => $voices,
        'providers'    => $providersOut,
        'agentEnabled' => $agentEnabled,
        'demo'         => $isDemo, // AGENIA-DEMO
        'guest'        => ($isGuest && !empty($GLOBALS['guestData'])) ? [
            'messagesUsed' => (int)($GLOBALS['guestData']['messages_used'] ?? 0),
            'maxMessages'  => (int)($GLOBALS['guestData']['max_messages'] ?? 10),
            'expiresAt'    => (int)($GLOBALS['guestData']['expires_at'] ?? 0),
        ] : null,
        'role'         => $isSuperAdmin ? 'superadmin' : $authRole,   // rôle public (pas les détails internes)
        'version'      => 'V8.0',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}


// =============================================================================
// ACTION : config_load_sensitive
// Valeurs admin masquées — accès admin/superadmin uniquement
// =============================================================================
function handleConfigLoadSensitive(): void
{
    global $isAdmin, $isSuperAdmin;

    if (!$isAdmin && !$isSuperAdmin ) {
        apiError(403, '🚫 Accès réservé.', true, 'llm');
        
    }

    $defaults  = loadSettingsJson();
    $overrides = loadSensitiveOverrides();

    $llmKey    = $overrides['llmKey']    ?? ($defaults['llm']['apiKey'] ?? '');
    $sttBearer = $overrides['sttBearer'] ?? ($defaults['stt']['bearer'] ?? '');

    // URLs affichées en clair (utiles pour debug admin)
    // Clés masquées — jamais en clair
    $sensitive = [
        'llmUrl'          => $overrides['llmUrl']      ?? ($defaults['llm']['url']          ?? ''),
        'llmModel'        => $overrides['llmModel']    ?? ($defaults['llm']['model']         ?? ''),
        'visionModel'     => $overrides['visionModel'] ?? ($defaults['llm']['visionModel']   ?? ''),
        'systemPrompt'    => $isSuperAdmin ? ($overrides['systemPrompt'] ?? ($defaults['llm']['systemPrompt'] ?? '')) : '', // AGENIA-SP : super-admin uniquement
        'ttsUrl'          => $overrides['ttsUrl']      ?? ($defaults['tts']['url']           ?? ''),
        'ttsModel'        => $overrides['ttsModel']    ?? ($defaults['tts']['model']         ?? ''),
        'ttsVoice'        => $overrides['ttsVoice']    ?? ($defaults['tts']['voice']         ?? ''),
        'ttsFormat'       => $overrides['ttsFormat']   ?? ($defaults['tts']['format']        ?? 'mp3'),
        'sttUrl'          => $overrides['sttUrl']      ?? ($defaults['stt']['url']           ?? ''),
        'sttModel'        => $overrides['sttModel']    ?? ($defaults['stt']['model']         ?? ''),
        // Secrets masqués
        'llmKeyMasked'    => $llmKey    ? maskSecret($llmKey)    : '',
        'sttBearerMasked' => $sttBearer ? maskSecret($sttBearer) : '',
    ];

    echo json_encode(['sensitive' => $sensitive], JSON_UNESCAPED_UNICODE);
    exit;
}

// =============================================================================
// ACTION : config_save_sensitive
// Persiste les valeurs sensibles — admin/superadmin uniquement
// =============================================================================
function handleConfigSaveSensitive(array $body): void
{
    global $isAdmin, $isSuperAdmin;

    if (!$isAdmin && !$isSuperAdmin) {
        
        apiError(403, '🚫 Accès réservé.', true, 'llm');
    }

    $allowed = [
        'llmUrl','llmModel','visionModel','systemPrompt','llmKey',
        'ttsUrl','ttsModel','ttsVoice','ttsFormat',
        'sttUrl','sttModel','sttBearer',
        'llmProvider','ttsProvider','sttProvider',
    ];

    $incoming = $body['sensitive'] ?? [];
    $current  = loadSensitiveOverrides();

    foreach ($allowed as $key) {
        if ($key === 'systemPrompt' && !$isSuperAdmin) { continue; } // AGENIA-SP : super-admin uniquement
        if (!array_key_exists($key, $incoming)) { continue; }
        $val = trim((string)$incoming[$key]);
        if (str_contains($val, '•')) { continue; } // valeur masquée — ignorer
        if ($val === '') {
            unset($current[$key]);
        } else {
            $current[$key] = $val;
        }
    }

    $dir = dirname(sensitiveOverridePath());
    if (!is_dir($dir)) { mkdir($dir, 0750, true); }

    $written = file_put_contents(
        sensitiveOverridePath(),
        json_encode($current, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        LOCK_EX
    );

    if ($written === false) {
        apiError(500, '⚠ Impossible d\'enregistrer la configuration.', false, 'llm');
    }

    debug_log('API', 'CONFIG_SAVED', ['by' => $GLOBALS['authUser'] ?? '']);
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

// =============================================================================
// ACTION : provider_prefs_save / provider_prefs_load
// Préférences provider PAR UTILISATEUR (pas admin) — stockées dans
// agenia_prospect_configs.llm_config, liées par email → subscriber_id.
// Les anonymes (guest/demo) n'appellent jamais ces actions — gérés en
// localStorage côté front.
// =============================================================================
function handleProviderPrefsSave(array $body): void
{
    global $isGuest, $isDemo;

    if ($isGuest || $isDemo) {
        apiError(403, '🚫 Connectez-vous pour synchroniser vos préférences.', false, 'llm');
    }

    $slug   = trim($body['slug']   ?? '');
    $apiKey = trim($body['apiKey'] ?? '');

    if ($slug === '') {
        apiError(400, '⚠ Provider manquant.', false, 'llm');
    }

    // ── Valider le slug contre la whitelist de settings.json ────────────────
    $settings = loadSettingsJson();
    $allowed  = $settings['providers']['llm']['list'] ?? [];
    if (!array_key_exists($slug, $allowed)) {
        apiError(400, '⚠ Provider inconnu.', false, 'llm');
    }
    $requiresKey = $allowed[$slug]['requiresKey'] ?? false;

    // ── Retrouver le subscriber via email ───────────────────────────────────
    $authUserKey = $GLOBALS['authUser'] ?? '';
    $usersFile   = require __DIR__ . '/../user.php'; // adapter le chemin si besoin
    $email       = $usersFile[$authUserKey]['email'] ?? '';

    if ($email === '') {
        apiError(403, '⚠ Compte non identifié.', false, 'llm');
    }

    $subscriber = db_row('SELECT id FROM waitlist_subscribers WHERE email = ? LIMIT 1', [$email]);
    if (!$subscriber) {
        apiError(404, '⚠ Profil utilisateur introuvable.', false, 'llm');
    }
    $subscriberId = $subscriber['id'];

    // ── Charger la config existante (pour ne pas perdre la clé si vide envoyé) ──
    $row = db_row('SELECT llm_config FROM agenia_prospect_configs WHERE subscriber_id = ? LIMIT 1', [$subscriberId]);
    $existing = $row && $row['llm_config'] ? (json_decode($row['llm_config'], true) ?: []) : [];

    $newConfig = [
        'provider' => $slug,
    ];

    if ($apiKey !== '') {
        $newConfig['apiKeyEnc'] = encryptKey($apiKey);
    } elseif (!empty($existing['apiKeyEnc']) && ($existing['provider'] ?? '') === $slug) {
        $newConfig['apiKeyEnc'] = $existing['apiKeyEnc'];
    } elseif ($requiresKey) {
        apiError(400, '⚠ Clé API requise pour ce provider.', false, 'llm');
    }

    // ── Upsert ────────────────────────────────────────────────────────────────
    if ($row) {
        db_query(
            'UPDATE agenia_prospect_configs SET llm_config = ?, updated_at = NOW() WHERE subscriber_id = ?',
            [json_encode($newConfig, JSON_UNESCAPED_UNICODE), $subscriberId]
        );
    } else {
        db_query(
            'INSERT INTO agenia_prospect_configs (subscriber_id, llm_config, created_at, updated_at) VALUES (?, ?, NOW(), NOW())',
            [$subscriberId, json_encode($newConfig, JSON_UNESCAPED_UNICODE)]
        );
    }

    debug_log('API', 'PROVIDER_PREFS_SAVED', ['user' => $authUserKey, 'slug' => $slug]);
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

function handleProviderPrefsLoad(): void
{
    global $isGuest, $isDemo;

    if ($isGuest || $isDemo) {
        echo json_encode(['slug' => null, 'hasKey' => false], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $authUserKey = $GLOBALS['authUser'] ?? '';
    $usersFile   = require __DIR__ . '/../user.php';
    $email       = $usersFile[$authUserKey]['email'] ?? '';

    if ($email === '') {
        echo json_encode(['slug' => null, 'hasKey' => false], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $subscriber = db_row('SELECT id FROM waitlist_subscribers WHERE email = ? LIMIT 1', [$email]);
    if (!$subscriber) {
        echo json_encode(['slug' => null, 'hasKey' => false], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $row = db_row('SELECT llm_config FROM agenia_prospect_configs WHERE subscriber_id = ? LIMIT 1', [$subscriber['id']]);
    $cfg = $row && $row['llm_config'] ? (json_decode($row['llm_config'], true) ?: []) : [];

    echo json_encode([
        'slug'   => $cfg['provider'] ?? null,
        'hasKey' => !empty($cfg['apiKeyEnc']),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// =============================================================================
// ACTION : provider_models_list
// Récupère la liste des modèles disponibles pour un provider donné.
// Pour l'instant, seul OpenRouter expose un endpoint public de listing.
// L'appel se fait CÔTÉ SERVEUR (curl) — contourne la CSP du navigateur
// qui bloquerait un appel direct depuis le front.
// =============================================================================
function handleProviderModelsList(array $body): void
{
    $slug = trim($body['slug'] ?? '');

    if ($slug === '') {
        apiError(400, '⚠ Provider manquant.', false, 'llm');
    }

    if ($slug !== 'openrouter') {
        echo json_encode(['models' => []], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $ch = curl_init('https://openrouter.ai/api/v1/models');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
    ]);
    $response = curl_exec($ch);
    $status   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        apiError(503, '⚠ Impossible de joindre OpenRouter.', false, 'llm');
    }
    if ($status !== 200) {
        apiError($status, '⚠ Erreur OpenRouter (HTTP ' . $status . ').', false, 'llm');
    }

    $data = json_decode($response, true);
    if (!is_array($data) || !isset($data['data'])) {
        apiError(502, '⚠ Réponse OpenRouter invalide.', false, 'llm');
    }

    $models = array_map(function($m) {
        return [
            'id'   => $m['id']   ?? '',
            'name' => $m['name'] ?? ($m['id'] ?? ''),
        ];
    }, $data['data']);

    echo json_encode(['models' => $models], JSON_UNESCAPED_UNICODE);
    exit;
}

// =============================================================================
// ACTION : capacites_list
// Liste des capacités avec rôles — superadmin uniquement
// =============================================================================
function handleCapacitesList(): void
{
    global $isSuperAdmin;

    if (!$isSuperAdmin) {
        apiError(403, '🚫 Accès réservé.', true, 'llm');
    }

    try {
        $rows = db_rows('SELECT id, slug, label_fr, label_en, roles, active FROM capacites ORDER BY id ASC');
        echo json_encode(['ok' => true, 'capacites' => $rows], JSON_UNESCAPED_UNICODE);
    } catch (\Exception $e) {
        debug_log('API', 'CAPACITES_LIST_ERROR', ['err' => $e->getMessage()]);
        apiError(500, '⚠ Erreur de lecture des capacités.', false, 'llm');
    }
    exit;
}

// =============================================================================
// ACTION : capacites_save
// Modifie les rôles d'une capacité — superadmin uniquement
// =============================================================================
function handleCapacitesSave(array $body): void
{
    global $isSuperAdmin;

    if (!$isSuperAdmin) {
        apiError(403, '🚫 Accès réservé.', true, 'llm');
    }

    $slug   = trim($body['slug']   ?? '');
    $roles  = $body['roles']  ?? [];
    $active = isset($body['active']) ? (bool)$body['active'] : true;

    if (!preg_match('/^[a-z_]+$/', $slug)) {
        apiError(400, '⚠ Slug invalide.', false, 'llm');
    }

    $hierarchy = ['guest', 'free', 'pro', 'admin', 'superadmin'];
    $validRoles = array_filter($roles, fn($r) => in_array($r, $hierarchy, true));
    $rolesStr   = implode(',', $validRoles);

    try {
        db_query(
            'UPDATE capacites SET roles = ?, active = ?, updated_at = NOW() WHERE slug = ?',
            [$rolesStr, (int)$active, $slug]
        );
        debug_log('API', 'CAPACITES_SAVE_OK', ['slug' => $slug, 'roles' => $rolesStr]);
        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    } catch (\Exception $e) {
        debug_log('API', 'CAPACITES_SAVE_ERROR', ['err' => $e->getMessage()]);
        apiError(500, '⚠ Erreur d\'enregistrement.', false, 'llm');
    }
    exit;
}

// =============================================================================
// ACTION : agent_decision - AGENIA-OBS
// Enregistre un REFUS de l'utilisateur (un accord est journalise par handleAgentRun)
// =============================================================================
function handleAgentDecision(array $body): void
{
    $trace = (string)($body['trace'] ?? '');
    audit_write('agent_decision', [
        'trace'    => audit_valid_trace($trace) ? $trace : null,
        'decision' => (($body['decision'] ?? '') === 'deny') ? 'denied' : 'unknown',
        'command'  => mb_substr((string)($body['command'] ?? ''), 0, 500),
    ]);
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}