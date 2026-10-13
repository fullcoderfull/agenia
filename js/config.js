// =============================================================================
// config.js — Configuration UI uniquement
// Les valeurs sensibles (URLs, clés API, modèles, prompts système) sont
// gérées exclusivement côté PHP et ne transitent jamais vers le navigateur.
//
// Priorité : api.php (config_load) < localStorage (préférences UI) < formulaire Admin
// =============================================================================
'use strict';

// Uniquement les clés UI — aucune info infra, aucune clé API
var config = {};
var VOXMIND_VERSION = 'V8.0';   // Version affichée, non sensible

// Clés autorisées en front — toute autre clé est ignorée au chargement et à la sauvegarde
var CONFIG_UI_KEYS = [
  'streaming', 'noThink', 'audioEnabled', 'naturalConversation',
  'firstChunkWords', 'sentenceMarkers', 'pauseMarkers', 'pauseMinWords', 'ttsTimeoutMs',
  'ttsHighlightEnabled', 'ttsHighlightColor', 'ttsHighlightColorLight',
  'ttsMinWordsStrong', 'ttsInstruct', 'ttsInstructPrompt', 'replayAudio',
  'visionMode', 'visionThreshold', 'visionPrompt', 'visionSpeak',
  'uploadEnabled',
  'ttsVoiceId',           // fr | en — langue de la voix (Célia/Alex)
  // Labels d'affichage des providers (existant — conservé)
  'llmProviderLabel', 'ttsProviderLabel', 'sttProviderLabel',
  // Slugs techniques des providers (ajout — lus par providers.js)
  'llmProvider', 'ttsProvider', 'sttProvider'
];

// =============================================================================
// CHARGEMENT — appelle api.php qui filtre les clés sensibles
// =============================================================================

/**
 * Charge la configuration UI depuis api.php (action=config_load).
 * Le back retourne uniquement les clés UI_SAFE issues de settings.json.
 * Ensuite, localStorage écrase avec les préférences utilisateur.
 * @returns {Promise<void>}
 */
function loadConfig() {
  return apiCall('config_load', {})
    .then(function(data) {
      // data.ui contient uniquement les clés UI_SAFE
      var ui = data.ui || {};
      config = {};
      CONFIG_UI_KEYS.forEach(function(k) {
        if (ui[k] !== undefined) { config[k] = ui[k]; }
      });

      // Appliquer les valeurs par défaut UI si absentes
      _applyUiDefaults();

      // Charger les labels providers pour les selects (remplit les <option>)
      if (data.providers && typeof providersLoad === 'function') {
        providersLoad(data.providers);
      }

      // Écraser avec les préférences UI sauvegardées par l'utilisateur
      _mergeLocalStorage();

      if (typeof providersRestoreFromConfig === 'function') {
        providersRestoreFromConfig();
      }
    })
    .catch(function() {
      // api.php inaccessible → valeurs UI minimales de secours
      // Aucune info sensible ici
      config = {
        streaming: true, noThink: true, audioEnabled: true,
        naturalConversation: false,
        firstChunkWords: 20,
        sentenceMarkers: ['.', '!', '?', '…'],
        pauseMarkers: [',', ';', ':'],
        pauseMinWords: 20, ttsTimeoutMs: 8000,
        ttsHighlightEnabled: true,
        ttsHighlightColor: 'rgba(124,58,237,0.35)',
        ttsHighlightColorLight: 'rgba(109,40,217,0.25)',
        ttsMinWordsStrong: 20,
        ttsInstruct: false, ttsInstructPrompt: '', replayAudio: false,
        visionMode: 'off', visionThreshold: 15,
        visionPrompt: 'Décris brièvement ce que tu vois dans cette image en 1 ou 2 phrases.',
        visionSpeak: true,
        uploadEnabled: true,
        llmProviderLabel: 'anthropic',
        ttsProviderLabel: '',
        sttProviderLabel: '',
        // Ajout — cohérence avec providers.js
        llmProvider: 'anthropic',
        ttsProvider: '',
        sttProvider: ''
      };
      _mergeLocalStorage();
    });
}

