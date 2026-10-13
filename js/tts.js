// =============================================================================
// tts.js — Moteur TTS OpenAI-compatible (TTS, Qwen-TTS, etc.)
// Pipeline : 2ème virgule → envoi immédiat, puis chaque point/!/?
// =============================================================================
'use strict';

var ttsQueue      = [];
var ttsWorkerBusy = false;
var ttsPaused     = false;
var currentTtsCtr = null;
var currentSource = null;

// Stockage audio pour relecture (session uniquement)
var replayBuffers  = [];   // ArrayBuffer[]
var replayIndex    = 0;    // index lecture courante

// AudioContext unique
var audioCtx = null;

// Référence DOM du message en cours de lecture — géré par ui.js
var currentTtsDomRef = null;

function getAudioCtx() {
  if (!audioCtx) {
    audioCtx = new (window.AudioContext || window.webkitAudioContext)();
  }
  return audioCtx;
}

function ttsUnlock() {
  var ctx = getAudioCtx();
  ctx.resume().catch(function(){});
  try {
    var buf = ctx.createBuffer(1, 1, ctx.sampleRate);
    var src = ctx.createBufferSource();
    src.buffer = buf;
    src.connect(ctx.destination);
    src.start(0);
  } catch(_e) {}
}

// =============================================================================
// NETTOYAGE
// =============================================================================
function countTripleAsterisks(text) {
    const regex = /\`\`\`/g;
    let count = 0;

    while (regex.exec(text) !== null) {
        count++;
    }

    return count;
}

function cleanForTTS(text) {
  text = text.replace(/`{0,3}\s*tool@chat\([^)]*\)\s*/g, '');
  text = text.replace(/`^tool@.*mode=admin\s/g, '');
  if(true){
    return text
      // Chiffre + point → "chiffre point" (ex: 3.5 → 3 point 5)
      .replace(/<think>[\s\S]*?<\/think>/g,'')
      .replace(/(\d)\.(\d)/g, '$1 point $2')
      .replace(/\[([^\]]+)\]\([^)]+\)/g, '$1')
      .replace(/https?:\/\/\S+/g, '')
      .replace(/\*{1,3}|_{1,2}/g, '')
      .replace(/^#{1,6}\s+/gm, '')
      .replace(/^>\s*/gm, '')
      .replace(/^[\-\*]\s+/gm, '')
      .replace(/[\u{1F300}-\u{1FAFF}]/gu, '')
      .replace(/ë/g,'é').replace(/Ë/g,'É')
      .replace(/ï/g,'i').replace(/Ï/g,'I')
      .replace(/ü/g,'u').replace(/Ü/g,'U')
      .replace(/ÿ/g,'i').replace(/Ÿ/g,'I')
      .replace(/œ/g,'eu').replace(/Œ/g,'Eu')
      .replace(/æ/g,'é').replace(/Æ/g,'É')
      .replace(/\s{2,}/g,' ')
      .trim();
    }
}

// =============================================================================
// DÉCOUPE EN PHRASES — masquer la latence au maximum
// =============================================================================

var ttsBuffer      = '';
var ttsWordCount   = 0;
var commaCount     = 0;
var firstSent      = false;
var inCodeBlock    = true;  // true entre ``` et ```

function resetTtsBuffer() {
  ttsBuffer = ''; ttsWordCount = 0;
  commaCount = 0; firstSent = false; inCodeBlock = false;
}

function countWords(str) {
  return str.split(/\s+/).filter(function(w){ return w.length > 0; }).length;
}

function sendChunk(text) {
  var clean = cleanForTTS(text).replace(/\s+/g, ' ').trim();
  ttsBuffer = ''; ttsWordCount = 0; commaCount = 0;
  console.log('[TTS] sendChunk clean.length='+clean.length+' text='+clean.slice(0,30));
  if (clean.length > 3) { ttsEnqueue(clean); }
}

function feedTtsToken(token) {
  // Détecter ``` dans le token
  if (token.indexOf('```') !== -1) {
    var parts = token.split('```');
    for (var pi = 0; pi < parts.length; pi++) {
      if (pi > 0) {
        if (!inCodeBlock) {
          // Entrée dans un bloc — lire le nom du langage si présent
          var firstLine = parts[pi].split('\n')[0].trim();
          if (firstLine.length > 0 && /^[a-zA-Z0-9_+-]+$/.test(firstLine)) {
            feedTtsText('code ' + firstLine + '. ');
          }
          // Le reste du part est du code → ignorer
        }
        // Si on sortait d'un bloc (inCodeBlock=true) → parts[pi] est du texte normal
        inCodeBlock = !inCodeBlock;
        if (!inCodeBlock) {
          // Texte après ``` de fermeture
          var afterClose = parts[pi].replace(/^[a-zA-Z0-9_+-]*\n?/, '').trim();
          if (afterClose.length > 0) { feedTtsText(afterClose); }
        }
      } else {
        // Texte avant le premier ```
        var part = parts[pi].trim();
        if (!inCodeBlock && part.length > 0) { feedTtsText(part); }
      }
    }
    return;
  }
  if (inCodeBlock) { return; }
  feedTtsText(token);
}

