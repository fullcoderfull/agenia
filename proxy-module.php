<?php
// =============================================================================
// proxy-module.php — Point d'entrée API pour les modules
// Remplace proxy-mcp.php avec l'architecture modulaire
// =============================================================================
require_once __DIR__ . '/check.php';
require_once __DIR__ . '/lib/debug.php';
require_once __DIR__ . '/lib/modules.php';

debug_request('MODULE');

header('Content-Type: application/json');

// ── Vérification méthode ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['error' => 'POST requis']);
  exit;
}

// ── Parser le body ──────────────────────────────────────────────────────────
$data = json_input();
$name    = sanitize_string($data['module']  ?? '', 64);
$action  = sanitize_string($data['action']  ?? '', 64);
$params  = is_array($data['params'] ?? null) ? $data['params'] : [];
$consent = !empty($data['consent']);

if (!$name || !$action) {
  http_response_code(400);
  echo json_encode(['error' => 'module et action requis']);
  exit;
}

// Commande spéciale _list — lister les modules pour le rôle courant
if ($name === '_list') {
  echo json_encode(['output' => json_encode(modules_list($role)), 'status' => 200]);
  exit;
}

// ── Rôle utilisateur ────────────────────────────────────────────────────────
$role = $GLOBALS['authRole'] ?? '';

// ── Exécuter ────────────────────────────────────────────────────────────────
$result = module_exec($name, $action, $params, $role, $consent);

http_response_code($result['status'] ?? 200);
echo json_encode($result, JSON_UNESCAPED_UNICODE);