// =============================================================================
// SAUVEGARDE — uniquement les clés UI, jamais les clés sensibles
// =============================================================================

/**
 * Lit le formulaire Admin, met à jour config (clés UI only),
 * persiste en localStorage et notifie le back des préférences admin.
 */
function saveConfig() {
  // ── Providers — labels d'affichage ET slugs techniques ──────────────────
  var selLlm = g('cfg-provider-llm');
  var selTts = g('cfg-provider-tts');
  var selStt = g('cfg-provider-stt');
  if (selLlm) {
    config.llmProviderLabel = selLlm.value;   // existant, conservé
    config.llmProvider      = selLlm.value;   // ajout — lu par providers.js
  }
  if (selTts) {
    config.ttsProviderLabel = selTts.value;
    config.ttsProvider      = selTts.value;
  }
  if (selStt) {
    config.sttProviderLabel = selStt.value;
    config.sttProvider      = selStt.value;
  }

  // ── Comportement LLM ──────────────────────────────────────────────────────
  config.noThink   = g('cfg-no-think')  ? g('cfg-no-think').checked  : true;
  config.streaming = g('cfg-streaming') ? g('cfg-streaming').checked : (config.streaming !== false);

  // ── TTS / Audio ───────────────────────────────────────────────────────────
  config.ttsMinWordsStrong   = g('cfg-tts-min-words-strong')
    ? (parseInt(g('cfg-tts-min-words-strong').value, 10) || 20) : 20;
  config.naturalConversation = g('cfg-natural-conv')
    ? g('cfg-natural-conv').checked : false;
  config.ttsInstruct         = g('cfg-tts-instruct')
    ? g('cfg-tts-instruct').checked : false;
  config.ttsInstructPrompt   = g('cfg-tts-instruct-prompt')
    ? g('cfg-tts-instruct-prompt').value.trim() : '';
  config.replayAudio         = g('cfg-replay-audio')
    ? g('cfg-replay-audio').checked : false;
  config.audioEnabled        = g('cfg-audio-enabled')
    ? g('cfg-audio-enabled').checked : (config.audioEnabled !== false);

  // ── Vision ────────────────────────────────────────────────────────────────
  config.visionMode      = g('cfg-vision-mode')
    ? g('cfg-vision-mode').value : (config.visionMode || 'off');
  config.visionThreshold = g('cfg-vision-threshold')
    ? (parseInt(g('cfg-vision-threshold').value, 10) || 15) : (config.visionThreshold || 15);
  config.visionPrompt    = g('cfg-vision-prompt')
    ? g('cfg-vision-prompt').value.trim() : (config.visionPrompt || '');
  config.visionSpeak     = g('cfg-vision-speak')
    ? g('cfg-vision-speak').checked : !!config.visionSpeak;

  // ── Sauvegarder en localStorage — uniquement les clés UI autorisées ───────
  _persistLocalStorage();

  // ── Notifier le back des valeurs sensibles saisies dans le panel Admin ────
  // (llmUrl, llmKey, etc. lus uniquement ici, jamais stockés dans config{})
  _saveAdminSensitiveFields();

  showTestResult('✓ Configuration sauvegardée', 'ok');
}

/**
 * Lit les champs sensibles du formulaire Admin et les envoie UNIQUEMENT
 * vers api.php — ils ne passent jamais dans l'objet config{} front.
 * @private
 */
