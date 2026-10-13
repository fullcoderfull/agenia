// =============================================================================
// js/providers.js — Providers LLM / TTS / STT dynamiques
// Lit settings.json (section "providers"), peuple les selects,
// gère une clé API par provider stockée dans localStorage
// =============================================================================
'use strict';

// Cache des providers chargés depuis settings.json
var _providers = null;

// Clés API par provider — stockées dans localStorage séparément de voxmind_config
// Format : vox_apikey_{type}_{slug}  ex: vox_apikey_llm_openai
var _apiKeys = {};

// ── Chargement ────────────────────────────────────────────────────────────────

/**
 * Reçoit directement la sous-clé "providers" (ex: data.providers depuis
 * config_load), PAS l'objet complet — d'où providersData et non settingsJson.
 */
function providersLoad(providersData) {
  _providers = providersData || null;
  if (!_providers) { return; }
  _loadApiKeys();
  providersRenderAll();
}

/** Charger toutes les clés API depuis localStorage */
function _loadApiKeys() {
  ['llm', 'tts', 'stt'].forEach(function(type) {
    var p = _providers[type];
    if (!p || !p.list) { return; }
    Object.keys(p.list).forEach(function(slug) {
      var k = 'vox_apikey_' + type + '_' + slug;
      var v = localStorage.getItem(k);
      if (v) { _apiKeys[k] = v; }
    });
  });
}

/** Sauvegarder une clé API dans localStorage */
function providerSaveKey(type, slug, value) {
  var k = 'vox_apikey_' + type + '_' + slug;
  if (value && value.trim()) {
    _apiKeys[k] = value.trim();
    try { localStorage.setItem(k, value.trim()); } catch(_e) {}
  } else {
    delete _apiKeys[k];
    try { localStorage.removeItem(k); } catch(_e) {}
  }
}

/** Lire une clé API pour un provider */
function providerGetKey(type, slug) {
  return _apiKeys['vox_apikey_' + type + '_' + slug] || '';
}

// ── Rendu des selects ─────────────────────────────────────────────────────────

function providersRenderAll() {
  _renderProviderSelect('llm', 'cfg-provider-llm', 'cfg-llm-url', 'cfg-llm-model', 'cfg-llm-key-wrap', 'cfg-llm-key');
  _renderProviderSelect('tts', 'cfg-provider-tts', 'cfg-tts-url', 'cfg-tts-model', 'cfg-tts-key-wrap', 'cfg-tts-key');
  _renderProviderSelect('stt', 'cfg-provider-stt', 'cfg-stt-url', 'cfg-stt-model', 'cfg-stt-key-wrap', 'cfg-stt-bearer');


  // Charge la liste de modèles OpenRouter si c'est le provider LLM actif
  loadOpenRouterModels();

  
}

/**
 * Construire le select d'un provider + brancher les événements
 * @param {string} type         llm | tts | stt
 * @param {string} selectId     id du <select> provider
 * @param {string} urlId        id du <input> URL
 * @param {string} modelId      id du <input> modèle
 * @param {string} keyWrapId    id du wrapper clé API (div)
 * @param {string} keyId        id du <input> clé API
 */
function _renderProviderSelect(type, selectId, urlId, modelId, keyWrapId, keyId) {
  var p = _providers && _providers[type];
  if (!p || !p.list) { return; }

  var sel = g(selectId);
  if (!sel) { return; }

  // Vider et repeupler
  sel.innerHTML = '';
  Object.keys(p.list).forEach(function(slug) {
    var item   = p.list[slug];
    var opt    = document.createElement('option');
    opt.value  = slug;
    opt.textContent = item.label;
    if (slug === p.active) { opt.selected = true; }
    sel.appendChild(opt);
  });

  // Appliquer l'état initial
  _applyProviderSelection(type, sel.value, urlId, modelId, keyWrapId, keyId);

  // Événement changement
  sel.addEventListener('change', function() {
    _applyProviderSelection(type, sel.value, urlId, modelId, keyWrapId, keyId);
    if (type === 'llm') { //loadOpenRouterModels(); loadProviderModels(0)
      }
    saveConfig();
  });
}

/**
 * Pré-remplir URL + modèle + clé quand un provider est sélectionné
 */
