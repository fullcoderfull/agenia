<?php
// =============================================================================
// api/superadmin-back.php — Point d'entrée superadmin
// Vérification accès + dispatch vers router.php
// =============================================================================
require_once __DIR__ . '/../check.php';
require_once __DIR__ . '/../business/BusinessClient.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store');

if (!($GLOBALS['isSuperAdmin'] ?? false)) {
    debug_log('SUPERADMIN_API', 'ACCESS_DENIED', ['user' => $GLOBALS['authUser']]);
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Accès refusé']);
    exit;
}

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$body   = [];

if ($method === 'POST') {
    $raw  = file_get_contents('php://input');
    $body = json_decode($raw, true) ?? [];
}

debug_log('SUPERADMIN_API', 'REQUEST', ['action' => $action, 'method' => $method]);

require_once __DIR__ . '/superadmin/router.php';
<?php
// =============================================================================
// api/superadmin-back.php — Point d'entrée superadmin
// Vérification accès + dispatch vers router.php
// =============================================================================
require_once __DIR__ . '/../check.php';
require_once __DIR__ . '/../business/BusinessClient.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store');

if (!($GLOBALS['isSuperAdmin'] ?? false)) {
    debug_log('SUPERADMIN_API', 'ACCESS_DENIED', ['user' => $GLOBALS['authUser']]);
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Accès refusé']);
    exit;
}

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$body   = [];

if ($method === 'POST') {
    $raw  = file_get_contents('php://input');
    $body = json_decode($raw, true) ?? [];
}

debug_log('SUPERADMIN_API', 'REQUEST', ['action' => $action, 'method' => $method]);

require_once __DIR__ . '/superadmin/router.php';