function _saveAdminSensitiveFields() {
  var sensitive = {};
  var fields = {
    llmUrl:            'cfg-llm-url',
    llmModel:          'cfg-llm-model',
    visionModel:       'cfg-vision-model',
    systemPrompt:      'cfg-system-prompt',
    llmKey:            'cfg-llm-key',
    ttsUrl:            'cfg-tts-url',
    ttsModel:          'cfg-tts-model',
    ttsVoice:          'cfg-tts-voice',
    ttsFormat:         'cfg-tts-format',
    sttUrl:            'cfg-stt-url',
    sttModel:          'cfg-stt-model',
    sttBearer:         'cfg-stt-bearer',
  };

  // Providers — valeur technique (pas juste le label)
  var selLlm = g('cfg-provider-llm');
  var selTts = g('cfg-provider-tts');
  var selStt = g('cfg-provider-stt');
  if (selLlm) { sensitive.llmProvider = selLlm.value; }
  if (selTts) { sensitive.ttsProvider = selTts.value; }
  if (selStt) { sensitive.sttProvider = selStt.value; }

  Object.keys(fields).forEach(function(key) {
    var el = g(fields[key]);
    if (el) { sensitive[key] = el.value.trim(); }
  });

  // Envoi silencieux — pas d'attente, pas de retour UI ici
  apiCall('config_save_sensitive', { sensitive: sensitive }).catch(function(e) {
    console.warn('config_save_sensitive:', e.message || e);
  });
}

// =============================================================================
// APPLIQUER config → formulaire Admin
// =============================================================================

function applyConfigToForm() {
  // ── Providers — la sélection réelle est gérée par providersRestoreFromConfig()
  // (appelé en fin de fonction), une fois les <option> déjà peuplées.

  // ── LLM — champs sensibles : le back les renvoie masqués pour affichage ──
  // Les valeurs réelles ne sont PAS dans config{}, on demande au back
  _loadSensitiveFieldsForAdmin();

  // ── Comportement ─────────────────────────────────────────────────────────
  if (g('cfg-no-think'))  { g('cfg-no-think').checked  = config.noThink !== false; }
  if (g('cfg-streaming')) { g('cfg-streaming').checked = config.streaming !== false; }

  // ── TTS / Audio ───────────────────────────────────────────────────────────
  if (g('cfg-tts-min-words-strong')) { g('cfg-tts-min-words-strong').value = config.ttsMinWordsStrong || 20; }
  if (g('cfg-natural-conv'))         { g('cfg-natural-conv').checked        = !!config.naturalConversation; }
  if (g('cfg-tts-instruct'))         { g('cfg-tts-instruct').checked        = !!config.ttsInstruct; }
  if (g('cfg-tts-instruct-prompt'))  { g('cfg-tts-instruct-prompt').value   = config.ttsInstructPrompt || ''; }
  if (g('cfg-replay-audio'))         { g('cfg-replay-audio').checked        = !!config.replayAudio; }
  if (g('cfg-audio-enabled'))        { g('cfg-audio-enabled').checked       = config.audioEnabled !== false; }

  // ── Vision ────────────────────────────────────────────────────────────────
  if (g('cfg-vision-mode'))      { g('cfg-vision-mode').value         = config.visionMode || 'off'; }
  if (g('cfg-vision-threshold')) { g('cfg-vision-threshold').value    = config.visionThreshold || 15; }
  if (g('cfg-threshold-val'))    { g('cfg-threshold-val').textContent = config.visionThreshold || 15; }
  if (g('cfg-vision-prompt'))    { g('cfg-vision-prompt').value       = config.visionPrompt || ''; }
  if (g('cfg-vision-speak'))     { g('cfg-vision-speak').checked      = !!config.visionSpeak; }

  if (typeof toggleInstructField          === 'function') { toggleInstructField(); }
  if (typeof providersRestoreFromConfig   === 'function') { providersRestoreFromConfig(); }
}

/**
 * Demande au back les valeurs sensibles pour les afficher dans le panel Admin.
 * Le back les envoie masquées (ex: llmKey → "sk-••••••1234") pour confirmation visuelle,
 * jamais en clair dans le DOM principal.
 * @private
 */
