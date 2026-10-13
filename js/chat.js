// =============================================================================
// chat.js — Affichage des messages, historique LLM, navigation clavier
// =============================================================================
'use strict';

var chatHistory  = [];   // historique LLM [{role, content}]
var inputHistory = [];   // historique des messages tapés par l'user
var inputIndex   = -1;   // position courante dans inputHistory (flèches ↑↓)

// =============================================================================
// MESSAGES
// =============================================================================

function esc(t) {
  return String(t)
    .replace(/&/g,'&amp;')
    .replace(/</g,'&lt;')
    .replace(/>/g,'&gt;')
    .replace(/\n/g,'<br>');
}

/**
 * Ajoute un message dans le chat.
 * @param {'user'|'assistant'|'vision'} role
 * @param {string} text
 * @param {string|null} thumbDataUrl  — miniature image optionnelle
 * @returns {HTMLElement}  span.bt  (cible pour le streaming)
 */
function appendMsg(role, text, thumbDataUrl) {
  thumbDataUrl = thumbDataUrl || null;
  rmEmptyState();

  var msgs     = g('messages');
  var isUser   = (role === 'user');
  var isVision = (role === 'vision');

  var div      = document.createElement('div');
  div.className = 'message' + (isUser ? ' user' : '') + (isVision ? ' vision' : '');

  var aClass = isUser ? 'usr' : isVision ? 'vis' : 'ai';
  var aLabel = isUser ? 'U'   : isVision ? '👁'  : 'AI';

  var inner = '';
  if (thumbDataUrl) {
    var tagClass = isVision ? '' : ' cyan';
    inner += '<div class="msg-tag' + tagClass + '">' + (isVision ? '▸ VISION' : '📷 image jointe') + '</div>';
    inner += '<img class="msg-thumb" src="' + thumbDataUrl + '" alt="">';
  }
  inner += '<span class="bt">' + esc(text) + '</span>';
  // Bouton copie uniquement sur les messages AI et vision
  if (!isUser) {
    inner += '<div class="msg-actions">'
           // Bouton relire en audio
           + '<button class="msg-btn audio-btn" onclick="replayMsg(this)" title="Relire">'
           + '<svg viewBox="0 0 24 24"><polygon points="5 3 19 12 5 21 5 3"/></svg>'
           + '<span>LIRE</span></button>'
           // Bouton copie
           + '<button class="msg-btn copy-btn" onclick="copyMsg(this)" title="Copier">'
           + '<svg viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2"/>'
           + '<path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>'
           + '<span>COPIER</span></button>'
           + '</div>';
  }

  div.innerHTML = '<div class="msg-avatar ' + aClass + '">' + aLabel + '</div>'
                + '<div class="msg-bubble">' + inner + '</div>';
  msgs.appendChild(div);
  msgs.scrollTop = msgs.scrollHeight;
  return div.querySelector('.bt');
}

function showTyping() {
  rmEmptyState();
  var msgs = g('messages');
  var d    = document.createElement('div');
  d.id = 'typing'; d.className = 'typing-indicator';
  d.innerHTML = '<div class="msg-avatar ai">AI</div>'
              + '<div class="typing-dots"><span></span><span></span><span></span></div>';
  msgs.appendChild(d);
  msgs.scrollTop = msgs.scrollHeight;
}

function hideTyping() {
  var el = g('typing');
  if (el) { el.remove(); }
}

function clearHistory() {
  chatHistory = [];
  var msgs = g('messages');
  msgs.innerHTML = '';
  msgs.appendChild(mkEmptyState());
}

function mkEmptyState() {
  var d = document.createElement('div');
  d.className = 'empty-state'; d.id = 'empty-state';
  d.innerHTML = '<div class="icon">◈</div>'
              + '<div>INTERFACE PRÊTE</div>'
              + '<div class="empty-sub">Configurez les endpoints puis commencez à écrire</div>';
  return d;
}

function rmEmptyState() {
  var el = g('empty-state');
  if (el) { el.remove(); }
}

/** Mise à jour du sous-titre empty state (probe LLM) */
function setProbeMsg(msg) {
  var el = g('empty-state');
  if (!el) { return; }
  var sub = el.querySelector('.probe-msg');
  if (!sub) {
    sub = document.createElement('div');
    sub.className = 'probe-msg';
    el.appendChild(sub);
  }
  sub.textContent = msg || '';
}

// =============================================================================
// NAVIGATION CLAVIER — flèches ↑↓ dans le champ input
// =============================================================================

function handleKey(e) {
  var input = g('user-input');

  // Envoyer avec Entrée (sans Shift)
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault();
    sendMessage();
    return;
  }

  // Flèche ↑ — remonter l'historique (seulement si curseur en ligne 1)
  if (e.key === 'ArrowUp') {
    var lines = input.value.split('\n');
    var cursorPos = input.selectionStart;
    var isFirstLine = (cursorPos <= lines[0].length);
    if (isFirstLine && inputHistory.length > 0) {
      e.preventDefault();
      if (inputIndex === -1) { inputIndex = inputHistory.length - 1; }
      else if (inputIndex > 0) { inputIndex--; }
      input.value = inputHistory[inputIndex];
      autoResize(input);
    }
    return;
  }

  // Flèche ↓ — redescendre l'historique
  if (e.key === 'ArrowDown') {
    if (inputIndex === -1) { return; }
    e.preventDefault();
    if (inputIndex < inputHistory.length - 1) {
      inputIndex++;
      input.value = inputHistory[inputIndex];
    } else {
      inputIndex = -1;
      input.value = '';
    }
    autoResize(input);
    return;
  }

  // Échap — vide le champ
  if (e.key === 'Escape') {
    input.value = '';
    inputIndex = -1;
    autoResize(input);
  }
}

