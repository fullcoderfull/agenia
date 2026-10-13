// =============================================================================
// js/superadmin.js — Logique interface super admin
// i18n : utilise t() depuis i18n.js (même système que index.php)
// =============================================================================
'use strict';

var SA_VERSION = 'V1.1';

// ── Navigation ────────────────────────────────────────────────────────────────

function saShowSection(name, triggerEl) {
  document.querySelectorAll('.sa-section').forEach(function(el) { el.classList.remove('active'); });
  document.querySelectorAll('.sa-nav-item').forEach(function(el) { el.classList.remove('active'); });
  var section = document.getElementById('section-' + name);
  if (section) { section.classList.add('active'); }
  if (triggerEl) { triggerEl.classList.add('active'); }
  if (name === 'dashboard') { saLoadQuickStats(); }
  if (name === 'upgrades')  { saLoadUpgrades(); }
  if (name === 'capacites') { saLoadCapacites(); }
}

document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('.sa-nav-item[data-section]').forEach(function(el) {
    el.addEventListener('click', function(e) {
      e.preventDefault();
      saShowSection(el.getAttribute('data-section'), el);
    });
  });
  saLoadQuickStats();
  if (typeof i18nApply === 'function') { i18nApply(); }
});

// ── Toast ─────────────────────────────────────────────────────────────────────

function saShowResult(msg, type) {
  var el = document.getElementById('action-result');
  if (!el) { return; }
  el.textContent = msg;
  el.className   = 'show ' + (type || 'ok');
  setTimeout(function() { el.classList.remove('show'); }, 4000);
}

// ── API helper ────────────────────────────────────────────────────────────────

function saApi(action, method, body) {
  method = method || 'GET';
  var url  = '/api/superadmin-back.php?action=' + encodeURIComponent(action);
  var opts = { method: method, credentials: 'same-origin', headers: { 'Content-Type': 'application/json' } };
  if (body) { opts.body = JSON.stringify(body); }
  return fetch(url, opts).then(function(r) { return r.json(); });
}

function saRow(label, val) {
  return '<tr><td class="sa-dim" style="font-family:var(--mono);font-size:11px;white-space:nowrap;">'
    + label + '</td><td>' + (val || '-') + '</td></tr>';
}

function saStatBox(label, val) {
  return '<div class="health-item"><div class="h-label">' + label + '</div><div class="h-value">' + val + '</div></div>';
}


// ── Dashboard ─────────────────────────────────────────────────────────────────

function saLoadQuickStats() {
  saApi('stats').then(function(data) {
    if (!data.ok) { return; }
    ['active','pro','wl','pending'].forEach(function(k) {
      var el = document.getElementById('stat-' + k);
      if (el) { el.textContent = data[k] || 0; }
    });
  }).catch(function() {});
}

// ── Clients ───────────────────────────────────────────────────────────────────

function saSearchClient() {
  var id  = document.getElementById('client-search').value.trim();
  var res = document.getElementById('client-result');
  if (!id || !res) { return; }
  res.innerHTML = '<span class="sa-dim">' + t('saLoading') + '</span>';
  saApi('client&id=' + encodeURIComponent(id)).then(function(data) {
    if (!data.ok || !data.client) {
      res.innerHTML = '<span class="sa-error">' + t('saNotFound') + '</span>'; return;
    }
    var c = data.client;
    res.innerHTML = '<table class="sa-table" style="margin-top:8px"><tbody>'
      + saRow('ID', c.id) + saRow('Email', c.email)
      + saRow(t('saPlan'), '<span class="badge info">' + (c.plan||'-') + '</span>')
      + saRow('Status', c.status) + saRow('Stripe', c.stripe_customer_id||'—')
      + '</tbody></table>';
  });
}

function saUpdatePlan() {
  var userId = document.getElementById('upgrade-user-id').value.trim();
  var status = document.getElementById('upgrade-status').value;
  if (!userId) { saShowResult(t('saUserIdRequired'), 'error'); return; }
  saApi('update_plan', 'POST', { userId: userId, status: status }).then(function(data) {
    saShowResult(data.ok ? '✓ ' + t('saPlanUpdated') : '✗ ' + (data.error||'Erreur'), data.ok ? 'ok' : 'error');
  });
}

// ── White Label ───────────────────────────────────────────────────────────────

function saSetWhiteLabel(enabled) {
  var userId = document.getElementById('wl-user-id').value.trim();
  var note   = document.getElementById('wl-note').value.trim();
  if (!userId) { saShowResult(t('saUserIdRequired'), 'error'); return; }
  saApi('white_label', 'POST', { userId: userId, enabled: enabled, note: note }).then(function(data) {
    saShowResult(
      data.ok ? '✓ White label ' + (enabled ? t('saEnabled') : t('saDisabled')) : '✗ ' + (data.error||'Erreur'),
      data.ok ? 'ok' : 'error'
    );
  });
}

// ── Upgrades ──────────────────────────────────────────────────────────────────

