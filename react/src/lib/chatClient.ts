// Moteur de chat : parle au backend PHP (backend/api.php action=llm_stream),
// qui renvoie un flux NDJSON. Format (vu dans l'ancien js/api.js) :
//   {"message":{"content":"..."}}   token normal
//   {"message":{"thinking":"..."}}  réflexion (ignorée à l'affichage)
//   {"clear":true}                  vider la bulle (préfixe agent détecté)
//   {"agent":true,"action":"...","objectif":"..."}  l'agent propose une action
//   {"done":true}                   fin
//   {"error":true,"message":"..."}  erreur normalisée

const PHP_ORIGIN = (process.env.NEXT_PUBLIC_PHP_ORIGIN ?? '').replace(/\/+$/, '');
const API = `${PHP_ORIGIN}/backend/api.php`;

export interface ChatMessage {
  role: 'user' | 'assistant';
  content: string;
}

// Pièce jointe image : data URL base64 (ex. "data:image/png;base64,....").
// L'ancien backend (proxy-llm.php) lit payload.image et bascule sur le modèle
// vision. On transmet donc les images au llm_stream, qui les relaie.
export interface ChatImage {
  name: string;
  dataUrl: string; // data:<mime>;base64,<...>
}

// Bilan d'un appel, mesuré par le serveur (api.php) et inscrit au journal d'audit.
export interface ServerAudit {
  trace: string;
  status: number;
  local: boolean | null;
  durationMs: number | null;
  ttftMs: number | null;
  tokensIn: number | null;
  tokensOut: number | null;
}

export type StreamEvent =
  | { kind: 'trace'; id: string }
  | { kind: 'audit'; audit: ServerAudit }
  | { kind: 'token'; text: string }
  | { kind: 'clear' }
  | { kind: 'agent'; action: string; objectif: string }
  | { kind: 'done' }
  | { kind: 'error'; message: string };

// Parseur pur et testable : consomme un morceau de texte + le reste précédent,
// renvoie les événements complets et le reste non terminé.
export function parseChunk(buffer: string, leftover: string): { events: StreamEvent[]; leftover: string } {
  const text = leftover + buffer;
  const lines = text.split('\n');
  const rest = lines.pop() ?? '';
  const events: StreamEvent[] = [];

  for (const raw of lines) {
    const line = raw.trim();
    if (!line) continue;
    let j: Record<string, unknown>;
    try {
      j = JSON.parse(line) as Record<string, unknown>;
    } catch {
      continue; // ligne JSON incomplète ou bruit : ignorée
    }
    if (typeof j.trace === 'string' && Object.keys(j).length === 1) {
      events.push({ kind: 'trace', id: j.trace });
      continue;
    }
    if (j.audit && typeof j.audit === 'object') {
      const a = j.audit as Record<string, unknown>;
      const num = (v: unknown) => (typeof v === 'number' && Number.isFinite(v) ? v : null);
      events.push({
        kind: 'audit',
        audit: {
          trace: typeof a.trace === 'string' ? a.trace : '',
          status: num(a.status) ?? 0,
          local: typeof a.local === 'boolean' ? a.local : null,
          durationMs: num(a.duration_ms),
          ttftMs: num(a.ttft_ms),
          tokensIn: num(a.tokens_in),
          tokensOut: num(a.tokens_out),
        },
      });
      continue;
    }
    if (j.error) {
      events.push({ kind: 'error', message: typeof j.message === 'string' ? j.message : 'Erreur' });
      continue;
    }
    if (j.clear === true) {
      events.push({ kind: 'clear' });
      continue;
    }
    if (j.agent === true) {
      events.push({
        kind: 'agent',
        action: typeof j.action === 'string' ? j.action : '',
        objectif: typeof j.objectif === 'string' ? j.objectif : '',
      });
      continue;
    }
    const msg = j.message as { content?: unknown } | undefined;
    const content = msg && typeof msg.content === 'string' ? msg.content : '';
    if (content) events.push({ kind: 'token', text: content });
    if (j.done === true) events.push({ kind: 'done' });
  }

  return { events, leftover: rest };
}

