// =============================================================================
// ui.js — Scroll automatique + Highlight phrase TTS
// index.php / VoxMind V8
// Interface :
//   uiHighlightChunk(text, duration) ← appelé par tts.js au début de chaque chunk
//   uiHighlightStop()                ← appelé par tts.js à la fin de chaque chunk
//   uiScrollBottom()                 ← appelé par api.js pendant le stream
//   uiSetCurrentBubble(el)           ← appelé par api.js au début du stream
//   uiUnlockScroll()                 ← appelé par api.js au début du stream
//   uiResetHighlight()               ← appelé par api.js au début du stream
// =============================================================================
'use strict';

// =============================================================================
// SCROLL
// =============================================================================

var _scrollLocked = false;

function uiInitScroll() {
  var msgs = g('messages');
  if (!msgs) { return; }

  msgs.addEventListener('scroll', function() {
    var atBottom = (msgs.scrollHeight - msgs.scrollTop - msgs.clientHeight) < 80;
    _scrollLocked = !atBottom;
  });

  var _touchStartY = 0;
  msgs.addEventListener('touchstart', function(e) {
    _touchStartY = e.touches[0].clientY;
  }, { passive: true });

  msgs.addEventListener('touchmove', function(e) {
    if (e.touches[0].clientY - _touchStartY > 10) { _scrollLocked = true; }
  }, { passive: true });

  msgs.addEventListener('touchend', function() {
    var atBottom = (msgs.scrollHeight - msgs.scrollTop - msgs.clientHeight) < 80;
    if (atBottom) { _scrollLocked = false; }
  }, { passive: true });
}

function uiScrollBottom() {
  if (_scrollLocked) { return; }
  var msgs = g('messages');
  if (msgs) { msgs.scrollTop = msgs.scrollHeight; }
}

function uiUnlockScroll() { _scrollLocked = false; }

// =============================================================================
// HIGHLIGHT — span positionné via Range sur le DOM réel
//
// Approche simplifiée et robuste :
// - On ne track pas _hlCharsRead / _hlTextOffset entre chunks
// - Pour chaque chunk on cherche la Nème occurrence du mot dans .bt
//   en partant de la position du premier mot du chunk (anchor search)
// - Si le mot n'est pas trouvé → on passe au suivant silencieusement
// =============================================================================

var _currentBubble = null;   // .msg-bubble référencée par api.js
var _hlBubble      = null;
var _hlSpan        = null;
var _hlWords       = [];
var _hlIndex       = 0;
var _hlMsPerWord   = 250;
var _hlTimer       = null;
var _hlAnchorNode  = null;   // TextNode où le 1er mot du chunk a été trouvé
var _hlAnchorOff   = 0;      // offset dans ce TextNode

function uiSetCurrentBubble(el) { _currentBubble = el; }

function uiResetHighlight() {
  uiHighlightStop();
  _hlAnchorNode = null;
  _hlAnchorOff  = 0;
}

/**
 * Lancer le highlight d'un chunk TTS.
 * @param {string} text     — texte brut du chunk (nettoyé par cleanForTTS)
 * @param {number} duration — durée audio en secondes
 */