function feedTtsText(token) {
  
  ttsBuffer    += token;
  ttsWordCount += countWords(token);

  var trimmed = ttsBuffer.trimEnd();

  // — Phase 1 : avant premier envoi
  // Déclenche sur : 2ème virgule OU marqueur de ponctuation fort
  if (!firstSent) {
    var commas = (token.match(/,/g) || []).length;
    commaCount += commas;
    var hasFirstPoint = /\.[)\s"»]|\.$/.test(trimmed);
    console.log('[TTS] phase1 words='+ttsWordCount+' commas='+commaCount+' point='+hasFirstPoint+' buf='+ttsBuffer.slice(-20));
    if (commaCount >= 1 || hasFirstPoint) {
      firstSent = true;
      console.log('[TTS] sendChunk:', ttsBuffer.slice(0,40));
      sendChunk(ttsBuffer);
    }
    return;
  }

  // — Phase 2 : après premier envoi → point toujours, !? si > minWordsStrong
  var minStrong = (config.ttsMinWordsStrong || 20);
  var hasPoint  = /\.[)\s"»]|\.$/.test(trimmed);
  var hasStrong = /[!?][)\s"»]|[!?]$/.test(trimmed) && ttsWordCount >= minStrong;

  if (hasPoint || hasStrong) {
    sendChunk(ttsBuffer);
  }
}

function flushTtsBuffer() {
  if (ttsBuffer.trim().length > 1) {
    var c = cleanForTTS(ttsBuffer); ttsBuffer = '';
    if (c.length > 1) { ttsEnqueue(c); }
  }
}

// =============================================================================
// QUEUE AUDIO
// =============================================================================

function ttsEnqueue(text) {
  console.log('[TTS] enqueue audioEnabled='+config.audioEnabled+' text='+text.slice(0,30));
  if (!config.audioEnabled) { return; }
  // Stocker texte + référence bulle au moment de l'enqueue
  ttsQueue.push({ text: text, bubble: currentTtsDomRef });
  ttsWorkerRun();
}

/** Définir la bulle DOM à surligner pendant la lecture */
function ttsSetDomRef(el) {
  currentTtsDomRef = el;
  if (typeof uiSetCurrentBubble === 'function') { uiSetCurrentBubble(el); }
}

function ttsWorkerRun() {
  var occurenceTerminal = "tool@terminal";
  var occurenceChat = "tool@chat(conversation)"; 

  const patternOccurenceTerminal = /.*?tool@terminal.*action=.*mode=admin.*?/gi;
    

  //console.log('[TTS] workerRun busy='+ttsWorkerBusy+' queue='+ttsQueue.length);
  if (ttsWorkerBusy || ttsQueue.length === 0) { return; }
  ttsWorkerBusy = true;
  var item   = ttsQueue.shift();
  var phrase = typeof item === 'object' ? item.text   : item;
  var bubble = typeof item === 'object' ? item.bubble : currentTtsDomRef;

  // var filtered_text = phrase.replace('tool\@', '');
  
  // // phrase = filtered_text;
  // if (/tool@terminal/i.test(phrase)){
  //   ;//phrase = "";
  // }

  //const regex = /tool@terminal[\s\S]*?action=(.+)objectif=(.+) mode=admin$/;
  //phrase = phrase.replace(/tool@terminal[\s\S]*?path=([^\s]+)[\s\S].*?action=([^\s]+) mode=admin/g, "");
  phrase = phrase.replaceAll(occurenceChat, "");

  // phrase = phrase.replace(patternOccurenceTerminal, '...');
  
  ttsSpeak(phrase, bubble).then(function() {
    ttsWorkerBusy = false;
    ttsWorkerRun();
  });
}

var _currentTtsChunk = '';


async function ttsSpeak(text, bubble) {
  _currentTtsChunk = text;
  if (bubble && typeof uiSetCurrentBubble === 'function') { uiSetCurrentBubble(bubble); }
  setDot('tts', 'pulse');
  currentTtsCtr = new AbortController();
  try {
    // Instruct : prefixer le texte si activé
    var input = (config.ttsInstruct && config.ttsInstructPrompt)
      ? config.ttsInstructPrompt + ' ' + text
      : text;
 
    // Lang depuis le select Célia(fr) / Alex(en) — jamais la voix technique
    var lang = config.ttsVoiceId || 'fr';
 
    var r = await fetch('/proxy-tts.php', {
      method: 'POST', mode: 'same-origin', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        input:  input,
        lang:   lang,
        format: config.ttsFormat || 'mp3',
      }),
      signal: currentTtsCtr.signal
    });
 
    if (!r.ok) {
      var errData = null;
      try { errData = await r.json(); } catch(_e) {}
      var errMsg = (errData && errData.error) ? errData.error : 'TTS HTTP ' + r.status;
      throw new Error(errMsg);
    }
 
    // Vérifier que la réponse est bien de l'audio
    var contentType = r.headers.get('content-type') || '';
    if (contentType.includes('application/json')) {
      var errJson = await r.json();
      throw new Error((errJson && errJson.error) ? errJson.error : 'Erreur TTS serveur');
    }
 
    var arrayBuffer = await r.arrayBuffer();
 
    // Stocker pour relecture si activé
    if (config.replayAudio) {
      replayBuffers.push(arrayBuffer.slice(0));
      var maxReplay = config.replayMaxBuffers || 10;
      if (replayBuffers.length > maxReplay) { replayBuffers.shift(); }
      updateReplayBtn();
    }
 
    await playAudio(arrayBuffer);
    setDot('tts', 'ok');
  } catch(e) {
    if (e.name !== 'AbortError') {
      setDot('tts', 'warn');
      console.warn('[TTS]', e.message);
    }
  } finally {
    currentTtsCtr = null;
  }
}

