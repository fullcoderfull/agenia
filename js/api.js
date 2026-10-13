// =============================================================================
// api.js — Couche transport front-end
// Zéro logique métier, zéro info d'archi, zéro regex agent
// Tout transite par backend/api.php
// =============================================================================
'use strict';

var probeAttempt = 0;
var probeTimer   = null;
var llmAbortCtr  = null;

// =============================================================================
// GESTION ERREURS — reçues normalisées depuis api.php
// Format : { error, code, message, block, context }
// =============================================================================

function handleApiError(err) {
  var msg     = err.message || '⚠ Erreur inconnue.';
  var block   = !!err.block;
  var context = err.context || 'llm';

  if (context === 'llm') {
    setDot('llm', block ? 'error' : 'warn');
    appendMsg('assistant', msg);
    if (block) {
      llmReady = false;
      var btn = g('send-btn');
      if (btn) {
        btn.disabled      = true;
        btn.title         = msg;
        btn.className     = 'icon-btn send';
        btn.style.opacity = '0.4';
        btn.style.cursor  = 'not-allowed';
      }
    }
  } else if (context === 'tts') {
    setDot('tts', 'warn');
    console.warn('Voix :', msg);
  } else if (context === 'probe') {
    setDot('llm', block ? 'error' : 'pulse');
    setProbeMsg(msg);
  } else if (context === 'db') {
    setDot('db', block ? 'error' : 'warn');
  }
  return msg;
}

// =============================================================================
// POINT D'ENTRÉE UNIQUE — toutes les requêtes passent ici
// =============================================================================

/**
 * Appel JSON vers backend/api.php
 * @param {string} action
 * @param {Object} body
 * @param {AbortSignal} [signal]
 * @returns {Promise<Object>}
 * @throws {Object} objet erreur normalisé
 */
async function apiCall(action, body, signal) {
  body = {
    action,
    ...(body ?? {})
  };
  var payload = Object.assign({}, body, { action: body.action, urlLLm: document.getElementById('cfg-llm-url').value, providerLLm: document.getElementById('cfg-provider-llm').value });
  var opts = {
    method:      'POST',
    mode:        'same-origin',
    credentials: 'same-origin',
    headers:     { 'Content-Type': 'application/json' },
    body:        JSON.stringify(payload),
  };
  if (signal) { opts.signal = signal; }

  var r;
  try {
    r = await fetch('/backend/api.php', opts);
  } catch (e) {
    if (e.name === 'AbortError') { throw e; }
    throw { code: 0, message: '⚠ Impossible de joindre le serveur.', block: false, context: 'llm' };
  }

  var data;
  try { data = await r.json(); } catch (_e) {
    throw { code: r.status, message: '⚠ Réponse serveur invalide.', block: false, context: 'llm' };
  }

  if (data.error) { throw data; }
  return data;
}

/**
 * Appel streaming vers backend/api.php — retourne la Response brute
 * @param {Object} body
 * @param {AbortSignal} signal
 * @returns {Promise<Response>}
 */
async function apiStream(body, signal) {
  var payload = Object.assign({}, body, { action: 'llm_stream',urlLLm: document.getElementById('cfg-llm-url').value, providerLLm: document.getElementById('cfg-provider-llm').value });
  var r;
  try {
    r = await fetch('/backend/api.php', {
      method:      'POST',
      mode:        'same-origin',
      credentials: 'same-origin',
      headers:     { 'Content-Type': 'application/json' },
      body:        JSON.stringify(payload),
      signal:      signal,
    });
  } catch (e) {
    if (e.name === 'AbortError') { throw e; }
    throw { code: 0, message: '⚠ Impossible de joindre le serveur.', block: false, context: 'llm' };
  }

  if (!r.ok) {
    var errData;
    try { errData = await r.json(); } catch (_e) { errData = null; }
    throw errData || { code: r.status, message: '⚠ Erreur de communication.', block: false, context: 'llm' };
  }
  return r;
}

// =============================================================================
// PROBE DÉMARRAGE
// =============================================================================

function startupProbe() {
  probeAttempt = 0;
  llmReady     = false;
  setDot('llm', 'pulse');
  setDot('tts', 'warn');
  setProbeMsg('⟳ Connexion…');
  requestAnimationFrame(function() { probeOnce(); });
}

