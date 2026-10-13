<?php

// ── PANNE4_TOGGLE : traitement direct du POST de moduleToggle() ───────────────
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && empty($GLOBALS['authRole'])) {
    require_once __DIR__ . '/check.php';
    header('Content-Type: application/json');
    $in  = json_decode(file_get_contents('php://input'), true) ?? [];
    $act = $in['action'] ?? '';
    if ($act === 'toggle' || $act === 'guest_allowed') {
        if (!($GLOBALS['isAdmin'] ?? false) && !($GLOBALS['isSuperAdmin'] ?? false)) {
            http_response_code(403); echo json_encode(['ok' => false, 'error' => 'Acces refuse']); exit;
        }
        $user     = $GLOBALS['authUser'] ?? '';
        $expected = hash_hmac('sha256', $user . date('YmdH'), HMAC_SECRET);
        if (!hash_equals($expected, (string)($in['csrf_token'] ?? ''))) {
            http_response_code(403); echo json_encode(['ok' => false, 'error' => 'CSRF invalide']); exit;
        }
        $slug = (string)($in['slug'] ?? '');
        if (!preg_match('/^[a-z_]+$/', $slug)) {
            http_response_code(400); echo json_encode(['ok' => false, 'error' => 'slug invalide']); exit;
        }
        try {
            if ($act === 'toggle') {
                db_query('UPDATE agenia_modules SET active = ?, updated_at = NOW() WHERE slug = ?', [(int)!empty($in['active']), $slug]);
            } else {
                db_query('UPDATE agenia_modules SET guest_allowed = ?, updated_at = NOW() WHERE slug = ?', [(int)!empty($in['allowed']), $slug]);
            }
            echo json_encode(['ok' => true, 'slug' => $slug]);
        } catch (Exception $e) {
            http_response_code(500); echo json_encode(['ok' => false, 'error' => 'Erreur serveur']);
        }
        exit;
    }
}
// =============================================================================
// modules-panel.php — Volet droit modules
// Inclus dans index.php
// =============================================================================
$_mp_lang        = defined('UI_LANG') ? UI_LANG : 'fr';
$_mp_isAdmin     = $GLOBALS['isAdmin']      ?? false;
$_mp_isSuperAdmin= $GLOBALS['isSuperAdmin'] ?? false;
$_mp_isGuest     = $GLOBALS['isGuest']      ?? false;
$_mp_isDemo      = $GLOBALS['isDemo']       ?? false;
$_mp_role        = $GLOBALS['authRole']     ?? 'guest';
$_mp_user        = $GLOBALS['authUser']     ?? '';

$_mp_csrf = hash_hmac('sha256', $_mp_user . date('YmdH'), HMAC_SECRET);

// Vérifier si le module agent est actif en BDD
$_mp_agentModuleActive = false;
$_mp_userHasTerminal   = false;
try {
    // Module actif globalement ?
    $modRow = db_row('SELECT active FROM agenia_modules WHERE slug = ? LIMIT 1', ['agent_terminal']);
    $_mp_agentModuleActive = $modRow && (bool)$modRow['active'];

    // Capacité terminal pour ce rôle ?
    if ($_mp_agentModuleActive && !$_mp_isGuest && !$_mp_isDemo) {
        $capRow = db_row('SELECT roles, active FROM capacites WHERE slug = ? LIMIT 1', ['terminal']);
        if ($capRow && (bool)$capRow['active']) {
            $hierarchy   = ['guest', 'free', 'pro', 'admin', 'superadmin'];
            $userIndex   = array_search($_mp_role, $hierarchy);
            $allowedRoles = array_map('trim', explode(',', $capRow['roles']));
            foreach ($allowedRoles as $ar) {
                $arIndex = array_search($ar, $hierarchy);
                if ($arIndex !== false && $userIndex !== false && $userIndex >= $arIndex) {
                    $_mp_userHasTerminal = true;
                    break;
                }
            }
        }
    }
    // Superadmin a toujours accès
    if ($_mp_isSuperAdmin) { $_mp_userHasTerminal = true; }
} catch (Exception $_mp_e) {
    debug_log('MODULES_PANEL', 'CAPACITY_CHECK_ERROR', ['err' => $_mp_e->getMessage()]);
}

