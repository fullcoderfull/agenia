// =============================================================================
// modules.js — Dialogue modules + consentement utilisateur
// VoxMind V8
// =============================================================================
'use strict';

var _modulesCache = null;

/**
 * Charger la liste des modules disponibles pour le rôle courant
 */
async function loadModules() {
  if (_modulesCache) { return _modulesCache; }
  try {
    var r = await fetch('/proxy-module.php', {
      method: 'POST', mode: 'same-origin', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ module: '_list', action: 'list', params: {} })
    });
    if (r.ok) {
      var data = await r.json();
      _modulesCache = data.output ? JSON.parse(data.output) : {};
    }
  } catch(e) { console.warn('Modules:', e.message); }
  return _modulesCache || {};
}

/**
 * Exécuter un module avec dialogue consentement si requis
 * @param {string} moduleName  ex: 'terminal'
 * @param {string} action      ex: 'powershell'
 * @param {object} params      ex: { command: 'Get-Date' }
 */
async function execModule(moduleName, action, params) {
  var modules = await loadModules();
  if (!modules[moduleName]) {
    appendMsg('assistant', '⚠ Module ' + moduleName + ' non disponible.');
    return null;
  }

  var mod     = modules[moduleName];
  var consent = false;

  // Demander consentement si requis
  if (mod.consent) {
    consent = await showModuleConsent(mod);
    if (!consent) {
      appendMsg('assistant', '⛔ Exécution annulée par l\'utilisateur.');
      return null;
    }
  } else {
    consent = true;
  }

  // Exécuter
  try {
    var r = await fetch('/proxy-module.php', {
      method: 'POST', mode: 'same-origin', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        module:  moduleName,
        action:  action,
        params:  params,
        consent: consent
      })
    });
    var data = await r.json();

    if (data.needs_consent) {
      consent = await showModuleConsent(mod);
      if (consent) { return execModule(moduleName, action, params); }
      return null;
    }

    if (data.error) {
      appendMsg('assistant', '⚠ [' + mod.icon + ' ' + mod.label + '] ' + data.error);
      return null;
    }

    return data.output;

  } catch(e) {
    appendMsg('assistant', '⚠ Erreur module : ' + e.message);
    return null;
  }
}

/**
 * Dialogue consentement — modal simple
 * @returns {Promise<boolean>}
 */
function showModuleConsent(mod) {
  return new Promise(function(resolve) {
    var overlay = document.createElement('div');
    overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:500;display:flex;align-items:center;justify-content:center;';

    var theme  = document.documentElement.getAttribute('data-theme') || 'dark';
    var bg     = theme === 'light' ? '#fff' : 'var(--surface, #12121a)';
    var border = theme === 'light' ? '#ddd' : 'var(--border, #2a2a3e)';
    var text   = theme === 'light' ? '#333' : 'var(--text, #e8e8f0)';

    var card = document.createElement('div');
    card.style.cssText = 'background:' + bg + ';border:1px solid ' + border + ';border-radius:12px;padding:28px 24px;max-width:380px;width:90%;font-family:system-ui,sans-serif;color:' + text + ';';
    card.innerHTML = '<div style="font-size:28px;margin-bottom:12px;">' + (mod.icon || '🔧') + '</div>'
      + '<div style="font-family:monospace;font-size:13px;font-weight:700;margin-bottom:8px;">' + (mod.label || 'Module') + '</div>'
      + '<p style="font-size:13px;line-height:1.6;opacity:0.8;margin-bottom:20px;">'
      + (mod.description || 'Ce module souhaite exécuter une action sur votre système.')
      + '<br><br>Votre accord est nécessaire pour continuer.</p>'
      + '<div style="display:flex;gap:10px;">'
      + '<button id="_mod_accept" style="flex:1;padding:10px;border:none;border-radius:8px;background:var(--accent,#7c3aed);color:#fff;cursor:pointer;font-family:monospace;font-size:12px;letter-spacing:.05em;">✓ AUTORISER</button>'
      + '<button id="_mod_deny"   style="flex:1;padding:10px;border:1px solid ' + border + ';border-radius:8px;background:none;color:' + text + ';cursor:pointer;font-family:monospace;font-size:12px;letter-spacing:.05em;">✕ REFUSER</button>'
      + '</div>';

    overlay.appendChild(card);
    document.body.appendChild(overlay);

    card.querySelector('#_mod_accept').addEventListener('click', function() {
      overlay.remove();
      resolve(true);
    });
    card.querySelector('#_mod_deny').addEventListener('click', function() {
      overlay.remove();
      resolve(false);
    });
    overlay.addEventListener('click', function(e) {
      if (e.target === overlay) { overlay.remove(); resolve(false); }
    });
  });
}

/**
 * Afficher le résultat d'un module dans le chat
 */
function appendModuleResult(moduleName, output) {
  var modules = _modulesCache || {};
  var mod     = modules[moduleName] || { icon: '🔧', label: moduleName };
  var text    = '```\n' + output + '\n```';
  var bt      = appendMsg('assistant', '');
  bt.innerHTML = renderMsg((mod.icon || '🔧') + ' **[' + mod.label + ']**\n' + text);
}