function _applyProviderSelection(type, slug, urlId, modelId, keyWrapId, keyId) {
  var p    = _providers && _providers[type];
  var item = p && p.list && p.list[slug];
  if (!item) { return; }

  // URL — pré-remplir seulement si le champ est vide ou correspond à un provider connu
  var urlEl = g(urlId);
  if (urlEl) {
    // Vérifier si l'URL actuelle correspond à un provider de la liste
    var isKnownUrl = Object.values(p.list).some(function(i) {
      return i.url && urlEl.value.trim() === i.url;
    });
    if (!urlEl.value.trim() || isKnownUrl) {
      urlEl.value = item.url || '';
    }
  }

  // Modèle — pré-remplir seulement si vide
  var modelEl = g(modelId);
  if (modelEl && !modelEl.value.trim()) {
    modelEl.value = _getDefaultModel(type, slug) || '';
  }

  // Clé API — afficher/masquer le champ selon requiresKey
  var keyWrap = g(keyWrapId);
  var keyEl   = g(keyId);
  if (keyWrap) {
    keyWrap.style.display = (item.requiresKey === true) ? 'block' : 'none';
  }
  if (keyEl) {
    keyEl.value = providerGetKey(type, slug);
    // Sauvegarder la clé à la saisie
    keyEl.oninput = function() {
      providerSaveKey(type, slug, keyEl.value);
    };
  }

  // Mettre à jour config live
  _syncProviderToConfig(type, slug, urlEl ? urlEl.value : '', modelEl ? modelEl.value : '');
}

/**
 * Synchroniser le provider sélectionné dans l'objet config global
 */
function _syncProviderToConfig(type, slug, url, model) {
  if (!window.config) { return; }
  if (type === 'llm') {
    config.llmProvider = slug;
    if (url)   { config.llmUrl   = url; }
    if (model) { config.llmModel = model; }
    config.llmKey = providerGetKey('llm', slug);
  } else if (type === 'tts') {
    config.ttsProvider = slug;
    if (url)   { config.ttsUrl   = url; }
    if (model) { config.ttsModel = model; }
    config.ttsKey = providerGetKey('tts', slug);
  } else if (type === 'stt') {
    config.sttProvider = slug;
    if (url)   { config.sttUrl   = url; }
    if (model) { config.sttModel = model; }
    config.sttKey = providerGetKey('stt', slug);
  }
}

// ── Modèles par défaut par provider ──────────────────────────────────────────

var _defaultModels = {
  llm: {
    ollama:     'AssistantQ14bI:latest',                           // modèle libre — saisi par l'utilisateur
    openai:     'AssistantQ14bI:latest',
    anthropic:  'claude-sonnet-4-5',
    mistral:    'mistral-large-latest',
    groq:       'llama-3.3-70b-versatile',
    gemini:     'gemini-2.0-flash',
    openrouter: '',
  },
  tts: {
    kokoro:     'tts',
    openai:     'tts-1',
    elevenlabs: 'eleven_multilingual_v2',
    azure:      '',
  },
  stt: {
    whisper_local: 'Systran/faster-whisper-small',
    openai:        'whisper-1',
    groq:          'whisper-large-v3',
    azure:         '',
  },
};

function _getDefaultModel(type, slug) {
  return (_defaultModels[type] && _defaultModels[type][slug]) || '';
}

// ── OpenRouter — liste dynamique des modèles ─────────────────────────────────

/**
 * Si OpenRouter est le provider LLM actuellement sélectionné, charge la liste
 * réelle des modèles via /backend/api.php (action=provider_models_list) et
 * remplit <select id="cfg-llmList-model">. Réessaie en boucle courte si le
 * select n'existe pas encore dans le DOM (panneau pas encore affiché).
 * @param {number} [attempt] compteur interne de tentatives
 */
function loadOpenRouterModels(attempt) {
  return;
  attempt = attempt || 0;

  var providerSel = g('cfg-provider-llm');
  var slug = providerSel ? providerSel.value : '';

  var listSel = g('cfg-llmList-model');

  if (!listSel) {
    if (attempt < 20) {
      setTimeout(function() { loadOpenRouterModels(attempt + 1); }, 300);
    }
    return;
  }

  if (slug !== 'openrouter') {
    listSel.style.display = 'none';
    return;
  }

  fetch('/backend/api.php', {
    method: 'POST',
    mode: 'same-origin',
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'provider_models_list', slug: 'openrouter' })
  })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      var models = data.models || [];
      listSel.innerHTML = '';
      models.forEach(function(m) {
        var opt = document.createElement('option');
        opt.value = m.id;
        opt.textContent = m.name || m.id;
        listSel.appendChild(opt);
      });
      listSel.style.display = models.length ? 'flex' : 'none';
    })
    .catch(function(e) {
      console.warn('Impossible de charger les modèles OpenRouter', e);
      listSel.style.display = 'none';
    });
}