function uiHighlightChunk(text, duration) {
  uiHighlightStop();
  if (!text || !_currentBubble) { return; }

  var bt = _currentBubble.querySelector('.bt');
  if (!bt) { return; }

  _hlBubble    = _currentBubble;
  _hlWords     = text.trim().split(/\s+/).filter(function(w) { return w.length > 0; });
  _hlIndex     = 0;
  _hlMsPerWord = duration > 0
    ? Math.max(60, Math.min(700, (duration * 1000) / Math.max(_hlWords.length, 1)))
    : 220;

  // Créer le span overlay
  if (_hlSpan && _hlSpan.parentNode) { _hlSpan.parentNode.removeChild(_hlSpan); }
  _hlSpan = document.createElement('span');
  _hlSpan.id = '_hl_word';
  var dark = (document.documentElement.getAttribute('data-theme') || 'dark') !== 'light';
  _hlSpan.style.cssText = [
    'position:absolute',
    'pointer-events:none',
    'border-radius:3px',
    'border:1px solid ' + (dark ? 'rgba(124,58,237,0.9)' : 'rgba(109,40,217,0.7)'),
    'background:'       + (dark ? (config.ttsHighlightColor || 'rgba(124,58,237,0.25)')
                                 : (config.ttsHighlightColorLight || 'rgba(109,40,217,0.15)')),
    'transition:left 0.06s ease, top 0.06s ease, width 0.06s ease',
    'z-index:10',
    'display:none'
  ].join(';');
  bt.style.position = 'relative';
  bt.appendChild(_hlSpan);

  // Trouver l'ancre = position du 1er mot du chunk dans le DOM
  // On cherche à partir de l'ancre précédente (fin du chunk précédent)
  _findChunkAnchor(bt, _hlWords[0]);

  _hlTick();
}

/**
 * Trouver le TextNode et l'offset du premier mot du chunk.
 * Repart de _hlAnchorNode/_hlAnchorOff si disponibles, sinon début du .bt.
 */
function _findChunkAnchor(bt, firstWord) {
  if (!firstWord) { _hlAnchorNode = null; _hlAnchorOff = 0; return; }

  var walker  = document.createTreeWalker(bt, NodeFilter.SHOW_TEXT);
  var node;
  var found   = false;
  var started = (_hlAnchorNode === null); // si pas d'ancre → commencer depuis le début

  while ((node = walker.nextNode())) {
    // Si on a une ancre de chunk précédent, ignorer jusqu'à ce nœud
    if (!started) {
      if (node === _hlAnchorNode) { started = true; }
      else { continue; }
    }

    var content = node.textContent;
    var searchFrom = (node === _hlAnchorNode) ? _hlAnchorOff : 0;
    var idx = _indexOfWordInText(content, firstWord, searchFrom);

    if (idx !== -1) {
      _hlAnchorNode = node;
      _hlAnchorOff  = idx;
      found = true;
      break;
    }
  }

  if (!found) {
    // Fallback : recommencer depuis le début
    _hlAnchorNode = null;
    _hlAnchorOff  = 0;
  }
}

/**
 * Chercher un mot dans un nœud texte en ignorant casse et ponctuation légère.
 * Retourne l'index dans le nœud, ou -1.
 */
