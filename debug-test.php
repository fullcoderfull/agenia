<?php
// =============================================================================
// debug-test.php — Test autonome utilisant debug_log existant
// Version : V1.1
// TEMPORAIRE — supprimer après usage
// =============================================================================
require_once __DIR__ . '/check.php';

if (!($GLOBALS['isSuperAdmin'] ?? false)) {
    http_response_code(403);
    die('Accès refusé');
}
require_once __DIR__ . '/config/secrets.php';
require_once __DIR__ . '/lib/debug.php';
require_once __DIR__ . '/lib/security.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/db_helpers.php';

// ── Tests ─────────────────────────────────────────────────────────────────────
$results = [];

// 1. debug_log fonctionne
debug_log('DEBUG_TEST', 'START', ['ts' => date('c')]);
$debugLog = __DIR__ . '/logs/debug_' . date('Y-m-d') . '.log';
$results['debug_log']      = file_exists($debugLog);
$results['logs_writable']  = is_writable(__DIR__ . '/logs/');
$results['DEBUG_LOG_value']= defined('DEBUG_LOG') ? (DEBUG_LOG ? 'true' : 'false') : 'non défini';


// 2. license
$results['license_exists'] = file_exists(__DIR__ . '/lib/license.php');
if ($results['license_exists']) {
    ob_start();
    try {
        require_once __DIR__ . '/lib/license.php';
        license_enforce();
        $results['license_ok'] = true;
    } catch (Exception $e) {
        $results['license_ok'] = false;
    }
    $results['license_output'] = ob_get_clean();
}

// 3. BDD
try {
    $row = db_row('SELECT 1 as ok');
    $results['db_ok'] = ($row['ok'] ?? 0) == 1;
} catch (Exception $e) {
    $results['db_ok']  = false;
    $results['db_err'] = $e->getMessage();
}

// 4. Tables
if ($results['db_ok']) {
    foreach (['agenia_guest_sessions','agenia_invite_tokens','agenia_prospect_configs','agenia_modules','agenia_token_usage'] as $t) {
        try {
            $r = db_row("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?", [DB_NAME, $t]);
            $results['tables'][$t] = $r ? true : false;
        } catch (Exception $e) {
            $results['tables'][$t] = false;
        }
    }
}

// 5. Constantes
foreach (['HMAC_SECRET','GUEST_HMAC_SECRET','GUEST_TTL','GUEST_BLOCK_MIN','DB_HOST','DB_NAME','DEBUG_LOG','SUPERADMIN_USER'] as $c) {
    $results['constants'][$c] = defined($c);
}

// Logger le résultat complet
debug_log('DEBUG_TEST', 'RESULTS', $results);

?><!DOCTYPE html>
<html>
<head>
<style>
  body{font-family:monospace;background:#0a0a0f;color:#e8e8f0;padding:24px}
  h2{color:#06b6d4} h3{color:#9090aa;font-size:12px;letter-spacing:.1em;margin:16px 0 8px}
  table{border-collapse:collapse;margin-bottom:12px}
  td,th{border:1px solid #2a2a3e;padding:5px 10px;font-size:12px}
  th{color:#06b6d4}
  .ok{color:#10b981} .error{color:#ef4444} .warn{color:#f59e0b}
  .note{color:#4a4a65;font-size:11px;margin-top:24px}
</style>
</head>
<body>
<h2>// Debug Test <?= date('H:i:s') ?></h2>

<h3>LOGS</h3>
<table>
  <tr><th>Test</th><th>Résultat</th></tr>
  <tr><td>logs/ writable</td><td class="<?= $results['logs_writable'] ? 'ok':'error' ?>"><?= $results['logs_writable'] ? '✓':'✗' ?></td></tr>
  <tr><td>DEBUG_LOG</td><td class="<?= $results['DEBUG_LOG_value']==='true' ? 'ok':'error' ?>"><?= $results['DEBUG_LOG_value'] ?></td></tr>
  <tr><td>debug_*.log créé</td><td class="<?= $results['debug_log'] ? 'ok':'error' ?>"><?= $results['debug_log'] ? '✓ '.$debugLog : '✗ absent' ?></td></tr>
</table>

<h3>LICENSE</h3>
<table>
  <tr><th>Test</th><th>Résultat</th></tr>
  <tr><td>license.php existe</td><td class="<?= $results['license_exists'] ? 'ok':'error' ?>"><?= $results['license_exists'] ? '✓':'✗' ?></td></tr>
  <?php if (isset($results['license_ok'])): ?>
  <tr><td>license_enforce()</td><td class="<?= $results['license_ok'] ? 'ok':'error' ?>"><?= $results['license_ok'] ? '✓ OK':'✗ BLOQUE' ?></td></tr>
  <?php endif; ?>
  <?php if (!empty($results['license_output'])): ?>
  <tr><td>Output émis</td><td class="warn"><?= htmlspecialchars($results['license_output']) ?></td></tr>
  <?php endif; ?>
</table>

<h3>BASE DE DONNÉES</h3>
<table>
  <tr><th>Test</th><th>Résultat</th></tr>
  <tr><td>Connexion MySQL</td><td class="<?= $results['db_ok'] ? 'ok':'error' ?>"><?= $results['db_ok'] ? '✓':('✗ '.htmlspecialchars($results['db_err'] ?? '')) ?></td></tr>
  <?php foreach (($results['tables'] ?? []) as $t => $ok): ?>
  <tr><td><?= $t ?></td><td class="<?= $ok ? 'ok':'error' ?>"><?= $ok ? '✓':'✗ MANQUANTE' ?></td></tr>
  <?php endforeach; ?>
</table>

<h3>CONSTANTES secrets.php</h3>
<table>
  <tr><th>Constante</th><th>Définie</th></tr>
  <?php foreach (($results['constants'] ?? []) as $k => $v): ?>
  <tr><td><?= $k ?></td><td class="<?= $v ? 'ok':'error' ?>"><?= $v ? '✓':'✗' ?></td></tr>
  <?php endforeach; ?>
</table>

<p class="note">Résultats complets dans logs/debug_<?= date('Y-m-d') ?>.log — ⚠ Supprimer après usage</p>
</body>
</html>