function _loadSensitiveFieldsForAdmin() {
  apiCall('config_load_sensitive', {})
    .then(function(data) {
      var s = data.sensitive || {};
      // Afficher les valeurs (masquées pour les secrets) dans les inputs Admin
      if (g('cfg-llm-url'))        { g('cfg-llm-url').value         = s.llmUrl        || ''; }
      if (g('cfg-llm-model'))      { g('cfg-llm-model').value       = s.llmModel      || ''; }
      if (g('cfg-vision-model'))   { g('cfg-vision-model').value    = s.visionModel   || ''; }
      if (g('cfg-system-prompt'))  { g('cfg-system-prompt').value   = s.systemPrompt  || ''; }
      if (g('cfg-llm-key'))        { g('cfg-llm-key').value         = s.llmKeyMasked  || ''; }   // masqué
      if (g('cfg-tts-url'))        { g('cfg-tts-url').value         = s.ttsUrl        || ''; }
      if (g('cfg-tts-model'))      { g('cfg-tts-model').value       = s.ttsModel      || ''; }
      if (g('cfg-tts-voice'))      { g('cfg-tts-voice').value       = s.ttsVoice      || ''; }
      if (g('cfg-tts-format'))     { g('cfg-tts-format').value      = s.ttsFormat     || 'mp3'; }
      if (g('cfg-stt-url'))        { g('cfg-stt-url').value         = s.sttUrl        || ''; }
      if (g('cfg-stt-model'))      { g('cfg-stt-model').value       = s.sttModel      || ''; }
      if (g('cfg-stt-bearer'))     { g('cfg-stt-bearer').value      = s.sttBearerMasked || ''; } // masqué
    })
    .catch(function() { /* silencieux — champs restent vides */ });
}

// =============================================================================
// RESET
// =============================================================================

/** Réinitialise localStorage et recharge depuis le back */
function resetConfig() {
  try { localStorage.removeItem('voxmind_config'); } catch (_e) {}
  return loadConfig().then(function() {
    applyConfigToForm();
    showTestResult('↺ Configuration réinitialisée', 'ok');
  });
}

// =============================================================================
// HELPERS PRIVÉS
// =============================================================================

function _applyUiDefaults() {
  var D = {
    streaming: true, noThink: true, audioEnabled: true, naturalConversation: false,
    firstChunkWords: 20,
    sentenceMarkers: ['.', '!', '?', '…'], pauseMarkers: [',', ';', ':'],
    pauseMinWords: 20, ttsTimeoutMs: 8000,
    ttsHighlightEnabled: true,
    ttsHighlightColor: 'rgba(124,58,237,0.35)',
    ttsHighlightColorLight: 'rgba(109,40,217,0.25)',
    ttsMinWordsStrong: 20, ttsInstruct: false, ttsInstructPrompt: '', replayAudio: false,
    visionMode: 'off', visionThreshold: 15,
    visionPrompt: 'Décris brièvement ce que tu vois dans cette image en 1-2 phrases.',
    visionSpeak: true, uploadEnabled: true,
    ttsVoiceId: 'fr',   // Célia par défaut
  };
  Object.keys(D).forEach(function(k) {
    if (config[k] === undefined) { config[k] = D[k]; }
  });
}

/** Fusionne localStorage dans config, en filtrant sur CONFIG_UI_KEYS */
function _mergeLocalStorage() {
  try {
    var saved = localStorage.getItem('voxmind_config');
    if (!saved) { return; }
    var parsed = JSON.parse(saved);
    CONFIG_UI_KEYS.forEach(function(k) {
      if (parsed[k] !== undefined) { config[k] = parsed[k]; }
    });
  } catch (_e) {}
}

/** Persiste config en localStorage — uniquement CONFIG_UI_KEYS */
function _persistLocalStorage() {
  var safe = {};
  CONFIG_UI_KEYS.forEach(function(k) {
    if (config[k] !== undefined) { safe[k] = config[k]; }
  });
  try { localStorage.setItem('voxmind_config', JSON.stringify(safe)); } catch (_e) {}
}