// =============================================================================
// LECTURE — AudioContext unifié
// =============================================================================

function playAudio(arrayBuffer) {
  return new Promise(function(resolve) {
    var ctx = getAudioCtx();
    ctx.resume().then(function() {
      ctx.decodeAudioData(arrayBuffer,
        function(decoded) {
          if (currentSource) {
            try { currentSource.stop(); } catch(_e) {}
            currentSource = null;
          }
          var source    = ctx.createBufferSource();
          source.buffer = decoded;
          source.connect(ctx.destination);
          currentSource = source;
          g('audio-playing').classList.add('visible');
          showPauseBtn(true);
          source.onended = function() {
            currentSource = null;
            g('audio-playing').classList.remove('visible');
            showPauseBtn(false);
            if (typeof uiHighlightStop === 'function') { uiHighlightStop(); }
            resolve();
          };
          if (!ttsPaused) {
            source.start(0);
            // Highlight via ui.js
            if (config.ttsHighlightEnabled && typeof uiHighlightChunk === 'function') {
              uiHighlightChunk(_currentTtsChunk || '', decoded.duration || 0);
            }
          } else { resolve(); }
        },
        function(err) { console.warn('decodeAudioData:', err); resolve(); }
      );
    }).catch(function(err) { console.warn('AudioContext resume:', err); resolve(); });
  });
}

// =============================================================================
// SURLIGNAGE TTS — mot par mot synchronisé avec la durée audio
// =============================================================================



function esc(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

// =============================================================================
// RELECTURE
// =============================================================================

function updateReplayBtn() {
  var btn = g('replay-btn');
  if (!btn) { return; }
  btn.style.display = (config.replayAudio && replayBuffers.length > 0) ? 'flex' : 'none';
  var lbl = g('replay-label');
  if (lbl) { lbl.textContent = (replayIndex + 1) + '/' + replayBuffers.length; }
}

function replayPlay() {
  if (!replayBuffers.length) { return; }
  ttsUnlock();
  playAudio(replayBuffers[replayIndex].slice(0));
}

function replayNext() {
  if (!replayBuffers.length) { return; }
  replayIndex = (replayIndex + 1) % replayBuffers.length;
  updateReplayBtn();
  replayPlay();
}

function replayReset() {
  replayBuffers = []; replayIndex = 0;
  updateReplayBtn();
}

// =============================================================================
// PAUSE / REPRISE
// =============================================================================

function ttsPauseToggle() {
  var btn = g('pause-btn');
  if (!audioCtx) { return; }
  if (ttsPaused) {
    ttsPaused = false;
    audioCtx.resume();
    if (btn) {
      btn.className = 'icon-btn pause';
      btn.title     = 'Pause';
      btn.innerHTML = '<svg viewBox="0 0 24 24"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg>';
    }
  } else {
    ttsPaused = true;
    audioCtx.suspend();
    if (btn) {
      btn.className = 'icon-btn resume';
      btn.title     = 'Reprendre';
      btn.innerHTML = '<svg viewBox="0 0 24 24"><polygon points="5 3 19 12 5 21 5 3"/></svg>';
    }
  }
}

function showPauseBtn(visible) {
  var btn = g('pause-btn');
  if (btn) { btn.style.display = visible ? 'flex' : 'none'; }
  if (!visible) { ttsPaused = false; }
}

document.addEventListener('visibilitychange', function() {
  if (document.visibilityState === 'visible' && audioCtx && !ttsPaused) {
    audioCtx.resume().catch(function(){});
  }
});

// =============================================================================
// STOP
// =============================================================================

function ttsStop() {
  ttsQueue = []; ttsWorkerBusy = false;
  if (typeof uiHighlightStop === 'function') { uiHighlightStop(); }
  if (currentTtsCtr) { currentTtsCtr.abort(); currentTtsCtr = null; }
  if (currentSource) { try { currentSource.stop(); } catch(_e) {} currentSource = null; }
  if (audioCtx)      { audioCtx.resume().catch(function(){}); }
  g('audio-playing').classList.remove('visible');
  showPauseBtn(false);
  setDot('tts', 'ok');
  resetTtsBuffer();
}

function ttsSay(text) {
  var clean = cleanForTTS(text);
  if (clean.length > 1) { ttsEnqueue(clean); }
}