debug_log('MODULES_PANEL', 'RENDER', ['role' => $_mp_role, 'agent' => $_mp_userHasTerminal]);
?>

<style>
  #modules-panel {
    width: 320px; background: var(--surface); border-left: 1px solid var(--border);
    overflow-y: auto; flex-shrink: 0;
    transform: translateX(100%);
    position: absolute; top: 0; right: 0; bottom: 0; z-index: 20;
    transition: transform 0.3s cubic-bezier(0.4,0,0.2,1);
  }
  #modules-panel.open { transform: translateX(0); position: relative; }

  .modules-header {
    padding: 16px 20px; border-bottom: 1px solid var(--border);
    font-family: var(--mono); font-size: 11px; color: var(--accent2);
    letter-spacing: 0.12em; text-transform: uppercase;
    display: flex; justify-content: space-between; align-items: center;
  }
  .modules-section { padding: 16px 20px; border-bottom: 1px solid var(--border); }
  .modules-section-title {
    font-family: var(--mono); font-size: 10px; color: var(--text-dim);
    letter-spacing: 0.15em; text-transform: uppercase; margin-bottom: 12px;
  }
  .module-row {
    display: flex; align-items: center; justify-content: space-between;
    padding: 8px 0; border-bottom: 1px solid rgba(255,255,255,0.04);
  }
  .module-row:last-child { border-bottom: none; }
  .module-info { flex: 1; }
  .module-label {
    font-family: var(--mono); font-size: 12px; color: var(--text);
    display: flex; align-items: center; gap: 6px;
  }
  .module-plan {
    font-size: 9px; padding: 2px 6px; border-radius: 4px;
    font-family: var(--mono); letter-spacing: 0.06em;
  }
  .module-plan.guest      { background: rgba(16,185,129,0.15); color: var(--green); }
  .module-plan.free       { background: rgba(6,182,212,0.15);  color: var(--accent2); }
  .module-plan.pro        { background: rgba(124,58,237,0.15); color: var(--accent); }
  .module-plan.enterprise { background: rgba(245,158,11,0.15); color: var(--yellow); }

  /* Message demo agent */
  .agent-demo-msg {
    margin-top: 6px;
    font-family: var(--mono); font-size: 10px;
    color: var(--accent2);
    background: rgba(6,182,212,0.08);
    border: 1px solid rgba(6,182,212,0.25);
    border-radius: 6px; padding: 6px 10px;
    line-height: 1.5;
  }
  .agent-demo-msg a {
    color: var(--accent); text-decoration: underline; cursor: pointer;
  }

  .switch { position: relative; width: 44px; height: 24px; flex-shrink: 0; }
  .switch input { opacity: 0; width: 0; height: 0; }
  .switch-track {
    position: absolute; inset: 0; background: var(--border);
    border-radius: 24px; cursor: pointer; transition: background 0.25s;
  }
  .switch-thumb {
    position: absolute; width: 18px; height: 18px; top: 3px; left: 3px;
    background: var(--text-muted); border-radius: 50%; transition: all 0.25s;
  }
  .switch input:checked ~ .switch-track { background: var(--accent); }
  .switch input:checked ~ .switch-thumb { transform: translateX(20px); background: #fff; }
  .switch input:disabled ~ .switch-track { opacity: 0.4; cursor: not-allowed; }

  .modules-toggle {
    background: none; border: 1px solid var(--border); color: var(--text-muted);
    font-family: var(--mono); font-size: 11px; padding: 5px 12px;
    border-radius: var(--radius); cursor: pointer;
    letter-spacing: 0.08em; transition: all var(--t);
  }
  .modules-toggle:hover  { border-color: var(--accent2); color: var(--accent2); }
  .modules-toggle.active { border-color: var(--accent2); color: var(--accent2); background: rgba(6,182,212,0.1); }

  .logout-btn {
    background: none; border: 1px solid var(--border); color: var(--text-muted);
    font-family: var(--mono); font-size: 11px; padding: 5px 12px;
    border-radius: var(--radius); cursor: pointer;
    letter-spacing: 0.08em; transition: all var(--t);
    text-decoration: none; display: inline-flex; align-items: center; gap: 5px;
  }
  .logout-btn:hover { border-color: var(--red); color: var(--red); }

  @media (max-width: 768px) {
    #modules-panel { width: 100%; position: fixed; top: 0; right: 0; bottom: 0; z-index: 50; }
    #modules-panel.open { transform: translateX(0); position: fixed; }
  }
</style>

<div id="modules-panel">
  <div class="modules-header">
    <span>⊞ <?php echo $_mp_lang === 'fr' ? 'MODULES' : 'MODULES'; ?></span>
    <button onclick="toggleModulesPanel()" style="background:none;border:none;color:var(--text-muted);cursor:pointer;font-size:16px;">✕</button>
  </div>

  <?php
  $_mp_modules = [];
  try {
    $_mp_rows = db_rows(
      'SELECT slug, label_fr, label_en, plan_min, guest_allowed, guest_max_uses, active
       FROM agenia_modules ORDER BY id ASC'
    );
    foreach ($_mp_rows as $_mp_row) {
      $_mp_modules[$_mp_row['slug']] = $_mp_row;
    }
  } catch (Exception $_mp_e) {
    debug_log('MODULES_PANEL', 'DB_ERROR', ['err' => $_mp_e->getMessage()]);
    $sf = __DIR__ . '/config/settings.json';
    if (file_exists($sf)) {
      $_mp_s = json_decode(file_get_contents($sf), true) ?? [];
      foreach (($_mp_s['modules'] ?? []) as $slug => $cfg) {
        $_mp_modules[$slug] = [
          'slug' => $slug, 'label_fr' => $slug, 'label_en' => $slug,
          'plan_min' => 'guest', 'guest_allowed' => false,
          'guest_max_uses' => null, 'active' => $cfg['enabled'] ?? true,
        ];
      }
    }
  }

  $_mp_groups = ['guest' => [], 'free' => [], 'pro' => [], 'enterprise' => []];
  foreach ($_mp_modules as $slug => $mod) {
    $plan = $mod['plan_min'] ?? 'free';
    if (!isset($_mp_groups[$plan])) { $_mp_groups[$plan] = []; }
    $_mp_groups[$plan][] = $mod;
  }

  $_mp_group_labels = [
    'guest'      => $_mp_lang === 'fr' ? 'Accès libre'     : 'Free access',
    'free'       => $_mp_lang === 'fr' ? 'Compte gratuit'  : 'Free account',
    'pro'        => 'Pro',
    'enterprise' => $_mp_lang === 'fr' ? 'Entreprise'      : 'Enterprise',
  ];

  foreach ($_mp_groups as $_mp_plan => $_mp_mods):
    if (empty($_mp_mods)) { continue; }
  ?>
  <div class="modules-section">
    <div class="modules-section-title">▸ <?php echo htmlspecialchars($_mp_group_labels[$_mp_plan]); ?></div>

    <?php foreach ($_mp_mods as $_mp_mod):
      $lbl    = $_mp_lang === 'fr' ? $_mp_mod['label_fr'] : $_mp_mod['label_en'];
      $active = (bool)$_mp_mod['active'];
      $slug   = $_mp_mod['slug'];

      if ($_mp_isGuest && !$_mp_mod['guest_allowed']) { continue; }

      // Pour le module agent, afficher selon les droits
      $isAgentModule = ($slug === 'agent_terminal');
    ?>
    <div class="module-row" id="module-row-<?php echo htmlspecialchars($slug); ?>">
      <div class="module-info">
        <div class="module-label">
          <?php if ($isAgentModule): ?>
            <?php echo $_mp_lang === 'fr' ? '⚡ Agent' : '⚡ Agent'; ?>
          <?php else: ?>
            <?php echo htmlspecialchars($lbl); ?>
          <?php endif; ?>
          <span class="module-plan <?php echo htmlspecialchars($_mp_plan); ?>">
            <?php echo htmlspecialchars($_mp_plan); ?>
          </span>
        </div>

        <?php if ($_mp_mod['guest_max_uses']): ?>
        <div style="font-family:var(--mono);font-size:9px;color:var(--text-dim);margin-top:2px;">
          <?php echo $_mp_lang === 'fr'
            ? 'Max ' . $_mp_mod['guest_max_uses'] . ' utilisations'
            : 'Max ' . $_mp_mod['guest_max_uses'] . ' uses'; ?>
        </div>
        <?php endif; ?>

        <?php
        // Message demo/guest pour le module agent
        if ($isAgentModule && ($_mp_isDemo || $_mp_isGuest) && $active):
        ?>
        <div class="agent-demo-msg">
          <?php if ($_mp_lang === 'fr'): ?>
            ⚡ Mode Agent disponible après connexion.<br>
            <a href="/login.php">Se connecter →</a>
          <?php else: ?>
            ⚡ Agent mode available after sign in.<br>
            <a href="/login.php">Sign in →</a>
          <?php endif; ?>
        </div>
        <?php elseif ($isAgentModule && !$_mp_isDemo && !$_mp_isGuest && !$_mp_userHasTerminal && $active): ?>
        <div class="agent-demo-msg" style="border-color:rgba(245,158,11,0.3);color:var(--yellow);">
          <?php echo $_mp_lang === 'fr'
            ? '⚠ Non disponible pour votre compte.'
            : '⚠ Not available for your account.'; ?>
        </div>
        <?php endif; ?>
      </div>

      <label class="switch">
        <input type="checkbox"
          id="mod-<?php echo htmlspecialchars($slug); ?>"
          <?php echo $active ? 'checked' : ''; ?>
          <?php echo (!$_mp_isAdmin && !$_mp_isSuperAdmin) ? 'disabled' : ''; ?>
          onchange="moduleToggle('<?php echo htmlspecialchars($slug); ?>', this.checked)">
        <div class="switch-track"></div>
        <div class="switch-thumb"></div>
      </label>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endforeach; ?>

  <?php if ($_mp_isAdmin || $_mp_isSuperAdmin): ?>
  <div class="modules-section">
    <div style="font-family:var(--mono);font-size:10px;color:var(--text-dim);text-align:center;padding:4px 0;">
      <?php echo $_mp_lang === 'fr'
        ? 'Modifications en temps réel · BDD'
        : 'Real-time changes · DB'; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<script>
var _modulesCsrf = '<?php echo $_mp_csrf; ?>';
var _modulesOpen = false;

// État agent disponible — injecté PHP, utilisé par api.js
var voxAgentEnabled = <?php echo $_mp_userHasTerminal ? 'true' : 'false'; ?>;

function toggleModulesPanel() {
  _modulesOpen = !_modulesOpen;
  var panel = document.getElementById('modules-panel');
  var btn   = document.getElementById('modules-btn');
  if (panel) { panel.classList.toggle('open', _modulesOpen); }
  if (btn)   { btn.classList.toggle('active', _modulesOpen); }
}

function moduleToggle(slug, active) {
  if (!voxIsAdmin && !voxIsSuperAdmin) { return; }
  fetch('/modules-panel.php', {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'toggle', slug: slug, active: active, csrf_token: _modulesCsrf })
  })
  .then(function(r) { return r.json(); })
  .then(function(data) {
    if (!data.ok) {
      var el = document.getElementById('mod-' + slug);
      if (el) { el.checked = !active; }
    }
  })
  .catch(function() {
    var el = document.getElementById('mod-' + slug);
    if (el) { el.checked = !active; }
  });
}
</script>