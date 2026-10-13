// =============================================================================
// main.js — Point d'entrée, sendMessage, stop, UI globale
// Version : V8.0
// Modifié : 2025-06-11
// =============================================================================
'use strict';

var MAIN_VERSION = 'V8.0';

var isLoading   = false;
var adminOpen   = false;
var micActive   = false;
var llmReady    = false;

// =============================================================================
// DEBUG
// =============================================================================

function voxDebug(context, msg, data) {
  if (!window.VOX_DEBUG) { return; }
  console.debug('[' + context + ']', msg, data !== undefined ? data : '');
}

// =============================================================================
// HELPER GLOBAL
// =============================================================================

function g(id) { return document.getElementById(id); }

// =============================================================================
// POLYFILL AbortSignal.timeout
// =============================================================================
if (!AbortSignal.timeout) {
  AbortSignal.timeout = function(ms) {
    var ctrl = new AbortController();
    setTimeout(function() {
      ctrl.abort(new DOMException('TimeoutError', 'TimeoutError'));
    }, ms);
    return ctrl.signal;
  };
}

// =============================================================================
// ANTI-SPAM CLICK — debounce global
// =============================================================================

var _actionLock = {};

function debounceAction(key, fn, delayMs) {
  delayMs = delayMs || 500;
  if (_actionLock[key]) {
    voxDebug('DEBOUNCE', 'blocked', key);
    return;
  }
  _actionLock[key] = true;
  voxDebug('DEBOUNCE', 'exec', key);
  fn();
  setTimeout(function() { delete _actionLock[key]; }, delayMs);
}

// =============================================================================
// INIT
// =============================================================================

function init() {
  voxDebug('MAIN', 'init', { version: MAIN_VERSION });
  if (typeof initI18n === 'function') { initI18n(); }
  loadConfig().then(function() {
    applyConfigToForm();
    checkDeviceStatus();
    applyVisionMode();
    startupProbe();
    if (typeof uiInitScroll === 'function') { uiInitScroll(); }
    if (typeof initUpload  === 'function') { initUpload(); }
    voxDebug('MAIN', 'init done');
  });
}

// =============================================================================
// GUEST — vérification quota messages
// =============================================================================

function guestCheckQuota() {
  if (!voxIsGuest) { return true; }

  if (voxGuestExpires && Math.floor(Date.now() / 1000) >= voxGuestExpires) {
    voxDebug('GUEST', 'session expired');
    var expired = g('guest-expired');
    if (expired) { expired.classList.add('show'); }
    return false;
  }

  if (voxGuestMessages >= voxGuestMaxMessages) {
    voxDebug('GUEST', 'quota reached', { used: voxGuestMessages, max: voxGuestMaxMessages });
    appendMsg('assistant', voxLang === 'fr'
      ? '⚠ Vous avez atteint la limite de **' + voxGuestMaxMessages + ' messages**. [Créez un compte](/login.php) pour continuer sans limite.'
      : '⚠ You have reached the **' + voxGuestMaxMessages + ' message** limit. [Sign up](/login.php) to continue without limits.');
    return false;
  }

  return true;
}

function guestIncrementMessage() {
  if (!voxIsGuest) { return; }
  voxGuestMessages++;
  var el = g('guest-msg-count');
  if (el) { el.textContent = voxGuestMessages; }
  voxDebug('GUEST', 'message count', { used: voxGuestMessages, max: voxGuestMaxMessages });
}

// =============================================================================
// SEND MESSAGE
// =============================================================================

function sendMessage() {
  debounceAction('send', _sendMessage, 800);
}

