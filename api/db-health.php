<?php
// =============================================================================
// api/db-health.php — Sonde santé BDD
// Teste chaque table agenia_* : EXISTS + INSERT + SELECT + UPDATE
// Retourne un badge par table : ok / warn / error
// =============================================================================
require_once __DIR__ . '/../check.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store');

// Admin uniquement
if (!$GLOBALS['isAdmin']) {
  http_response_code(403);
  echo json_encode(['error' => 'Accès refusé']);
  exit;
}

debug_log('DB_HEALTH', 'START', ['user' => $GLOBALS['authUser']]);

$tables = [
  'agenia_guest_sessions',
  'agenia_invite_tokens',
  'agenia_prospect_configs',
  'agenia_modules',
  'agenia_token_usage',
];

$results = [];

// ── Test connexion PDO ────────────────────────────────────────────────────────
try {
  $pdo = db();
  $results['_connection'] = [
    'label'  => 'Connexion MySQL',
    'status' => 'ok',
    'msg'    => DB_HOST . '/' . DB_NAME,
  ];
  debug_log('DB_HEALTH', 'CONNECTION_OK', ['host' => DB_HOST]);
} catch (Exception $e) {
  $results['_connection'] = [
    'label'  => 'Connexion MySQL',
    'status' => 'error',
    'msg'    => $e->getMessage(),
  ];
  debug_log('DB_HEALTH', 'CONNECTION_FAIL', ['err' => $e->getMessage()]);
  echo json_encode(['ok' => false, 'results' => $results]);
  exit;
}

// ── Test chaque table ─────────────────────────────────────────────────────────
foreach ($tables as $table) {
  $result = ['label' => $table, 'status' => 'ok', 'tests' => []];

  // 1. EXISTS
    try {
        $row = db_row(
            "SELECT TABLE_NAME FROM information_schema.TABLES 
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?",
            [DB_NAME, $table]
        );  
    if (!$row) {
      $result['status'] = 'error';
      $result['tests'][] = 'EXISTS ✗';
      $results[$table]   = $result;
      debug_log('DB_HEALTH', 'TABLE_MISSING', ['table' => $table]);
      continue;
    }
    $result['tests'][] = 'EXISTS ✓';
  } catch (Exception $e) {
    $result['status'] = 'error';
    $result['tests'][] = 'EXISTS ✗ ' . $e->getMessage();
    $results[$table]   = $result;
    continue;
  }

  // 2. INSERT test
  $testId = null;
  try {
    switch ($table) {
      case 'agenia_guest_sessions':
        db_query(
          'INSERT INTO agenia_guest_sessions (token_hash, ip_hash, expires_at) VALUES (?,?,?)',
          ['_health_test_' . time(), '_health_ip_', date('Y-m-d H:i:s', time() + 60)]
        );
        $testId = db()->lastInsertId();
        break;

      case 'agenia_invite_tokens':
        db_query(
          'INSERT INTO agenia_invite_tokens (subscriber_id, token_hash, expires_at) VALUES (?,?,?)',
          [0, '_health_test_' . time(), date('Y-m-d H:i:s', time() + 60)]
        );
        $testId = db()->lastInsertId();
        break;

      case 'agenia_prospect_configs':
        db_query(
          'INSERT INTO agenia_prospect_configs (subscriber_id, system_prompt) VALUES (?,?)',
          [999999, '_health_test_']
        );
        $testId = db()->lastInsertId();
        break;

      case 'agenia_modules':
        // Ne pas insérer — données déjà présentes, juste compter
        $count = db_row('SELECT COUNT(*) as c FROM agenia_modules');
        $result['tests'][] = 'INSERT skipped (données initiales: ' . ($count['c'] ?? 0) . ')';
        $testId = null;
        break;

      case 'agenia_token_usage':
        db_query(
          'INSERT INTO agenia_token_usage (session_id, user_type, provider, model, tokens_in, tokens_out) VALUES (?,?,?,?,?,?)',
          ['_health_test_' . time(), 'admin', 'health', 'test', 0, 0]
        );
        $testId = db()->lastInsertId();
        break;
    }
    if ($table !== 'agenia_modules') {
      $result['tests'][] = 'INSERT ✓';
    }
  } catch (Exception $e) {
    $result['status']  = $result['status'] === 'ok' ? 'warn' : $result['status'];
    $result['tests'][] = 'INSERT ✗ ' . $e->getMessage();
    $results[$table]   = $result;
    debug_log('DB_HEALTH', 'INSERT_FAIL', ['table' => $table, 'err' => $e->getMessage()]);
    continue;
  }

  // 3. SELECT
  if ($testId) {
    try {
      $pk  = 'id';
      $row = db_row("SELECT * FROM `$table` WHERE $pk = ?", [$testId]);
      if ($row) {
        $result['tests'][] = 'SELECT ✓';
      } else {
        $result['status']  = 'warn';
        $result['tests'][] = 'SELECT ✗ (row not found)';
      }
    } catch (Exception $e) {
      $result['status']  = 'warn';
      $result['tests'][] = 'SELECT ✗ ' . $e->getMessage();
    }

    // 4. UPDATE
    try {
      $updated = db_query(
        "UPDATE `$table` SET id = id WHERE id = ?", [$testId]
      )->rowCount();
      $result['tests'][] = 'UPDATE ✓';
    } catch (Exception $e) {
      $result['status']  = 'warn';
      $result['tests'][] = 'UPDATE ✗ ' . $e->getMessage();
    }

    // Nettoyage — supprimer la ligne de test
    try {
      db_query("DELETE FROM `$table` WHERE id = ?", [$testId]);
      $result['tests'][] = 'CLEANUP ✓';
    } catch (Exception $e) {
      $result['tests'][] = 'CLEANUP ✗';
    }
  }

  $results[$table] = $result;
  debug_log('DB_HEALTH', 'TABLE_OK', ['table' => $table, 'tests' => $result['tests']]);
}

$allOk   = array_reduce($results, function($carry, $r) {
  return $carry && $r['status'] === 'ok';
}, true);
$hasWarn = array_reduce($results, function($carry, $r) {
  return $carry || $r['status'] === 'warn';
}, false);

debug_log('DB_HEALTH', 'DONE', ['ok' => $allOk, 'warn' => $hasWarn]);

echo json_encode([
  'ok'      => $allOk,
  'warn'    => $hasWarn,
  'results' => $results,
]);