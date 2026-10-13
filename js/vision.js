// =============================================================================
// vision.js — Webcam, détection de changement, capture, description
// =============================================================================
'use strict';

var facingMode = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent) ? 'environment' : 'user';

var visionStream   = null;
var visionInterval = null;
var lastFrameData  = null;
var currentDelta   = 0;

// =============================================================================
// DÉMARRAGE / ARRÊT
// =============================================================================

async function startVision() {
  if (visionStream) { return; }
  try {
    visionStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: facingMode, width: 640, height: 480 } });
    g('cam-preview').srcObject = visionStream;
    setDot('cam', 'ok');
    lastFrameData  = null;
    currentDelta   = 0;
    visionInterval = setInterval(computeDelta, 1000);
    g('vision-overlay').classList.add('show');
    refreshVisionOverlay();
  } catch(e) {
    setDot('cam', 'error');
    alert('Webcam inaccessible : ' + e.message);
    config.visionMode = 'off';
    g('cfg-vision-mode').value = 'off';
    g('vision-auto-section').style.display = 'none';
    saveConfig();
  }
}

function stopVision() {
  if (visionInterval) { clearInterval(visionInterval); visionInterval = null; }
  if (visionStream)   { visionStream.getTracks().forEach(function(t){ t.stop(); }); visionStream = null; }
  var vp = g('cam-preview');
  if (vp) { vp.srcObject = null; }
  g('vision-overlay').classList.remove('show');
  g('cam-capture-btn').classList.remove('show');
  lastFrameData = null; currentDelta = 0;
  updateDelta(0);
  config.visionMode = 'off';
  g('cfg-vision-mode').value = 'off';
  g('vision-auto-section').style.display = 'none';
  saveConfig();
}

// =============================================================================
// MODE (appelé au changement Admin ou à l'init)
// =============================================================================

function onVisionModeChange() {
  config.visionMode = g('cfg-vision-mode').value;
  g('vision-auto-section').style.display = (config.visionMode === 'auto') ? '' : 'none';
  saveConfig();
  applyVisionMode();
}

function applyVisionMode() {
  var mode = config.visionMode;
  g('vision-auto-section').style.display = (mode === 'auto') ? '' : 'none';

  if (mode === 'off') {
    if (visionInterval) { clearInterval(visionInterval); visionInterval = null; }
    if (visionStream)   { visionStream.getTracks().forEach(function(t){ t.stop(); }); visionStream = null; }
    var vp = g('cam-preview');
    if (vp) { vp.srcObject = null; }
    g('vision-overlay').classList.remove('show');
    g('cam-capture-btn').classList.remove('show');
    return;
  }

  if (!visionStream) { startVision(); }
  else               { refreshVisionOverlay(); }
}

function refreshVisionOverlay() {
  var mode = config.visionMode;
  g('vision-overlay').classList.add('show');
  if (mode === 'auto') {
    g('vision-info').textContent = '👁 AUTO — image jointe si Δ ≥ ' + config.visionThreshold + '%';
    g('delta-wrap').style.display = '';
    g('cam-capture-btn').classList.remove('show');
  } else if (mode === 'manual') {
    g('vision-info').textContent = '📸 MANUEL — cliquez le bouton caméra';
    g('delta-wrap').style.display = 'none';
    g('cam-capture-btn').classList.add('show');
  }
}

// =============================================================================
// CAPTURE & DELTA
// =============================================================================

/** Capture frame courante → base64 JPEG sans prefix */
function captureFrame() {
  var video = g('cam-preview');
  if (!video || video.readyState < 2) { return null; }
  var c = document.createElement('canvas');
  c.width  = video.videoWidth  || 640;
  c.height = video.videoHeight || 480;
  c.getContext('2d').drawImage(video, 0, 0);
  return c.toDataURL('image/jpeg', 0.85).split(',')[1];
}