// ── Lecture provider actif depuis config ──────────────────────────────────────

function loadProviderModels(attempt) {

  attempt = attempt || 0;

  var providerSel = g('cfg-provider-llm');
  var slug = providerSel ? providerSel.value : 'Ollama (local)';
  var listSel = g('cfg-llmList-model');
  var urlInput = g('cfg-llm-url');

  if (!listSel || !urlInput) {
    if (attempt < 20) {
      setTimeout(() => loadProviderModels(attempt + 1), 300);
    }
    return;
  }

  if (!slug) {
    listSel.style.display = 'none';
    urlInput.value = '';
    return;
  }

  fetch('/settings.php')
  .then(r => r.json())
  .then(settings => {
    if (!settings?.providers?.llm?.list?.[slug]) {
      console.warn('Provider non trouvé dans les settings');
      return;
    }

    const defaultUrl = settings.providers.llm.list[slug]?.url || '';
    const urlInput = document.getElementById('cfg-llm-url');

    if (urlInput) {
      urlInput.value = defaultUrl;
    }
  })
  .catch(error => {
    console.error('Erreur lors du chargement des settings:', error);
  });

  

  
  fetch('/backend/api.php', {
    method: 'POST',
    mode: 'same-origin',
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'provider_models_list', slug })
  })
    .then(r => r.json())
    .then(data => {
      var models = data.models || [];
      listSel.innerHTML = '';
      models.forEach(function(model) {
        var opt = document.createElement('option');
        opt.value = model.id;
        opt.textContent = model.label || model.id;
        listSel.appendChild(opt);
      });


      // // Gestion de l'URL
      // var url = slug === 'openrouter'
      //   ? 'https://api.openrouter.ai'
      //   : (data.url || '');
      // urlInput.value = url;
    })
    .catch(e => {
      console.warn('Erreur de chargement des modèles:', e);
      listSel.style.display = 'none';
      urlInput.value = '';
    });
}

// Intégration avec providersRenderAll
function providersRenderAll() {
  _renderProviderSelect('llm', 'cfg-provider-llm', 'cfg-llm-url', 'cfg-llm-model', 'cfg-llm-key-wrap', 'cfg-llm-key');

  // Remplacer l'appel à loadOpenRouterModels() par loadProviderModels()
  var sel = g('cfg-provider-llm');
  if (sel) {
    sel.addEventListener('change', function() {
      loadProviderModels(0);
      saveConfig();
    });
  }
}


/**
 * Retourner la clé API active pour le provider courant
 * Appelé par les proxies JS avant chaque requête
 */
function providerGetActiveKey(type) {
  var slug = (type === 'llm') ? config.llmProvider
           : (type === 'tts') ? config.ttsProvider
           : config.sttProvider;
  return slug ? providerGetKey(type, slug) : '';
}



// ── Restauration depuis localStorage ─────────────────────────────────────────

/**
 * Après loadConfig(), restaurer le provider sélectionné depuis localStorage
 * Appelé dans main.js après que les selects sont rendus
 */
function providersRestoreFromConfig() {
  if (!_providers) { return; }

  ['llm', 'tts', 'stt'].forEach(function(type) {
    var slug = (type === 'llm') ? config.llmProvider
             : (type === 'tts') ? config.ttsProvider
             : config.sttProvider;

    if (!slug) { return; }

    var selectId  = 'cfg-provider-' + type;
    var urlId     = type === 'llm' ? 'cfg-llm-url'   : type === 'tts' ? 'cfg-tts-url'   : 'cfg-stt-url';
    var modelId   = type === 'llm' ? 'cfg-llm-model' : type === 'tts' ? 'cfg-tts-model' : 'cfg-stt-model';
    var keyWrapId = 'cfg-' + type + '-key-wrap';
    var keyId     = type === 'llm' ? 'cfg-llm-key' : type === 'tts' ? 'cfg-tts-key' : 'cfg-stt-bearer';

    var sel = g(selectId);
    if (sel && slug) {
      sel.value = slug;
      _applyProviderSelection(type, slug, urlId, modelId, keyWrapId, keyId);
      if (type === 'llm') { loadOpenRouterModels(); loadProviderModels(0)}
    }
  });
}