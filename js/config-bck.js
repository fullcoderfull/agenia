// =============================================================================
// config.js — Chargement et persistance de la configuration
// Priorité : settings.json < localStorage < formulaire Admin
// =============================================================================
'use strict';

var config = {};
var Agenia_VERSION = 'V7.9';

/**
 * Charge settings.json puis écrase avec localStorage si présent.
 * Retourne une Promise résolue quand config est prête.
 */
function loadConfig() {
  return fetch('./settings.php?v=' + Date.now(), {
    method: 'GET', mode: 'same-origin', credentials: 'same-origin',
    cache: 'no-store'
  })
  .then(function(r) { return r.json(); })
  .then(function(defaults) {
    // Aplatir le JSON structuré en objet plat
    config = {
      llmUrl:        defaults.llm.url,
      llmModel:      defaults.llm.model,
      visionModel:      defaults.llm.visionModel,
      systemPrompt:     defaults.llm.systemPrompt,
      llmKey:        defaults.llm.apiKey,
      //sttUrl:       (defaults.stt && defaults.stt.url) ||
      sttUrl:       defaults.stt.url,
      sttModel:       defaults.stt.model,
      noThink:          defaults.llm.noThink !== false,
      naturalConversation:    !!(defaults.audio && defaults.audio.naturalConversation),
      ttsHighlightEnabled:    (defaults.ttsHighlight && defaults.ttsHighlight.enabled !== false),
      ttsHighlightColor:      (defaults.ttsHighlight && defaults.ttsHighlight.color)      || 'rgba(124,58,237,0.35)',
      ttsHighlightColorLight: (defaults.ttsHighlight && defaults.ttsHighlight.colorLight) || 'rgba(109,40,217,0.25)',
      uploadEnabled:    (defaults.upload && defaults.upload.enabled !== false),
      uploadMaxSizeMb:  (defaults.upload && defaults.upload.maxSizeMb)   || 20,
      uploadMaxTokens:  (defaults.upload && defaults.upload.maxTokens)   || 10000,
      uploadMaxPages:   (defaults.upload && defaults.upload.maxPages)    || 2,
      uploadAcceptedTypes: (defaults.upload && defaults.upload.acceptedTypes) || ['image/*','application/pdf','text/plain'],

      ttsUrl:        defaults.tts.url,
      ttsVoice:      defaults.tts.voice,
      ttsFormat:     defaults.tts.format,

      audioEnabled:     defaults.audio.enabled,
      streaming:        defaults.audio.streaming,
      firstChunkWords:  defaults.audio.firstChunkWords,
      sentenceMarkers:  defaults.audio.sentenceMarkers,
      pauseMarkers:     defaults.audio.pauseMarkers,
      pauseMinWords:    defaults.audio.pauseMinWords,
      ttsTimeoutMs:  defaults.audio.ttsTimeoutMs,

      visionMode:       defaults.vision.mode,
      visionThreshold:  defaults.vision.threshold,
      visionPrompt:     defaults.vision.prompt,
      visionSpeak:      defaults.vision.speak,

      probeMaxAttempts: defaults.probe.maxAttempts,
      probeDelayMs:     defaults.probe.delayMs,

      mcpBaseUrl:     defaults.mcp.baseUrl,
    };

    // Écraser avec la config sauvegardée par l'utilisateur
    try {
      var saved = localStorage.getItem('Agenia_config');
      if (saved) {
        var parsed = JSON.parse(saved);
        Object.keys(parsed).forEach(function(k) {
          config[k] = parsed[k];
        });
      }
    } catch(_e) {}
  })
  .catch(function() {
    // settings.json inaccessible → valeurs minimales de secours
    config = {
      llmUrl: 'http://172.17.164.70:11434', llmModel: 'llama3',
      visionModel: 'llava', systemPrompt: 'Tu es un assistant francophone, toutes tes reponses commencent par tools@chat(conversation) ou tools@terminal(mode agent) + parametre (ls | grep | etc...)',
      llmKey: '', noThink: true, sttUrl: 'http://172.17.164.70:8990', ttsUrl: 'http://172.17.164.70:8880',
      ttsVoice: 'af_heart', ttsFormat: 'wav',
      audioEnabled: true, streaming: true,
      firstChunkWords: 20, sentenceMarkers: ['.','!','?','…'],
      pauseMarkers: [',',';',':'], pauseMinWords: 20, ttsTimeoutMs: 8000,
      visionMode: 'off', visionThreshold: 15,
      visionPrompt: 'Décris brièvement ce que tu vois dans cette image en 1-2 phrases.',
      visionSpeak: true, probeMaxAttempts: 10, probeDelayMs: 3000,
    };
  });
}