async function _sendMessage() {
  if (isLoading) { return; }
  if (!guestCheckQuota()) { return; }

  var input = g('user-input');
  var text  = input.value.trim();
  if (!text) { return; }

  voxDebug('SEND', 'start', { len: text.length });

  pushInputHistory(text);
  input.value = '';
  autoResize(input);

  guestIncrementMessage();

  isLoading = true;
  updateStopBtn();
  setDot('llm', 'pulse');
  ttsUnlock();

  var imageB64 = null;
  var thumbUrl = null;
  if (config.visionMode === 'auto' && visionStream && currentDelta >= config.visionThreshold) {
    imageB64 = captureFrame();
    if (imageB64) {
      thumbUrl = 'data:image/jpeg;base64,' + imageB64;
      flashPreview();
    }
  }

  var uploadPayload = getUploadPayload ? getUploadPayload() : null;
  var userText = text;
  if (uploadPayload) {
    if (uploadPayload.type === 'image') {
      imageB64 = uploadPayload.content;
      thumbUrl = thumbUrl || ('data:image/jpeg;base64,' + imageB64.slice(0, 100) + '...');
    } else {
      userText = text + '\n\n[Fichier : ' + uploadPayload.name + ']\n' + uploadPayload.content;
    }
    clearUpload();
  }

  appendMsg('user', text, thumbUrl);
  chatHistory.push({ role: 'user', content: userText });

  try {
    var reply = await callLLM(imageB64);
    if (reply) {
      chatHistory.push({ role: 'assistant', content: reply });
      voxDebug('SEND', 'reply received', { len: reply.length });
    }
  } catch(e) {
    if (e.name !== 'AbortError') {
      voxDebug('SEND', 'error', { msg: e.message });
      appendMsg('assistant', '⚠ Erreur : ' + e.message);
    }
  }

  isLoading = false;
  updateStopBtn();
  setDot('llm', llmReady ? 'ok' : 'error');

  if (config.naturalConversation && !micActive) {
    setTimeout(function() { startMic(); }, 300);
  }
}

async function agentSendAutoMessage(text, comment) {
  if (!guestCheckQuota()) { return; }

  var input = g('user-input');
  if (!text) { return; }

  input.value = '';
  autoResize(input);

  guestIncrementMessage();

  isLoading = true;
  updateStopBtn();
  setDot('llm', 'pulse');
  ttsUnlock();

  voxDebug('AGENT', 'send', { len: text.length });

  var imageB64 = null;
  var thumbUrl = null;
  if (config.visionMode === 'auto' && visionStream && currentDelta >= config.visionThreshold) {
    imageB64 = captureFrame();
    if (imageB64) {
      thumbUrl = 'data:image/jpeg;base64,' + imageB64;
      flashPreview();
    }
  }

  var uploadPayload = getUploadPayload ? getUploadPayload() : null;
  var userText = text;
  if (uploadPayload) {
    if (uploadPayload.type === 'image') {
      imageB64 = uploadPayload.content;
      thumbUrl = thumbUrl || ('data:image/jpeg;base64,' + imageB64.slice(0, 100) + '...');
    } else {
      userText = text + '\n\n[Fichier : ' + uploadPayload.name + ']\n' + uploadPayload.content;
    }
    clearUpload();
  }

  appendMsg('user', text, thumbUrl);
  chatHistory.push({ role: 'user', content: userText });

  try {
    var reply = await agentCallLLM(imageB64, text, comment);
    if (reply) { chatHistory.push({ role: 'agent', content: reply }); }
  } catch(e) {
    if (e.name !== 'AbortError') {
      voxDebug('AGENT', 'error', { msg: e.message });
      appendMsg('assistant', '⚠ Erreur : ' + e.message);
    }
  }

  isLoading = false;
  updateStopBtn();
  setDot('llm', llmReady ? 'ok' : 'error');

  if (config.naturalConversation && !micActive) {
    setTimeout(function() { startMic(); }, 300);
  }
}

// =============================================================================
// STOP
// =============================================================================