function _indexOfWordInText(content, word, from) {
  from = from || 0;
  var clean = word.replace(/[.,;:!?'"»«()\[\]]/g, '').toLowerCase();
  if (!clean) { return -1; }
  var lower = content.toLowerCase();
  var idx   = lower.indexOf(clean, from);
  return idx;
}

/**
 * Tick : positionner le span sur le mot courant.
 */
function _hlTick() {
  if (_hlIndex >= _hlWords.length || !_hlBubble || !_hlSpan) {
    uiHighlightStop();
    return;
  }

  var bt = _hlBubble.querySelector('.bt');
  if (!bt) { uiHighlightStop(); return; }

  _positionSpanOnWord(bt, _hlWords[_hlIndex], _hlIndex);
  _hlIndex++;
  _hlTimer = setTimeout(_hlTick, _hlMsPerWord);
}

/**
 * Placer _hlSpan sur le mot wordIndex dans le chunk courant.
 * Stratégie : partir de _hlAnchorNode/_hlAnchorOff et avancer mot par mot.
 */
function _positionSpanOnWord(bt, word, wordIndex) {
  if (!_hlAnchorNode) { return; }

  // Pour wordIndex=0 l'ancre est déjà sur le bon mot.
  // Pour wordIndex>0 on avance de mot en mot depuis l'ancre.
  var walker   = document.createTreeWalker(bt, NodeFilter.SHOW_TEXT);
  var node;
  var found    = false;
  var started  = false;
  var wordsSkipped = 0;
  // On veut le (wordIndex)ème mot à partir de l'ancre
  var targetSkip = wordIndex; // 0 = le mot de l'ancre lui-même

  // Repositionner le walker sur _hlAnchorNode
  while ((node = walker.nextNode())) {
    if (node === _hlAnchorNode) { started = true; break; }
  }
  if (!started) { return; }

  // Depuis l'ancre, compter les mots
  var searchFrom = _hlAnchorOff;
  var currentNode = _hlAnchorNode;

  while (true) {
    var content = currentNode.textContent;
    // Itérer sur les mots de ce nœud à partir de searchFrom
    var re = /\S+/g;
    re.lastIndex = searchFrom;
    var m;
    while ((m = re.exec(content)) !== null) {
      if (wordsSkipped === targetSkip) {
        // C'est le mot qu'on cherche
        try {
          var range = document.createRange();
          range.setStart(currentNode, m.index);
          range.setEnd(currentNode,   m.index + m[0].length);
          var rect   = range.getBoundingClientRect();
          var btRect = bt.getBoundingClientRect();
          if (rect.width > 0) {
            _hlSpan.style.display = 'block';
            _hlSpan.style.left    = (rect.left - btRect.left - 2) + 'px';
            _hlSpan.style.top     = (rect.top  - btRect.top  - 2) + 'px';
            _hlSpan.style.width   = (rect.width  + 4) + 'px';
            _hlSpan.style.height  = (rect.height + 4) + 'px';
          }
        } catch(_e) {}
        found = true;
        break;
      }
      wordsSkipped++;
    }
    if (found) { break; }

    // Passer au nœud texte suivant
    currentNode = walker.nextNode();
    if (!currentNode) { break; }
    searchFrom = 0;
  }
}

/** Arrêter le highlight et mettre à jour l'ancre pour le chunk suivant. */
function uiHighlightStop() {
  if (_hlTimer) { clearTimeout(_hlTimer); _hlTimer = null; }

  // Avancer l'ancre à la fin du chunk terminé
  if (_hlBubble && _hlWords.length > 0) {
    var bt = _hlBubble.querySelector('.bt');
    if (bt) { _advanceAnchorPastChunk(bt); }
  }

  if (_hlSpan && _hlSpan.parentNode) {
    _hlSpan.parentNode.removeChild(_hlSpan);
    _hlSpan = null;
  }
  _hlBubble = null;
  _hlWords  = [];
  _hlIndex  = 0;
}

/**
 * Après lecture d'un chunk, avancer _hlAnchorNode/_hlAnchorOff
 * pour qu'il pointe après le dernier mot du chunk.
 */
function _advanceAnchorPastChunk(bt) {
  if (!_hlAnchorNode || _hlWords.length === 0) { return; }

  var walker  = document.createTreeWalker(bt, NodeFilter.SHOW_TEXT);
  var node;
  var started = false;

  while ((node = walker.nextNode())) {
    if (node === _hlAnchorNode) { started = true; break; }
  }
  if (!started) { return; }

  var wordsToSkip  = _hlWords.length;
  var wordsSkipped = 0;
  var searchFrom   = _hlAnchorOff;
  var currentNode  = _hlAnchorNode;

  while (wordsSkipped < wordsToSkip) {
    var content = currentNode.textContent;
    var re = /\S+/g;
    re.lastIndex = searchFrom;
    var m;
    var foundInNode = false;
    while ((m = re.exec(content)) !== null) {
      wordsSkipped++;
      if (wordsSkipped === wordsToSkip) {
        // Ancre = juste après ce mot
        _hlAnchorNode = currentNode;
        _hlAnchorOff  = m.index + m[0].length;
        foundInNode = true;
        break;
      }
    }
    if (foundInNode) { break; }
    currentNode = walker.nextNode();
    if (!currentNode) { _hlAnchorNode = null; _hlAnchorOff = 0; break; }
    searchFrom = 0;
  }
}