function saLoadUpgrades() {
  saApi('upgrades').then(function(data) {
    var tbody = document.getElementById('upgrades-body');
    if (!tbody) { return; }
    if (!data.ok || !data.upgrades || !data.upgrades.length) {
      tbody.innerHTML = '<tr><td colspan="4" class="sa-dim">' + t('saNoPending') + '</td></tr>'; return;
    }
    tbody.innerHTML = data.upgrades.map(function(u) {
      return '<tr><td style="font-family:var(--mono);font-size:11px;">' + (u.email||u.user_id) + '</td>'
        + '<td><span class="badge info">' + u.requested_plan + '</span></td>'
        + '<td>' + (u.message||'-') + '</td>'
        + '<td><button class="btn-sm primary" onclick="saValidateUpgrade(\'' + u.user_id + '\',\'' + u.requested_plan + '\')">✓</button></td></tr>';
    }).join('');
  });
}

function saValidateUpgrade(userId, plan) {
  saApi('validate_upgrade', 'POST', { userId: userId, status: plan }).then(function(data) {
    saShowResult(data.ok ? '✓ ' + t('saUpgradeValidated') : '✗ Erreur', data.ok ? 'ok' : 'error');
    if (data.ok) { saLoadUpgrades(); }
  });
}

// ── Billing ───────────────────────────────────────────────────────────────────

function saCheckBalance() {
  var userId = document.getElementById('balance-user-id').value.trim();
  var res    = document.getElementById('balance-result');
  if (!userId || !res) { return; }
  res.innerHTML = '<span class="sa-dim">' + t('saLoading') + '</span>';
  saApi('balance&id=' + encodeURIComponent(userId)).then(function(data) {
    if (!data.ok) { res.innerHTML = '<span class="sa-error">' + (data.error||'Erreur') + '</span>'; return; }
    var b = data.balance;
    res.innerHTML = '<div style="display:flex;gap:16px;flex-wrap:wrap;margin-top:8px;">'
      + saStatBox(t('saUsed'), (b.tokensUsed||0).toLocaleString())
      + saStatBox(t('saLimit'), b.tokensLimit===-1 ? '∞' : (b.tokensLimit||0).toLocaleString())
      + saStatBox(t('saPlan'), '<span class="badge info">'+(b.plan||'-')+'</span>')
      + saStatBox(t('saCredits'), (b.creditsUsed||0).toFixed(4))
      + '</div>';
  });
}

// ── Rapport ───────────────────────────────────────────────────────────────────

function saDownloadReport(format) {
  var period = document.getElementById('report-period').value;
  if (!period) { saShowResult(t('saPeriodRequired'), 'error'); return; }
  var url = '/api/superadmin-back.php?action=report&period=' + encodeURIComponent(period) + '&format=' + format;
  if (format === 'word') { var a = document.createElement('a'); a.href=url; a.download='agenia-report-'+period+'.docx'; a.click(); }
  else { window.open(url, '_blank'); }
}

// ── API Health ────────────────────────────────────────────────────────────────

function saRefreshApiHealth() {
  var el = document.getElementById('api-health-result');
  if (!el) { return; }
  el.textContent = t('saLoading');
  saApi('api_health').then(function(data) {
    if (data.ok && data.health) {
      var h = data.health;
      el.innerHTML = '<span class="badge '+(h.ok?'ok':'error')+'">'+(h.ok?'● ONLINE':'● OFFLINE')+'</span>'
        + ' v'+(h.version||'-')+' | '+(h.region||'-')+' | DB: '+(h.dbStatus||'-')+' | Stripe: '+(h.stripeStatus||'-');
    } else {
      el.innerHTML = '<span class="badge error">● '+t('saApiDown')+'</span>';
    }
  });
}

// ── Logs ──────────────────────────────────────────────────────────────────────

function saLoadLogs() {
  var el = document.getElementById('logs-content');
  if (!el) { return; }
  el.textContent = t('saLoading');
  saApi('logs').then(function(data) { el.textContent = data.ok && data.logs ? data.logs : (data.error||'Erreur'); });
}

// ── Capacités Agent ───────────────────────────────────────────────────────────

var _saCapacites = [];
var _saRoles     = ['guest','free','pro','admin','superadmin'];
var _saRoleLabels= { guest:'Guest', free:'Free', pro:'Pro', admin:'Admin', superadmin:'Super Admin' };

function saLoadCapacites() {
  fetch('/backend/api.php', {
    method:'POST', credentials:'same-origin',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify({ action: 'capacites_list' })
  }).then(function(r){return r.json();}).then(function(data) {
    if (!data.ok) { saShowResult('✗ '+(data.message||'Erreur'), 'error'); return; }
    _saCapacites = data.capacites || [];
    saRenderCapacites();
  });
}

