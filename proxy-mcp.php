<?php
// =============================================================================
// proxy-mcp.php — Proxy MCP : exécution PowerShell/CMD locale
// Sécurité : auth requise, whitelist outils, admin uniquement
// =============================================================================
require_once __DIR__ . '/check.php';
require_once __DIR__ . '/lib/debug.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/db_helpers.php';

@ini_set('display_errors','0'); error_reporting(E_ALL); // AGENIA-502 : pas de warning dans le JSON
header('Content-Type: application/json');
//var_dump($GLOBALS['u']); echo k;
// MCP réservé aux admins
if (!$GLOBALS['isAdmin']) {
  http_response_code(403);
  echo json_encode(['error' => 'Accès réservé aux administrateurs']);
  exit;
}

$raw    = file_get_contents('php://input');
$req    = json_decode($raw, true);
$tool   = isset($req['tool'])   ? trim($req['tool'])  : '';
$params = isset($req['params']) ? $req['params']       : [];
//$path   = isset($req['path'])   ? trim($req['path'])  : '.';
$action   = isset($req['action'])   ? trim($req['action'])  : 'ls';
debug_log('MCP VAR DUMP REQ', 'AGENT_PREFIX_DETECTED', ['text' => $req['action']]);
// Whitelist stricte
$allowed = ['powershell', 'cmd', 'info','tool@terminal'];
if (!$tool || !in_array($tool, $allowed, true)) {
  http_response_code(400);
  echo json_encode(['error' => 'Outil non autorisé : ' . htmlspecialchars($tool)]);
  exit;
}

// Désactiver les fonctions dangereuses si mal configurées
if (!function_exists('shell_exec') || ini_get('disable_functions') && strpos(ini_get('disable_functions'), 'shell_exec') !== false) {
  http_response_code(500);
  echo json_encode(['error' => 'shell_exec désactivé sur ce serveur']);
  exit;
}
debug_log('MCP TOOL', 'AGENT_PREFIX_DETECTED', ['text' => $tool]);
switch ($tool) {

 case 'tool@terminal':
  $script = isset($req['action']) ? $req['action'] : '';
  if (!$script) { echo json_encode(['error' => 'script manquant']); exit; }
  $body_array = array("Action"=>$action, "EmailRepId"=>($GLOBALS['u'] ?? $GLOBALS['authUser'] ?? '') /* AGENIA-502 */);
  // Construct the API URL
  $url = "http://127.0.0.1:5000/api/Terminal/linux";
  //$url = rawurlencode($url);
  // Set the options for the API call
  $options = [
    'http' => [
      'header' => "Content-Type: application/json\r\n",
      'method' => 'POST',
      'content' => json_encode($body_array),
      'timeout' => 20, 'ignore_errors' => true, // AGENIA-502
    ],
  ];

  // Create a stream context with the options
  $context = stream_context_create($options);
  //str_replace("&amp;", "&", $url) ;
  // Execute the API call
  //$url = html_entity_decode($url);
  $output = @file_get_contents($url, false, $context); // AGENIA-502
  if ($output === false) { http_response_code(502); echo json_encode(['error' => 'Service terminal injoignable', 'tool' => 'api']); exit; }

  $texte = $output;

// Supprime tous les types de retours à la ligne
  $output = str_replace(["\r\n", "\r", "\n"], '', $texte);
  //echo ("===================> :".$url) ;
  // Decode the JSON response
  $response = json_decode($output, true);

  // Check if the API call was successful
  if (isset($response['error'])) {
    echo json_encode(['error' => $response['error'],'body' => $body_array]);
    exit;
  }

  // Get the output from the API response
  $output = $response['result'] ?? '(aucune sortie)';

  // Return the output as JSON
  echo json_encode([
    'tool'   => 'api',
    'output' => $output,
    'url' => $url,
    'commande' => $action
  ]);
  break;



  case 'powershell':
    $script = isset($params['script']) ? $params['script'] : '';
    if (!$script) { echo json_encode(['error' => 'script manquant--']); exit; }
    // Timeout 30s via Start-Job
    $cmd    = 'powershell.exe -NonInteractive -NoProfile -ExecutionPolicy Bypass -Command '
            . escapeshellarg($script) . ' 2>&1';
    $output = shell_exec($cmd);
    echo json_encode([
      'tool'   => 'powershell',
      'output' => $output ?? '(aucune sortie)',
    ]);
    break;

  case 'cmd':
    $command = isset($params['command']) ? $params['command'] : '';
    if (!$command) { echo json_encode(['error' => 'command manquant---']); exit; }
    $output = shell_exec($command . ' 2>&1');
    echo json_encode([
      'tool'   => 'cmd',
      'output' => $output ?? '(aucune sortie)',
    ]);
    break;

  case 'info':
    echo json_encode([
      'tool'   => 'info',
      'output' => php_uname() . "\nPHP " . phpversion(),
    ]);
    break;
}