export interface StreamHandlers {
  onTrace?: (id: string) => void;
  onAudit?: (audit: ServerAudit) => void;
  onToken: (text: string) => void;
  onClear: () => void;
  onAgent: (action: string, objectif: string) => void;
  onError: (message: string) => void;
}

// Lance une requête de chat et dispatche les événements. Renvoie quand le flux est fini.
// `images` : pièces jointes du dernier message utilisateur (data URLs base64).
export async function streamChat(
  messages: ChatMessage[],
  handlers: StreamHandlers,
  signal: AbortSignal,
  images?: ChatImage[],
): Promise<void> {
  // Base64 brut (sans préfixe data:) pour le champ legacy `image`/`images`
  // attendu par proxy-llm.php, + conservation des data URLs complètes.
  const dataUrls = (images ?? []).map((im) => im.dataUrl);
  const rawBase64 = dataUrls.map((u) => u.replace(/^data:[^;]+;base64,/, ''));
  const body: Record<string, unknown> = { action: 'llm_stream', messages, stream: true };
  if (rawBase64.length) {
    body.image = rawBase64[0]; // compat ancien backend (une image)
    body.images = rawBase64; // plusieurs images éventuelles
  }

  let r: Response;
  try {
    r = await fetch(API, {
      method: 'POST',
      mode: 'same-origin',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
      signal,
    });
  } catch (e) {
    if ((e as { name?: string }).name === 'AbortError') return;
    handlers.onError('network');
    return;
  }

  if (r.status === 401 || r.status === 403) {
    handlers.onError('unauth');
    return;
  }
  if (!r.ok || !r.body) {
    handlers.onError('server');
    return;
  }

  const reader = r.body.getReader();
  const decoder = new TextDecoder();
  let leftover = '';

  try {
    for (;;) {
      const { done, value } = await reader.read();
      if (done) break;
      const { events, leftover: rest } = parseChunk(decoder.decode(value, { stream: true }), leftover);
      leftover = rest;
      for (const ev of events) {
        if (ev.kind === 'trace') handlers.onTrace?.(ev.id);
        else if (ev.kind === 'audit') handlers.onAudit?.(ev.audit);
        else if (ev.kind === 'token') handlers.onToken(ev.text);
        else if (ev.kind === 'clear') handlers.onClear();
        else if (ev.kind === 'agent') handlers.onAgent(ev.action, ev.objectif);
        else if (ev.kind === 'error') handlers.onError(ev.message);
      }
    }
  } catch (e) {
    if ((e as { name?: string }).name !== 'AbortError') handlers.onError('stream');
  }
}

// Exécute une action d'agent SEULEMENT après accord explicite de l'utilisateur.
// (L'ancien client l'exécutait automatiquement — ici, jamais sans ce clic.)
// `trace` relie l'exécution à la proposition de l'agent dans le journal d'audit.
export async function runAgentAction(action: string, objectif: string, signal: AbortSignal, trace?: string): Promise<string> {
  const r = await fetch(API, {
    method: 'POST',
    mode: 'same-origin',
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json' },
    // Le back route sur le 'default' du switch (réservé admin) puis lit action+objectif.
    body: JSON.stringify(trace ? { action, objectif, trace } : { action, objectif }),
    signal,
  });
  if (!r.ok) throw new Error('agent');
  const data = (await r.json()) as { output?: string };
  return data.output ?? '';
}

// Enregistre un REFUS de l'utilisateur dans le journal d'audit (un accord est
// enregistré par le serveur au moment de l'exécution). Ne bloque jamais l'interface.
export async function recordAgentDecision(trace: string, command: string, decision: 'deny'): Promise<void> {
  try {
    await fetch(API, {
      method: 'POST',
      mode: 'same-origin',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'agent_decision', decision, trace, command }),
    });
  } catch {
    /* le refus reste effectif côté interface */
  }
}