async function probeOnce() {
  probeAttempt++;
  try {
    var data = await apiCall('probe', {}, AbortSignal.timeout(8000));
    if (data.ok) {
      llmReady = true;
      setDot('llm', 'ok');
      setProbeMsg(null);
      return;
    }
  } catch (e) {
    if (e.name === 'AbortError' || e.name === 'TimeoutError') { /* normal */ }
    else if (e.error) {
      handleApiError(Object.assign({}, e, { context: 'probe' }));
      return;
    }
  }

  var max   = (typeof config !== 'undefined' && config.probeMaxAttempts) || 10;
  var delay = (typeof config !== 'undefined' && config.probeDelayMs)     || 3000;

  setDot('llm', 'pulse');
  if (probeAttempt < max) {
    setProbeMsg('⟳ Tentative ' + probeAttempt + '/' + max + '…');
    probeTimer = setTimeout(probeOnce, delay);
  } else {
    setDot('llm', 'error');
    setProbeMsg('⚠ Service inaccessible.');
  }
}

function reprobeLLM() {
  if (llmReady) { return; }
  if (probeTimer) { clearTimeout(probeTimer); probeTimer = null; }
  startupProbe();
}

// =============================================================================
// TEST CONNEXIONS (bouton Admin)
// =============================================================================