function saRenderCapacites() {
  var el = document.getElementById('capacites-list');
  if (!el) { return; }
  if (!_saCapacites.length) {
    el.innerHTML = '<span class="sa-dim">'+t('saNoCapacites')+'</span>'; return;
  }
  var html = '<table class="sa-table"><thead><tr>'
    + '<th>'+t('saCapacity')+'</th>'
    + _saRoles.map(function(r){return '<th>'+_saRoleLabels[r]+'</th>';}).join('')
    + '<th>'+t('saActive')+'</th>'
    + '</tr></thead><tbody>';

  _saCapacites.forEach(function(cap) {
    var allowed = cap.roles ? cap.roles.split(',').map(function(r){return r.trim();}) : [];
    html += '<tr data-slug="'+cap.slug+'">'
      + '<td style="font-family:var(--mono);font-size:12px;">'
      + '<div>'+(voxLang==='fr'?cap.label_fr:cap.label_en)+'</div>'
      + '<div style="font-size:10px;color:var(--text-dim);">'+cap.slug+'</div></td>';
    _saRoles.forEach(function(role) {
      var isSA    = role === 'superadmin';
      var checked = (isSA || allowed.includes(role)) ? 'checked' : '';
      html += '<td style="text-align:center;"><input type="checkbox" class="cap-role-cb"'
        +' data-slug="'+cap.slug+'" data-role="'+role+'" '+(isSA?'disabled checked':checked)+'></td>';
    });
    html += '<td style="text-align:center;"><input type="checkbox" class="cap-active-cb" data-slug="'+cap.slug+'" '+(cap.active==1?'checked':'')+'></td></tr>';
  });
  el.innerHTML = html + '</tbody></table>';
}

function saSaveCapacites() {
  var rows = document.querySelectorAll('#capacites-list tr[data-slug]');
  var promises = [];
  rows.forEach(function(row) {
    var slug  = row.getAttribute('data-slug');
    var roles = ['superadmin'];
    row.querySelectorAll('.cap-role-cb:not(:disabled)').forEach(function(cb) { if (cb.checked) { roles.push(cb.getAttribute('data-role')); } });
    var activeCb = row.querySelector('.cap-active-cb');
    promises.push(fetch('/backend/api.php', {
      method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ action:'capacites_save', slug:slug, roles:roles, active: activeCb?activeCb.checked:true })
    }).then(function(r){return r.json();}));
  });
  Promise.all(promises).then(function(results) {
    var ok = results.every(function(r){return r.ok;});
    saShowResult(ok ? '✓ '+t('saCapacitesSaved') : '⚠ '+t('saPartialError'), ok?'ok':'error');
    if (ok) { saLoadCapacites(); }
  });
}

// ── Groupes Admin ─────────────────────────────────────────────────────────────

function saAddGroupMember() {
  var admin  = document.getElementById('grp-admin-email').value.trim();
  var member = document.getElementById('grp-member-email').value.trim();
  if (!admin||!member) { saShowResult(t('saEmailsRequired'), 'error'); return; }
  saApi('group_add','POST',{admin_email:admin,member_email:member}).then(function(data) {
    saShowResult(data.ok?'✓ '+t('saMemberAdded'):'✗ '+(data.error||'Erreur'), data.ok?'ok':'error');
    if (data.ok) { saLoadGroupMembers(); }
  });
}

function saRemoveGroupMember() {
  var admin  = document.getElementById('grp-admin-email').value.trim();
  var member = document.getElementById('grp-member-email').value.trim();
  if (!admin||!member) { saShowResult(t('saEmailsRequired'), 'error'); return; }
  saApi('group_remove','POST',{admin_email:admin,member_email:member}).then(function(data) {
    saShowResult(data.ok?'✓ '+t('saMemberRemoved'):'✗ '+(data.error||'Erreur'), data.ok?'ok':'error');
    if (data.ok) { saLoadGroupMembers(); }
  });
}

function saLoadGroupMembers() {
  var admin = document.getElementById('grp-filter-email').value.trim();
  var el    = document.getElementById('group-members-list');
  if (!admin||!el) { return; }
  el.innerHTML = '<span class="sa-dim">'+t('saLoading')+'</span>';
  saApi('group_list&admin='+encodeURIComponent(admin)).then(function(data) {
    if (!data.ok||!data.members||!data.members.length) {
      el.innerHTML = '<span class="sa-dim">'+t('saNoMembers')+'</span>'; return;
    }
    el.innerHTML = '<table class="sa-table"><thead><tr>'
      +'<th>'+t('saMember')+'</th><th>'+t('saSince')+'</th>'
      +'</tr></thead><tbody>'
      +data.members.map(function(m){
        return '<tr><td style="font-family:var(--mono);font-size:11px;">'+m.member_email+'</td>'
          +'<td style="font-family:var(--mono);font-size:10px;color:var(--text-dim);">'+(m.created_at||'-')+'</td></tr>';
      }).join('')+'</tbody></table>';
  });
}

// ── Thème ─────────────────────────────────────────────────────────────────────

function toggleTheme() {
  var html = document.documentElement;
  var next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
  html.setAttribute('data-theme', next);
  localStorage.setItem('vox-theme', next);
}