<?php
// =============================================================================
// settings.php — Sert settings.json au client (config/ protégé par IIS)
// Accessible sans auth — ne contient aucune donnée sensible
// =============================================================================
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store');

$file = __DIR__ . '/config/settings.json';
if (!file_exists($file)) {
  http_response_code(404);
  echo json_encode(['error' => 'settings not found']);
  exit;
}
echo file_get_contents($file);