async function testConnections() {
  showTestResult('⟳ Test en cours…', '');
  var results;
  try {
    var data = await apiCall('test_connections', {});
    results  = data.results || [];
    results.forEach(function(r) {
      if (r.dot) { setDot(r.dot, r.status); }
      if (r.dot === 'llm' && r.status === 'ok') { llmReady = true; }
    });
  } catch (e) {
    results = [{ label: 'API', status: 'error', msg: e.message || 'Erreur' }];
  }

  var el = g('test-result');
  if (!el) { return; }

  var html = results.map(function(r) {
    var color = r.status === 'ok'   ? 'var(--green)'
              : r.status === 'warn' ? 'var(--yellow)'
              : 'var(--red)';
    var icon  = r.status === 'ok' ? '✓' : r.status === 'warn' ? '~' : '✗';
    var title = r.msg ? ' title="' + String(r.msg).replace(/"/g, "'") + '"' : '';
    return '<span' + title + ' style="display:inline-flex;align-items:center;gap:3px;'
      + 'background:' + color + '22;border:1px solid ' + color + ';'
      + 'border-radius:4px;padding:2px 7px;font-family:var(--mono);font-size:10px;'
      + 'color:' + color + ';margin:2px;">'
      + icon + ' ' + r.label + '</span>';
  }).join(' ');

  el.innerHTML = html;
  el.className = 'test-result';
}

// =============================================================================
// APPEL LLM
// =============================================================================

async function callLLM(imageB64) {
  imageB64 = imageB64 || null;
  var maxHist = (config && config.maxHistoryMessages) || 20;
  var payload = {
    messages:     chatHistory.slice(-maxHist),
    stream:       config.streaming,
    systemPrompt: config.systemPrompt || '',
  };
  // Overrides cloud admin — valeurs non sensibles (le back lit les vraies depuis secrets.php)
  if (config.llmUrl)   { payload._llm_url   = document.getElementById('cfg-llm-url').value}
  if (config.llmKey)   { payload._llm_key   = document.getElementById('cfg-llm-key').value }
  if (config.llmModel) { payload._llm_model = document.getElementById('cfg-llm-model').value; }
  payload._llm_provider = document.getElementById('cfg-provider-llm').value;
  if (imageB64)        { payload.image = imageB64; }

  llmAbortCtr = new AbortController();
  return config.streaming
    ? llmStream(payload, llmAbortCtr.signal)
    : llmDirect(payload, llmAbortCtr.signal);
}

async function agentCallLLM(imageB64, text, comment) {
  imageB64 = imageB64 || null;
  chatHistory.push({ role: 'user', content: 'le resultat est ' + text + ' et l\'objectif était : ' + comment });
  var maxHist = (config && config.maxHistoryMessages) || 20;
  var payload = {
    messages:     chatHistory.slice(-maxHist),
    stream:       config.streaming,
    systemPrompt: config.systemPrompt || '',
  };
  if (config.llmUrl)   { payload._llm_url   = config.llmUrl; }
  if (config.llmModel) { payload._llm_model = config.llmModel; }
  if (imageB64)        { payload.image = imageB64; }

  llmAbortCtr = new AbortController();
  return config.streaming
    ? llmStream(payload, llmAbortCtr.signal)
    : llmDirect(payload, llmAbortCtr.signal);
}

// =============================================================================
// LLM DIRECT
// =============================================================================

async function llmDirect(payload, signal) {
  payload.stream = false;
  showTyping();
  var data;
  try {
    data = await apiCall('llm_direct', payload, signal);
  } catch (e) {
    hideTyping();
    if (e.name === 'AbortError') { return ''; }
    handleApiError(Object.assign({ context: 'llm' }, e));
    return '';
  }
  hideTyping();
  var reply = (data.message && data.message.content) ? data.message.content : '';
  reply = reply.replace(/`^tool@.*mode=admin\s/g, '');
  appendMsg('assistant', reply);
 
  ttsSay(reply);
  return reply;
}

// =============================================================================
// LLM STREAM
// Reçoit des tokens NDJSON depuis api.php
// Gère le token agent { agent: true, action, objectif } injecté par le back
// Zéro regex ici — la détection est entièrement côté PHP
// =============================================================================

var isThinking = false;

function showThinking() {
  if (isThinking) { return; }
  isThinking = true;
  var el = g('typing');
  if (el) {
    var dots = el.querySelector('.typing-dots');
    if (dots) {
      dots.innerHTML = '<span style="width:auto;height:auto;background:none;animation:none;'
        + 'border-radius:0;font-family:var(--mono);font-size:11px;'
        + 'color:var(--text-muted);letter-spacing:0.05em;">Patientez, je réfléchis…</span>';
    }
  } else { showTyping(); showThinking(); }
}

function hideThinking() { isThinking = false; hideTyping(); }

async function sleep(ms) { return new Promise(function(r) { setTimeout(r, ms); }); }
var  mode_agent = true;
async function llmStream(payload, signal) {
  payload.stream = true;
  
  showTyping();

  var r;
  try {
    r = await apiStream(payload, signal);
    console.log("après apiStream");
    console.log(r);
  } catch (e) {
    hideTyping();
    if (e.name === 'AbortError') { return ''; }
    handleApiError(Object.assign({ context: 'llm' }, e));
    return '';
  }

  hideTyping();
  isThinking = false;
  resetTtsBuffer();

  var bt     = appendMsg('assistant', '');
  var bubble = bt ? bt.closest('.msg-bubble') : null;
  if (typeof ttsSetDomRef       === 'function') { ttsSetDomRef(bubble); }
  if (typeof uiSetCurrentBubble === 'function') { uiSetCurrentBubble(bubble); }
  if (typeof uiUnlockScroll     === 'function') { uiUnlockScroll(); }
  if (typeof uiResetHighlight   === 'function') { uiResetHighlight(); }

  var reader  = r.body.getReader();
  var dec     = new TextDecoder();
  var full    = '';
  var jsonBuf = '';
  
  // try {
  //   var j;
  //   while (true) {
  //     var chunk = await reader.read();
  //     if (chunk.done) { break; }

  //     //var lines = (jsonBuf + dec.decode(chunk.value, { stream: true })).split('\n');
  //     //jsonBuf = '';
  //     var lines = (jsonBuf + dec.decode(chunk.value, { stream: true })).split('\n');
  //     jsonBuf = lines.pop(); // dernière ligne potentiellement incomplète
      
  //     for (var i = 0; i < lines.length; i++) {
  //       var line = lines[i].trim();
  //       if (!line) { continue; }
       
        
  //       try { j = JSON.parse(line); } catch (_e) {
          
  //         if (line.startsWith('{')) { jsonBuf += line; }

  //         //continue;
  //       }
        

  //       // ── Erreur normalisée injectée par api.php ──────────────────────────
  //        if (typeof j !== 'undefined' && j !== null) 
  //         if (j.error) {
  //           hideThinking();
  //           handleApiError(Object.assign({ context: 'llm' }, j));
  //           return '';
  //         }

  //       // ── Préfixe agent détecté — vider la bulle ───────────────────────────
  //       //
  //       // On efface ce qui s'affiche le temps que le pattern complet arrive

  //       //var  patternOccurenceTerminal = /.*tool@?.*/i;
  //       //full = full.replace(patternOccurenceTerminal, '');

  //       //patternOccurenceTerminal = /tool@chat|**Mode Conversation :** /i;
  //       var pattern = /tool\@chat?.* |\*\*Mode Conversation :\*\*|\*\*Mode Agent :\*\*|tool\@terminal .*?\w+/gi;
  //       //pattern = /?.*tool@.* /gi;
  //       full = full.replace(pattern, '>');
  //      if (typeof j !== 'undefined' && j !== null) {    
  //       if (j.clear === true) {
  //         full = 'ok';
  //         if (bt) { bt.innerHTML = ''; }
  //           continue;
  //       }
        
  //         // ── Tokens normaux ───────────────────────────────────────────────────
  //         var token    = (j.message && j.message.content)  ? j.message.content  : '';
  //         var thinking = (j.message && j.message.thinking) ? j.message.thinking : '';

  //         if (!token && thinking) { showThinking(); continue; }
  //         if (token && isThinking) { hideThinking(); }
  //         if (!token) { continue; }
  //       }
  //       full += token;
  //       bt.innerHTML = esc(full);
  //       uiScrollBottom();
  //       feedTtsToken(token);
  //       mode_agent = true;
  //     }
  //   }
  // } catch (e) {
  //   if (e.name !== 'AbortError') { throw e; }
  // }

try {

    var j = null;

    while (true) {

        var chunk = await reader.read();

        if (chunk.done) {
            break;
        }

        var decoded = dec.decode(chunk.value, { stream: true });

        var lines = (jsonBuf + decoded).split('\n');

        // Dernière ligne potentiellement incomplète
        jsonBuf = lines.pop() || '';

        for (var i = 0; i < lines.length; i++) {

            var line = lines[i].trim();

            if (!line) {
                continue;
            }

            // IMPORTANT :
            // on réinitialise j à chaque nouvelle ligne.
            // Sinon, si JSON.parse() échoue, j contient
            // encore le JSON précédent et le token précédent
            // est réinjecté.
            j = null;

            try {

                j = JSON.parse(line);

            } catch (_e) {

                // La ligne n'est pas encore un JSON complet.
                // On la remet dans le buffer UNE SEULE FOIS.
                jsonBuf = line + '\n' + jsonBuf;

                continue;
            }

            // ── Erreur normalisée injectée par api.php ────────────────

            if (j && j.error) {

                hideThinking();

                handleApiError(
                    Object.assign(
                        { context: 'llm' },
                        j
                    )
                );

                return '';
            }

            // ── Préfixe agent détecté ─────────────────────────────────

            var pattern =
                /tool@chat.*|\*\*Mode Conversation :\*\*|\*\*Mode Agent :\*\*|tool@terminal .*?\w+/gi;

            full = full.replace(pattern, '>');

            // ── Clear ─────────────────────────────────────────────────

            if (j.clear === true) {

                full = 'ok';

                if (bt) {
                    bt.innerHTML = '';
                }

                continue;
            }

            // ── Tokens normaux ────────────────────────────────────────

            var token =
                (j.message && j.message.content)
                    ? j.message.content
                    : '';

            var thinking =
                (j.message && j.message.thinking)
                    ? j.message.thinking
                    : '';

            if (!token && thinking) {

                showThinking();

                continue;
            }

            if (token && isThinking) {
                hideThinking();
            }

            if (!token) {
                continue;
            }

            // ── Ajout du token UNE SEULE FOIS ─────────────────────────

            full += token;

            bt.innerHTML = esc(full);

            uiScrollBottom();

            feedTtsToken(token);

            mode_agent = true;
        }
    }

} catch (e) {

    if (e.name !== 'AbortError') {
        throw e;
    }
}

  flushTtsBuffer();

  if (full && !j.clear) {
    try {
      var rendered = renderMsg(full);
      if (rendered) { bt.innerHTML = rendered; }
    } catch (_e) { bt.innerHTML = esc(full); }
  }




        // ── Token agent — injecté par api.php après détection côté PHP ──────
        // Le front ne connaît pas le pattern, juste ce token structuré
       if (typeof j !== 'undefined' && j !== null) 
        if (j.agent === true) { 
          j.agent = false;
          var agentAction   = j.action   || '';
          var agentObjectif = j.objectif || '';

          if (agentAction && mode_agent) {
            // Déléguer l'exécution au back
            apiCall('agent_run', { action: agentAction, objectif: agentObjectif })
              .then(function(res) {
                var output = (res.output && res.output !== '')
                  ? res.output
                  : (voxLang === 'fr' ? 'Action terminée sans message.' : 'Action completed with no output.');

                if (output.toLowerCase().includes('erreur') || output.toLowerCase().includes('error')) {
                  output = voxLang === 'fr' ? 'Une erreur s\'est produite.' : 'An error occurred.';
                }

                var resultText = '```' + output + '```';
                if(output == "") output="pas de resultat trouvé"
                if(res.output!=""){
                  agentSendAutoMessage("action completed : " + res.output + " conclusion, le resultat à été trouvé", agentObjectif);
                 
                  
                }
                else {
                
                  //agentSendAutoMessage("Pas de resultat pour cette requete essaye autre chose si cela n'est pas acceptable", agentObjectif);
                   //mode_agent = false;
                }
              })
              .catch(function(err) {
                appendMsg('assistant', err.message || (voxLang === 'fr' ? 'Erreur agent.' : 'Agent error.'));
              });
          }

          // Afficher un message neutre à l'utilisateur (pas le pattern brut)
          //full = voxLang === 'fr'
            //? '…'///le mode agent ne parle pas, agent en cours.
            //: '…';
          bt.innerHTML = esc(full);
          uiScrollBottom();
          //continue;
        }


  return full;
}



// =============================================================================
// AGENT AUTO-MESSAGE
// =============================================================================

async function agentSendAutoMessage(text, comment) {
  var input = g('user-input');
  //const occurrence = input.indexOf("text");
  if (!text) { return; }
  input.value = '';
  autoResize(input);
  isLoading = true;
  updateStopBtn();
  setDot('llm', 'pulse');
  ttsUnlock();

  var imageB64 = null;
  var thumbUrl = null;
  if (config.visionMode === 'auto' && visionStream && currentDelta >= config.visionThreshold) {
    imageB64 = captureFrame();
    if (imageB64) { thumbUrl = 'data:image/jpeg;base64,' + imageB64; flashPreview(); }
  }

  var uploadPayload = (typeof getUploadPayload === 'function') ? getUploadPayload() : null;
  var userText = text;
  if (uploadPayload) {
    if (uploadPayload.type === 'image') {
      imageB64 = uploadPayload.content;
    } else {
      userText = text + '\n\n[Fichier : ' + uploadPayload.name + ']\n' + uploadPayload.content;
    }
    if (typeof clearUpload === 'function') { clearUpload(); }
  }

  appendMsg('user', text, thumbUrl);
  chatHistory.push({ role: 'user', content: userText });
  
  let trouvé = false;

  chatHistory.forEach(message => {
      if (message.content === text) {
          trouvé = true;
      }
  });

  // if (trouvé) {
  //     console.log("Le message a été trouvé dans l'historique de conversation.");
  //     isLoading = false;
  //     updateStopBtn();
  //     return;
  // } 

  try {
    var reply = await agentCallLLM(imageB64, text, comment);
    const patternOccurenceTerminal = /^```tool.*/gi;
    text = text.replace(patternOccurenceTerminal, '');


    if (reply) { chatHistory.push({ role: 'agent', content: reply }); }
  } catch (e) {
    if (e.name !== 'AbortError') {
      appendMsg('assistant', voxLang === 'fr' ? '⚠ Erreur agent.' : '⚠ Agent error.');
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
// STOP LLM
// =============================================================================

function llmStop() {
  if (llmAbortCtr) { llmAbortCtr.abort(); llmAbortCtr = null; }
}