/** Lit le formulaire Admin et persiste en localStorage */
function saveConfig() {
  config.llmUrl       = g('cfg-llm-url').value.trim();
  config.llmModel     = g('cfg-llm-model').value.trim();
  config.visionModel     = g('cfg-vision-model').value.trim();
  config.systemPrompt    = g('cfg-system-prompt').value.trim();
  config.llmKey       = g('cfg-llm-key').value.trim();
  config.ttsUrl       = g('cfg-tts-url').value.trim();
  config.ttsVoice     = g('cfg-tts-voice').value.trim();
  config.ttsFormat    = g('cfg-tts-format').value;
  config.sttUrl      = g('cfg-stt-url') ? g('cfg-stt-url').value.trim() : config.sttUrl;
  config.audioEnabled    = g('cfg-audio-enabled').checked;
  config.noThink         = g('cfg-no-think')     ? g('cfg-no-think').checked     : true;
  config.ttsModel        = g('cfg-tts-model')        ? g('cfg-tts-model').value.trim()        : config.ttsModel;
  config.ttsMinWordsStrong = g('cfg-tts-min-words-strong') ? parseInt(g('cfg-tts-min-words-strong').value) || 20 : 20;
  config.sttUrl     = g('cfg-stt-url')     ? g('cfg-stt-url').value.trim()     : config.sttUrl || '';
  config.sttModel   = g('cfg-stt-model')   ? g('cfg-stt-model').value.trim()   : config.sttModel || '';
  config.sttBearer  = g('cfg-stt-bearer')  ? g('cfg-stt-bearer').value.trim()  : config.sttBearer || '';
  config.llmUrl     = g('cfg-llm-url')     ? g('cfg-llm-url').value.trim()     : config.llmUrl || '';
  config.llmModel   = g('cfg-llm-model')   ? g('cfg-llm-model').value.trim()   : config.llmModel || '';
  config.llmKey     = g('cfg-llm-key')     ? g('cfg-llm-key').value.trim()     : config.llmKey || '';
  config.naturalConversation = g('cfg-natural-conv') ? g('cfg-natural-conv').checked : false;
  config.ttsInstruct     = g('cfg-tts-instruct') ? g('cfg-tts-instruct').checked : false;
  config.ttsInstructPrompt = g('cfg-tts-instruct-prompt') ? g('cfg-tts-instruct-prompt').value.trim() : '';
  config.replayAudio     = g('cfg-replay-audio') ? g('cfg-replay-audio').checked  : false;
  config.streaming       = g('cfg-streaming').checked;
  config.visionMode      = g('cfg-vision-mode').value;
  config.visionThreshold = parseInt(g('cfg-vision-threshold').value, 10);
  config.visionPrompt    = g('cfg-vision-prompt').value.trim();
  config.visionSpeak     = g('cfg-vision-speak').checked;

  try { localStorage.setItem('Agenia_config', JSON.stringify(config)); } catch(_e) {}
  showTestResult('✓ Configuration sauvegardée', 'ok');
}

/** Applique config → formulaire Admin */
function applyConfigToForm() {
  g('cfg-llm-url').value        = config.llmUrl;
  g('cfg-llm-model').value      = config.llmModel;
  g('cfg-vision-model').value      = config.visionModel;
  g('cfg-system-prompt').value     = config.systemPrompt;
  g('cfg-llm-key').value        = config.llmKey;
  g('cfg-tts-url').value        = config.ttsUrl;
  g('cfg-tts-voice').value      = config.ttsVoice;
  g('cfg-tts-format').value     = config.ttsFormat;
  g('cfg-audio-enabled').checked   = config.audioEnabled;
  if (g('cfg-no-think'))           { g('cfg-no-think').checked            = config.noThink !== false; }
  if (g('cfg-tts-model'))            { g('cfg-tts-model').value             = config.ttsModel || 'tts'; }
  if (g('cfg-tts-min-words-strong')) { g('cfg-tts-min-words-strong').value  = config.ttsMinWordsStrong || 20; }
  if (g('cfg-stt-url'))      { g('cfg-stt-url').value     = config.sttUrl || ''; }
  if (g('cfg-stt-model'))    { g('cfg-stt-model').value   = config.sttModel || ''; }
  if (g('cfg-stt-bearer'))   { g('cfg-stt-bearer').value  = config.sttBearer || ''; }
  if (g('cfg-llm-url'))      { g('cfg-llm-url').value     = config.llmUrl || ''; }
  if (g('cfg-llm-model'))    { g('cfg-llm-model').value   = config.llmModel || ''; }
  if (g('cfg-llm-key'))      { g('cfg-llm-key').value     = config.llmKey || ''; }
  if (g('cfg-natural-conv')) { g('cfg-natural-conv').checked = !!config.naturalConversation; }
  if (g('cfg-tts-instruct'))         { g('cfg-tts-instruct').checked         = !!config.ttsInstruct; }
  if (g('cfg-tts-instruct-prompt')){ g('cfg-tts-instruct-prompt').value   = config.ttsInstructPrompt || ''; }
  if (g('cfg-replay-audio'))       { g('cfg-replay-audio').checked        = !!config.replayAudio; }
  if (g('cfg-stt-url')) { g('cfg-stt-url').value = config.sttUrl || ''; }
  g('cfg-streaming').checked       = config.streaming;
  g('cfg-vision-mode').value       = config.visionMode;
  g('cfg-vision-threshold').value  = config.visionThreshold;
  g('cfg-threshold-val').textContent = config.visionThreshold;
  g('cfg-vision-prompt').value     = config.visionPrompt;
  g('cfg-vision-speak').checked    = config.visionSpeak;
}

/** Réinitialise localStorage et recharge depuis settings.json */
function resetConfig() {
  try { localStorage.removeItem('Agenia_config'); } catch(_e) {}
  return loadConfig().then(function() {
    applyConfigToForm();
    showTestResult('↺ Configuration réinitialisée', 'ok');
  });
}