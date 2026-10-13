<?php
// =============================================================================
// check-ip.php — Vérifier et débloquer une IP guest
// Version : V1.0
// TEMPORAIRE — supprimer après usage
// =============================================================================
require_once __DIR__ . '/config/secrets.php';
require_once __DIR__ . '/lib/debug.php';
require_once __DIR__ . '/lib/security.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/db_helpers.php';
require_once __DIR__ . '/lib/auth_guest.php';

$ip     = get_client_ip();
$ipHash = guest_hash_ip($ip);

// ── Action déblocage ──────────────────────────────────────────────────────────
if (isset($_GET['unblock'])) {
    db_query(
        'UPDATE agenia_guest_sessions SET blocked_until = NULL WHERE ip_hash = ?',
        [$ipHash]
    );
    db_query(
        'DELETE FROM agenia_guest_sessions WHERE ip_hash = ? AND expires_at < NOW()',
        [$ipHash]
    );
    echo '<p style="color:green;font-family:monospace">✓ IP débloquée — <a href="/guest-init.php">Retenter l\'accès guest</a></p>';
}

// ── Statut IP ─────────────────────────────────────────────────────────────────
$blocked  = guest_ip_is_blocked($ip);
$sessions = db_rows(
    'SELECT id, expires_at, blocked_until, messages_used, created_at
     FROM agenia_guest_sessions WHERE ip_hash = ?
     ORDER BY created_at DESC LIMIT 10',
    [$ipHash]
);

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
<style>
  body { font-family: monospace; background: #0a0a0f; color: #e8e8f0; padding: 24px; }
  table { border-collapse: collapse; width: 100%; }
  th, td { border: 1px solid #2a2a3e; padding: 8px 12px; text-align: left; }
  th { color: #06b6d4; }
  .ok    { color: #10b981; }
  .error { color: #ef4444; }
  a { color: #7c3aed; }
</style>
</head>
<body>
<h2>// Guest IP Check</h2>
<p>IP pseudonymisée : <strong><?= htmlspecialchars($ip) ?></strong></p>
<p>Hash IP : <code><?= htmlspecialchars(substr($ipHash, 0, 16)) ?>...</code></p>
<p>Statut : <strong class="<?= $blocked ? 'error' : 'ok' ?>"><?= $blocked ? '✗ BLOQUÉE' : '✓ OK' ?></strong></p>

<?php if ($blocked): ?>
<p><a href="?unblock=1">→ Débloquer cette IP</a></p>
<?php endif; ?>

<h3>Sessions récentes</h3>
<?php if (empty($sessions)): ?>
<p style="color:#4a4a65">Aucune session trouvée.</p>
<?php else: ?>
<table>
  <tr><th>ID</th><th>Créée</th><th>Expire</th><th>Bloquée jusqu'à</th><th>Messages</th></tr>
  <?php foreach ($sessions as $s): ?>
  <tr>
    <td><?= $s['id'] ?></td>
    <td><?= $s['created_at'] ?></td>
    <td class="<?= strtotime($s['expires_at']) > time() ? 'ok' : 'error' ?>"><?= $s['expires_at'] ?></td>
    <td class="<?= $s['blocked_until'] ? 'error' : 'ok' ?>"><?= $s['blocked_until'] ?? '—' ?></td>
    <td><?= $s['messages_used'] ?></td>
  </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>

<p style="margin-top:24px">
  <a href="/guest-init.php">→ Créer une session guest</a> |
  <a href="/login.php">→ Login</a>
</p>

<p style="color:#4a4a65;font-size:11px;margin-top:32px">⚠ Supprimer ce fichier après usage</p>
</body>
</html>