function stopAll() {
  voxDebug('MAIN', 'stopAll');
  llmStop();
  ttsStop();
  hideTyping();
  var bt = document.querySelector('#messages .message:last-child .bt');
  if (bt && bt.textContent.trim()) {
    chatHistory.push({ role: 'assistant', content: bt.textContent.trim() });
  }
  isLoading = false;
  updateStopBtn();
  setDot('llm', llmReady ? 'ok' : 'error');
}

function updateStopBtn() {
  var btn = g('send-btn');
  if (!btn) { return; }
  if (isLoading) {
    btn.title     = 'Arrêter';
    btn.className = 'icon-btn stop';
    btn.onclick   = stopAll;
    btn.innerHTML = '<svg viewBox="0 0 24 24"><rect x="4" y="4" width="16" height="16" rx="2"/></svg>';
  } else {
    btn.title     = 'Envoyer';
    btn.className = 'icon-btn send';
    btn.onclick   = sendMessage;
    btn.innerHTML = '<svg viewBox="0 0 24 24"><path d="M22 2L11 13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>';
  }
}

// =============================================================================
// ADMIN PANEL
// =============================================================================

function toggleUserMenu() {
  var menu = g('user-menu');
  if (!menu) { return; }
  menu.classList.toggle('open');
  if (menu.classList.contains('open')) {
    setTimeout(function() {
      document.addEventListener('click', function closeMenu(e) {
        var wrap = g('user-menu-wrap');
        if (wrap && !wrap.contains(e.target)) {
          menu.classList.remove('open');
          document.removeEventListener('click', closeMenu);
        }
      });
    }, 0);
  }
}

function toggleAdmin() {
  debounceAction('admin', function() {
    adminOpen = !adminOpen;
    g('admin-panel').classList.toggle('open', adminOpen);
    var btn = g('admin-btn');
    if (btn) { btn.classList.toggle('active', adminOpen); }
    voxDebug('ADMIN', 'toggle', { open: adminOpen });
  }, 300);
}

function showTestResult(msg, cls) {
  var el = g('test-result');
  if (!el) { return; }
  el.textContent = msg;
  el.className   = 'test-result ' + (cls || '');
}

// =============================================================================
// STATUS DOTS
// =============================================================================

function setDot(dotId, state) {
  var el = g('dot-' + dotId);
  if (el) { el.className = 'status-dot ' + state; }
}

async function checkDeviceStatus() {
  voxDebug('DEVICE', 'checking mic + cam');
  try {
    var s = await navigator.mediaDevices.getUserMedia({ audio: true });
    s.getTracks().forEach(function(t){ t.stop(); });
    setDot('mic', 'ok');
    voxDebug('DEVICE', 'mic ok');
  } catch(_e) {
    setDot('mic', 'error');
    voxDebug('DEVICE', 'mic error', { msg: _e.message });
  }
  try {
    var s2 = await navigator.mediaDevices.getUserMedia({ video: true });
    s2.getTracks().forEach(function(t){ t.stop(); });
    setDot('cam', 'ok');
    voxDebug('DEVICE', 'cam ok');
  } catch(_e) {
    setDot('cam', 'error');
    voxDebug('DEVICE', 'cam error', { msg: _e.message });
  }
}

// =============================================================================
// MIC
// =============================================================================

var mediaRecorder = null;
var audioChunks   = [];
var micStream     = null;
var vadEnabled    = true;
var vadAnalyser   = null;
var vadTimer      = null;
var vadSilenceMs  = 1500;
var vadThreshold  = 0.01;
var vadRecording  = false;

function toggleMic() {
  debounceAction('mic', function() {
    if (micActive) { stopMic(); return; }
    if (isLoading) { llmStop(); isLoading = false; updateStopBtn(); }
    ttsStop();
    startMic();
  }, 300);
}