/** Calcule le % de pixels ayant changé (canvas 80×60 downscalé) */
function computeDelta() {
  var video = g('cam-preview');
  if (!video || video.readyState < 2) { return; }
  var canvas = g('diff-canvas');
  var ctx    = canvas.getContext('2d', { willReadFrequently: true });
  ctx.drawImage(video, 0, 0, 80, 60);
  var fd = ctx.getImageData(0, 0, 80, 60);
  if (!lastFrameData) { lastFrameData = fd; return; }
  var d1 = lastFrameData.data, d2 = fd.data;
  var changed = 0, total = d1.length / 4;
  for (var i = 0; i < d1.length; i += 4) {
    var avg = (Math.abs(d1[i]-d2[i]) + Math.abs(d1[i+1]-d2[i+1]) + Math.abs(d1[i+2]-d2[i+2])) / 3;
    if (avg > 20) { changed++; }
  }
  currentDelta  = Math.round((changed / total) * 100);
  lastFrameData = fd;
  updateDelta(currentDelta);
}

function updateDelta(pct) {
  var fill = g('delta-fill'), val = g('delta-val');
  if (!fill || !val) { return; }
  var p = Math.min(pct, 100);
  fill.style.width = p + '%';
  val.textContent  = p + '%';
  fill.className   = 'delta-fill' + (p >= config.visionThreshold ? ' ready' : p > 0 ? ' active' : '');
}

function flashPreview() {
  var f = g('cam-flash');
  if (!f) { return; }
  f.classList.add('flash');
  setTimeout(function(){ f.classList.remove('flash'); }, 180);
}

// =============================================================================
// MODE MANUEL — description autonome sur clic 📸
// =============================================================================

async function manualCapture() {
  if (!visionStream || isLoading) { return; }
  var imageB64 = captureFrame();
  if (!imageB64) { return; }
  flashPreview();

  var thumbUrl = 'data:image/jpeg;base64,' + imageB64;
  isLoading = true;
  setDot('ollama', 'pulse');

  try {
    var headers = { 'Content-Type': 'application/json' };
    if (config.ollamaKey) { headers['Authorization'] = 'Bearer ' + config.ollamaKey; }
    showTyping();
    var r = await fetch(config.ollamaUrl + '/api/chat', {
      method: 'POST', mode: 'cors', credentials: 'omit',
      headers: headers,
      body: JSON.stringify({
        model: config.visionModel, stream: false,
        messages: [{ role: 'user', content: config.visionPrompt, images: [imageB64] }]
      })
    });
    hideTyping();
    if (!r.ok) { throw new Error('HTTP ' + r.status); }
    var data  = await r.json();
    var reply = (data.message && data.message.content) ? data.message.content : '';
    appendMsg('vision', reply, thumbUrl);
    if (config.visionSpeak && config.audioEnabled) { ttsSay(reply); }
    setDot('ollama', 'ok');
  } catch(e) {
    hideTyping();
    appendMsg('vision', '⚠ Erreur vision : ' + e.message, thumbUrl);
    setDot('ollama', 'error');
  }

  isLoading = false;
  updateStopBtn();
}

// =============================================================================
// BASCULE CAMÉRA avant/arrière (mobile)
// =============================================================================

function toggleCamera() {
  facingMode = (facingMode === 'user') ? 'environment' : 'user';
  var btn = g('cam-flip-btn');
  if (btn) { btn.title = facingMode === 'environment' ? 'Caméra frontale' : 'Caméra arrière'; }
  if (!visionStream) { return; }
  // Arrêter complètement le stream — obligatoire sur iOS avant de changer de caméra
  if (visionInterval) { clearInterval(visionInterval); visionInterval = null; }
  visionStream.getTracks().forEach(function(t){ t.stop(); });
  visionStream = null;
  var vp = g('cam-preview');
  if (vp) { vp.srcObject = null; }
  // Petit délai pour laisser iOS libérer la caméra
  setTimeout(function() { startVision(); }, 300);
}
  