// =============================================================================
// upload.js — Dépôt fichier + extraction texte côté client
// PDF via pdf.js (CDN), DOCX via mammoth.js (CDN), TXT direct, Image base64
// =============================================================================
'use strict';

var uploadedFile     = null;  // fichier en attente
var uploadedContent  = null;  // texte extrait ou base64
var uploadedType     = null;  // 'text' | 'image'
var uploadedName     = '';

// =============================================================================
// INIT — créer le bouton et l'input file
// =============================================================================

function initUpload() {
  
  // Input file — addEventListener propre, pas d'onclick inline
  var input = g('file-input');
  if (input) {
    input.addEventListener('change', function() {
      var file = this.files[0];
      if (!file) { return; }
      handleFile(file);
      this.value = '';  // reset pour permettre re-sélection du même fichier
      // Refocus input texte sur mobile
      setTimeout(function() {
        var ui = g('user-input');
        if (ui) { ui.focus(); }
      }, 300);
    });
  }

  // Drag & drop
  var msgs = g('messages');
  if (!msgs) { return; }
  msgs.addEventListener('dragover', function(e) { e.preventDefault(); msgs.classList.add('dragover'); });
  msgs.addEventListener('dragleave', function() { msgs.classList.remove('dragover'); });
  msgs.addEventListener('drop', function(e) {
    e.preventDefault();
    msgs.classList.remove('dragover');
    var file = e.dataTransfer.files[0];
    if (file) { handleFile(file); }
  });
}

// =============================================================================
// VALIDATION
// =============================================================================

function validateFile(file) {
  if (!config.uploadEnabled) { showUploadError('Upload désactivé'); return false; }

  var maxMb = (config.uploadMaxSizeMb || 20) * 1024 * 1024;
  if (file.size > maxMb) {
    showUploadError('Fichier trop volumineux (max ' + (config.uploadMaxSizeMb || 20) + ' Mo)');
    return false;
  }

  // Détection par extension ET type MIME
  var name = file.name.toLowerCase();
  var mime = file.type || '';
  var ok   = mime.startsWith('image/')
          || mime === 'application/pdf'
          || mime === 'text/plain'
          || mime === 'text/markdown'
          || mime === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
          || name.endsWith('.pdf')
          || name.endsWith('.txt')
          || name.endsWith('.md')
          || name.endsWith('.docx');

  if (!ok) {
    showUploadError('Type non supporté — image, PDF, TXT, DOCX uniquement');
    return false;
  }
  return true;
}

// =============================================================================
// DISPATCH selon type
// =============================================================================

function handleFile(file) {
  if (!validateFile(file)) { return; }
  uploadedFile = file;
  uploadedName = file.name;
  showUploadPending(file.name);

  if (file.type.startsWith('image/')) {
    extractImage(file);
  } else if (file.type === 'application/pdf') {
    extractPdf(file);
  } else if (file.type === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') {
    extractDocx(file);
  } else {
    // text/plain, text/markdown, etc.
    extractText(file);
  }
}

// =============================================================================
// EXTRACTEURS
// =============================================================================

function extractImage(file) {
  var reader = new FileReader();
  reader.onload = function(e) {
    var b64 = e.target.result.split(',')[1];
    uploadedContent = b64;
    uploadedType    = 'image';
    showUploadReady(file.name, 'image');
  };
  reader.readAsDataURL(file);
}

function extractText(file) {
  var reader = new FileReader();
  reader.onload = function(e) {
    var text = e.target.result;
    uploadedContent = truncateTokens(text);
    uploadedType    = 'text';
    showUploadReady(file.name, 'text', uploadedContent.length);
  };
  reader.readAsText(file, 'UTF-8');
}

function extractDocx(file) {
  // mammoth.js chargé depuis CDN dans index.php
  if (typeof mammoth === 'undefined') {
    showUploadError('Librairie DOCX non disponible');
    return;
  }
  var reader = new FileReader();
  reader.onload = function(e) {
    mammoth.extractRawText({ arrayBuffer: e.target.result })
      .then(function(result) {
        var text = result.value;
        uploadedContent = truncateTokens(text);
        uploadedType    = 'text';
        showUploadReady(file.name, 'docx', uploadedContent.length);
      })
      .catch(function(err) {
        showUploadError('Erreur lecture DOCX : ' + err.message);
      });
  };
  reader.readAsArrayBuffer(file);
}