function autoResize(el) {
  el.style.height = 'auto';
  el.style.height = Math.min(el.scrollHeight, 120) + 'px';
}

/** Ajoute un message à l'historique de saisie (flèches) */
function pushInputHistory(text) {
  if (!text) { return; }
  // Éviter les doublons consécutifs
  if (inputHistory[inputHistory.length - 1] === text) { return; }
  inputHistory.push(text);
  if (inputHistory.length > 50) { inputHistory.shift(); }
  inputIndex = -1;
}

// =============================================================================
// COPIE MESSAGE
// =============================================================================

function copyMsg(btn) {
  var bubble = btn.closest('.msg-bubble');
  var bt     = bubble ? bubble.querySelector('.bt') : null;
  if (!bt) { return; }
  var text = bt.innerText || bt.textContent;
  navigator.clipboard.writeText(text).then(function() {
    btn.classList.add('copied');
    btn.querySelector('span').textContent = 'COPIÉ';
    setTimeout(function() {
      btn.classList.remove('copied');
      btn.querySelector('span').textContent = 'COPIER';
    }, 2000);
  }).catch(function() {
    // Fallback sans clipboard API
    var ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed'; ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.select();
    document.execCommand('copy');
    document.body.removeChild(ta);
    btn.classList.add('copied');
    btn.querySelector('span').textContent = 'COPIÉ';
    setTimeout(function() {
      btn.classList.remove('copied');
      btn.querySelector('span').textContent = 'COPIER';
    }, 2000);
  });
}

// =============================================================================
// RELIRE UN MESSAGE EN AUDIO
// =============================================================================

function replayMsg(btn) {
  var bubble = btn.closest('.msg-bubble');
  var bt     = bubble ? bubble.querySelector('.bt') : null;
  if (!bt) { return; }
  var text = bt.innerText || bt.textContent;
  if (!text.trim()) { return; }
  ttsUnlock();   // débloquer iOS
  ttsStop();     // stopper lecture en cours
  ttsSay(text);  // envoyer à TTS
}

// =============================================================================
// RENDU FINAL MESSAGE — code blocks + copie
// =============================================================================

function renderMsg(text) {
  // Séparer les blocs ```lang ... ``` du texte normal
  var parts  = text.split(/(```[\s\S]*?```)/g);
  var result = '';
  for (var i = 0; i < parts.length; i++) {
    var part = parts[i];
    if (part.indexOf('```') === 0) {
      // Bloc de code
      var inner = part.slice(3, -3);           // retirer les ```
      var nl    = inner.indexOf('\n');
      var lang  = '';
      var code  = inner;
      if (nl !== -1) {
        lang = inner.slice(0, nl).trim();
        code = inner.slice(nl + 1);
      }
      var label = lang ? lang.toUpperCase() : 'CODE';
      var id    = 'cb-' + Math.random().toString(36).slice(2, 7);
      result += '<div class="code-block">'
              + '<div class="code-block-header">'
              + '<span>' + esc(label) + '</span>'
              + '<button class="code-exec-btn" onclick="execCode(\'' + id + '\')">EXECUTE</button>'
             
              + '<button class="code-copy-btn" onclick="execCode(\'' + id + '\')">COPIER</button>'
              + '</div>'
              + '<pre id="' + id + '">' + escCode(code) + '</pre>'
              + '</div>';
    } else {
      // Texte normal — préserver les sauts de ligne
      result += '<span>' + esc(part) + '</span>';
    }
  }
  return result;
}

/** Escape HTML pour code — préserve les espaces et indentation */
function escCode(t) {
  return String(t)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');
}
function execCode(id) {
  ;//apiCall();
}
function copyCode(id) {
  var el = document.getElementById(id);
  if (!el) { return; }
  var text = el.textContent || el.innerText;
  var btn  = el.parentElement.querySelector('.code-copy-btn');
  navigator.clipboard.writeText(text).then(function() {
    if (btn) { btn.textContent = 'COPIÉ'; btn.classList.add('copied'); }
    setTimeout(function() {
      if (btn) { btn.textContent = 'COPIER'; btn.classList.remove('copied'); }
    }, 2000);
  }).catch(function() {
    var ta = document.createElement('textarea');
    ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
    document.body.appendChild(ta); ta.select();
    document.execCommand('copy'); document.body.removeChild(ta);
    if (btn) { btn.textContent = 'COPIÉ'; btn.classList.add('copied'); }
    setTimeout(function() {
      if (btn) { btn.textContent = 'COPIER'; btn.classList.remove('copied'); }
    }, 2000);
  });
}