async function startMic() {
  voxDebug('MIC', 'start');
  try {
    micStream = await navigator.mediaDevices.getUserMedia({ audio: true });
  } catch(e) {
    setDot('mic', 'error');
    appendMsg('assistant', t('micDenied'));
    voxDebug('MIC', 'denied', { msg: e.message });
    return;
  }

  audioChunks = [];

  var mimeType = '';
  if (MediaRecorder.isTypeSupported('audio/webm;codecs=opus')) { mimeType = 'audio/webm;codecs=opus'; }
  else if (MediaRecorder.isTypeSupported('audio/webm'))        { mimeType = 'audio/webm'; }
  else if (MediaRecorder.isTypeSupported('audio/ogg'))         { mimeType = 'audio/ogg'; }
  if (!mimeType && MediaRecorder.isTypeSupported('audio/mp4')) { mimeType = 'audio/mp4'; }

  try {
    mediaRecorder = mimeType
      ? new MediaRecorder(micStream, { mimeType: mimeType })
      : new MediaRecorder(micStream);
  } catch(e) {
    voxDebug('MIC', 'recorder init error', { msg: e.message });
    stopMic(); return;
  }

  mediaRecorder.ondataavailable = function(e) {
    if (e.data && e.data.size > 0) { audioChunks.push(e.data); }
  };

  mediaRecorder.onstop = function() {
    var mime = mediaRecorder.mimeType || mimeType || 'audio/webm';
    var blob = new Blob(audioChunks, { type: mime });
    audioChunks = [];
    vadCleanup();
    transcribeSTT(blob, mime);
  };

  micActive    = true;
  vadRecording = false;
  var btn = g('mic-btn');
  if (btn) { btn.classList.add('recording'); }

  if (vadEnabled) {
    setDot('mic', 'ok');
    vadStart(micStream, mediaRecorder);
  } else {
    mediaRecorder.start(100);
    setDot('mic', 'pulse');
  }
}

// =============================================================================
// VAD
// =============================================================================

function vadStart(stream, recorder) {
  try {
    var ctx     = new (window.AudioContext || window.webkitAudioContext)();
    var source  = ctx.createMediaStreamSource(stream);
    vadAnalyser = ctx.createAnalyser();
    vadAnalyser.fftSize = 512;
    source.connect(vadAnalyser);
    var buf          = new Uint8Array(vadAnalyser.frequencyBinCount);
    var silenceStart = null;

    function vadTick() {
      if (!micActive) { return; }
      vadAnalyser.getByteFrequencyData(buf);
      var sum   = buf.reduce(function(a, b) { return a + b; }, 0);
      var level = sum / buf.length / 255;

      if (level > vadThreshold) {
        silenceStart = null;
        if (!vadRecording) {
          vadRecording = true;
          ttsStop();
          if (isLoading) { llmStop(); isLoading = false; updateStopBtn(); setDot('llm', 'ok'); }
          if (recorder && recorder.state === 'inactive') { recorder.start(100); }
          setDot('mic', 'pulse');
          voxDebug('VAD', 'voice detected');
        }
      } else {
        if (vadRecording) {
          if (!silenceStart) { silenceStart = Date.now(); }
          if (Date.now() - silenceStart > vadSilenceMs) {
            voxDebug('VAD', 'silence timeout — stopping');
            stopMic(); return;
          }
        }
      }
      vadTimer = requestAnimationFrame(vadTick);
    }
    vadTimer = requestAnimationFrame(vadTick);
  } catch(e) {
    voxDebug('VAD', 'error', { msg: e.message });
  }
}

function vadCleanup() {
  if (vadTimer) { cancelAnimationFrame(vadTimer); vadTimer = null; }
  vadAnalyser = null; vadRecording = false;
}

function stopMic() {
  micActive = false;
  var btn = g('mic-btn');
  if (btn) { btn.classList.remove('recording'); }
  if (mediaRecorder && mediaRecorder.state !== 'inactive') {
    try { mediaRecorder.stop(); } catch(_e) {}
  }
  if (micStream) { micStream.getTracks().forEach(function(t){ t.stop(); }); micStream = null; }
  setDot('mic', 'pulse');
  voxDebug('MIC', 'stopped');
}

