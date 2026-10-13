<?php
// =============================================================================
// lib/debug.php — Log technique VoxMind
// Activer via config/secrets.php : define('DEBUG_LOG', true);
// Fichier : logs/debug_YYYY-MM-DD.log
// =============================================================================
return;


function debug_log(string $context, string $msg, array $data = []): void {
    try {
      // if (!defined('DEBUG_LOG') || !DEBUG_LOG) { return; }
      $logDir  = __DIR__ . '/../logs/';
      if (!is_dir($logDir)) { @mkdir($logDir, 0750, true); }
      $logFile = $logDir . 'debug_' . date('Y-m-d') . '.log';
      $entry   = json_encode([
        'ts'      => date('c'),
        'context' => $context,
        'msg'     => $msg,
        'data'    => $data,
        'mem'     => round(memory_get_usage(true) / 1024) . 'KB',
      ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
      file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
    } catch (\Throwable $th) {
      //throw $th;
    }
}

function debug_request(string $context): void {
  // Lire php://input une seule fois — le stream ne peut être lu qu'une fois
  static $body = null;
  if ($body === null) {
    $body = file_get_contents('php://input');
  }
  debug_log($context, 'REQUEST', [
    'method'  => $_SERVER['REQUEST_METHOD'] ?? '',
    'uri'     => $_SERVER['REQUEST_URI']    ?? '',
    'ct'      => $_SERVER['CONTENT_TYPE']   ?? '',
    'ip'      => $_SERVER['REMOTE_ADDR']    ?? '',
    'cookies' => array_keys($_COOKIE),
    'body_sz' => strlen($body),
  ]);
}

function debug_response(string $context, int $status, string $msg = ''): void {
  debug_log($context, 'RESPONSE ' . $status, ['msg' => $msg]);
}