function extractPdf(file) {
  // pdf.js chargé depuis CDN dans index.php
  if (typeof pdfjsLib === 'undefined') {
    showUploadError('Librairie PDF non disponible');
    return;
  }
  var reader = new FileReader();
  reader.onload = function(e) {
    var typedArr = new Uint8Array(e.target.result);
    pdfjsLib.getDocument({ data: typedArr }).promise.then(function(pdf) {
      var maxPages = config.uploadMaxPages || 2;
      var pages    = Math.min(pdf.numPages, maxPages);
      var texts    = [];
      var promises = [];

      for (var p = 1; p <= pages; p++) {
        promises.push(
          pdf.getPage(p).then(function(page) {
            return page.getTextContent().then(function(tc) {
              return tc.items.map(function(i) { return i.str; }).join(' ');
            });
          })
        );
      }

      Promise.all(promises).then(function(results) {
        var text = results.join('\n\n');
        uploadedContent = truncateTokens(text);
        uploadedType    = 'text';
        showUploadReady(file.name, 'pdf', uploadedContent.length);
      });
    }).catch(function(err) {
      showUploadError('Erreur lecture PDF : ' + err.message);
    });
  };
  reader.readAsArrayBuffer(file);
}

// =============================================================================
// TRONCATURE — ~10000 tokens ≈ 40000 chars (4 chars/token en moyenne)
// =============================================================================

function truncateTokens(text) {
  var maxChars = (config.uploadMaxTokens || 10000) * 4;
  if (text.length <= maxChars) { return text; }
  return text.slice(0, maxChars) + '\n\n[... contenu tronqué à ' + (config.uploadMaxTokens || 10000) + ' tokens]';
}

// =============================================================================
// INJECTION DANS LE MESSAGE
// =============================================================================

/**
 * Appelé depuis sendMessage() — injecte le fichier dans le payload LLM
 * @returns {object|null} {type, content, name} ou null
 */
function getUploadPayload() {
  if (!uploadedContent || !uploadedType) { return null; }
  return { type: uploadedType, content: uploadedContent, name: uploadedName };
}

function clearUpload() {
  uploadedFile    = null;
  uploadedContent = null;
  uploadedType    = null;
  uploadedName    = '';
  // Retirer le point vert du bouton
  var btn = g('upload-btn');
  if (btn) {
    var dot = btn.querySelector('.upload-dot');
    if (dot) { dot.remove(); }
    btn.title = 'Joindre un fichier';
    btn.removeEventListener('click', showUploadInfo);
  }
  var badge = g('upload-badge');
  if (badge) { badge.style.display = 'none'; }
}

// =============================================================================
// UI — badges et erreurs
// =============================================================================

function showUploadPending(name) {
  var badge = g('upload-badge');
  if (!badge) { return; }
  badge.style.display = 'flex';
  badge.className     = 'upload-badge loading';
  badge.innerHTML     = '<span class="upload-spin">⟳</span> ' + esc(name.slice(0, 30));
}

function showUploadReady(name, type, chars) {
  
  // Badge masqué — indicateur sur le bouton upload uniquement
  var badge = g('upload-badge');
  if (badge) { badge.style.display = 'none'; }

  // Point vert sur le bouton upload
  var btn = g('upload-btn');
  if (btn) {
    btn.style.position = 'relative';
    btn.title = name + (chars ? ' (~' + Math.round(chars/4) + ' tokens)' : '');
    // Ajouter indicateur si pas déjà présent
    if (!btn.querySelector('.upload-dot')) {
      var dot = document.createElement('span');
      dot.className = 'upload-dot';
      dot.style.cssText = 'position:absolute;top:2px;right:2px;width:8px;height:8px;background:var(--green);border-radius:50%;border:1px solid var(--bg);';
      btn.appendChild(dot);
    }
    // Icône ℹ — tap pour voir le nom
    btn.addEventListener('click', showUploadInfo, { once: true });
  }
  uploadedName = name;
}

var _uploadInfoVisible = false;
function showUploadInfo() {
  if (!uploadedContent) { return; }
  // Afficher le nom brièvement en tooltip
  var btn = g('upload-btn');
  if (!btn) { return; }
  var tip = document.createElement('div');
  tip.style.cssText = 'position:absolute;bottom:calc(100% + 8px);left:50%;transform:translateX(-50%);background:var(--surface2);border:1px solid var(--accent2);border-radius:var(--radius);padding:6px 10px;font-family:var(--mono);font-size:10px;color:var(--accent2);white-space:nowrap;z-index:300;';
  tip.textContent = uploadedName + ' — ✕ Retirer';
  tip.onclick = function() { clearUpload(); tip.remove(); };
  btn.style.position = 'relative';
  btn.appendChild(tip);
  setTimeout(function() { if (tip.parentNode) { tip.remove(); } }, 3000);
}

function showUploadError(msg) {
  var badge = g('upload-badge');
  if (!badge) { return; }
  badge.style.display = 'flex';
  badge.className     = 'upload-badge error';
  badge.innerHTML     = '⚠ ' + esc(msg) + ' <button id="upload-err-close">✕</button>';
  var closeBtn = badge.querySelector('#upload-err-close');
  if (closeBtn) { closeBtn.addEventListener('click', clearUpload); }
  setTimeout(clearUpload, 4000);
}

function esc(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}