async function transcribeSTT(blob, mime) {
  voxDebug('STT', 'start', { size: blob.size, mime: mime });
  try {
    var formData = new FormData();
    var ext = 'webm';
    if (mime && mime.includes('ogg'))  { ext = 'ogg'; }
    if (mime && mime.includes('mp4'))  { ext = 'm4a'; }
    if (mime && mime.includes('mpeg')) { ext = 'mp3'; }
    if (mime && mime.includes('opus')) { ext = 'opus'; }

    formData.append('file', blob, 'audio.' + ext);
    formData.append('model', config.sttModel || 'Systran/faster-whisper-small');
    formData.append('language', 'fr');
    formData.append('response_format', 'json');
    if (config.sttUrl) { formData.append('_stt_url', config.sttUrl); }
    var sttKey = config.sttKey || config.sttBearer || '';
    if (sttKey) { formData.append('_stt_bearer', sttKey); }

    var r = await fetch('/proxy-stt.php', {
      method: 'POST', mode: 'cors', credentials: 'same-origin',
      body: formData
    });

    if (!r.ok) {
      if (r.status === 401) {
        appendMsg('assistant', t('micExpired'));
        setDot('mic', 'error'); return;
      }
      if (r.status === 502) {
        appendMsg('assistant', t('micUnavailable'));
        setDot('mic', 'error'); return;
      }
      throw new Error('STT HTTP ' + r.status);
    }

    var data       = await r.json();
    var transcript = (data.text || '').trim();
    voxDebug('STT', 'result', { text: transcript.slice(0, 50) });

    setDot('mic', 'ok');

    var sttNoise = ['sous-titrage','sous-titre','soustitrage','st 501','st501','merci','you','the','i','a','...','..', '.'];
    var lower    = transcript.toLowerCase();
    var isNoise  = !transcript || transcript.length < 3
      || sttNoise.some(function(n) { return lower === n || lower.indexOf(n) === 0; });

    if (isNoise) {
      voxDebug('STT', 'noise filtered', { text: transcript });
      if (config.naturalConversation) { setTimeout(function() { startMic(); }, 300); }
      return;
    }

    var input = g('user-input');
    input.value = transcript;
    autoResize(input);
    sendMessage();

  } catch(e) {
    voxDebug('STT', 'error', { msg: e.message });
    setDot('mic', 'error');
    setTimeout(function() { setDot('mic', 'ok'); }, 3000);
  }
}

// =============================================================================
// MCP
// =============================================================================

async function callMcp(tool, params) {
  voxDebug('MCP', 'call', { tool: tool });
  var r = await fetch('/proxy-mcp.php', {
    method: 'POST', mode: 'same-origin', credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ tool: tool, params: params })
  });
  if (!r.ok) { throw new Error('MCP HTTP ' + r.status); }
  var data = await r.json();
  return data.output || data.error || '';
}

function appendMcpResult(tool, output) {
  var text = '```powershell\n' + output + '\n```';
  var bt   = appendMsg('assistant', '');
  bt.innerHTML = renderMsg('**[MCP:' + tool + ']**\n' + text);
}

// =============================================================================
// BOOT
// =============================================================================

document.addEventListener('DOMContentLoaded', function() {
  voxDebug('MAIN', 'DOMContentLoaded', { version: MAIN_VERSION });

  ['llm', 'tts', 'mic', 'cam'].forEach(function(id) {
    var el = document.getElementById('dot-' + id);
    if (el) { el.className = 'status-dot pulse'; }
  });

  function unlockOnFirstTap() {
    ttsUnlock();
    document.removeEventListener('touchstart', unlockOnFirstTap);
    document.removeEventListener('mousedown',  unlockOnFirstTap);
  }
  document.addEventListener('touchstart', unlockOnFirstTap, { passive: true });
  document.addEventListener('mousedown',  unlockOnFirstTap, { passive: true });

  init();
});