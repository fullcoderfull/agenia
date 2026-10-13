// Voix de l'assistant — reprend le comportement de la version précédente
// (bloc "audio" de config/settings.json) :
//   - la lecture démarre PENDANT le stream, phrase par phrase (audio.streaming) ;
//   - découpage sur sentenceMarkers, coupe sur pauseMarkers au-delà de pauseMinWords ;
//   - les morceaux sont lus dans l'ordre, sans chevauchement (file d'attente) ;
//   - les blocs de code ne sont jamais lus.
// Un seul moteur : proxy-tts.php, avec le contrat de l'ancienne version (js/tts.js) :
//   POST JSON { input, lang, format }
//   lang  = identifiant de voix choisi (fr = Célia, en = Alex — sélecteur cfg-tts-voice-ui)
//   input = texte, précédé de tts.instructPrompt si tts.instruct est activé
// La voix réelle et l'URL restent côté serveur (config/settings.json).
// Aucune voix de navigateur.

const PHP_ORIGIN = (process.env.NEXT_PUBLIC_PHP_ORIGIN ?? '').replace(/\/+$/, '');
const TTS = `${PHP_ORIGIN}/proxy-tts.php`;

export interface AudioCfg {
  enabled: boolean;
  streaming: boolean;
  firstChunkWords: number;
  sentenceMarkers: string[];
  pauseMarkers: string[];
  pauseMinWords: number;
  ttsTimeoutMs: number;
  // bloc "tts" de settings.json
  format: string;
  instruct: boolean;
  instructPrompt: string;
}

// Valeurs de config/settings.json (bloc "audio") — utilisées si le serveur ne les transmet pas.
export const DEFAULT_AUDIO: AudioCfg = {
  enabled: true,
  streaming: true,
  firstChunkWords: 120,
  sentenceMarkers: ['.', '!', '?', '…'],
  pauseMarkers: [',', ';', ':'],
  pauseMinWords: 50,
  ttsTimeoutMs: 8000,
  format: 'mp3',
  instruct: false,
  instructPrompt: '',
};

// raw = réponse de config_load. Accepte les blocs de settings.json ("audio", "tts")
// comme les clés à plat de l'ancien front (ttsFormat, ttsInstruct, ttsInstructPrompt).
export function mergeAudio(raw: unknown): AudioCfg {
  const root = raw && typeof raw === 'object' ? (raw as Record<string, unknown>) : {};
  const a = (root.audio && typeof root.audio === 'object' ? root.audio : root) as Partial<AudioCfg>;
  const t = (root.tts && typeof root.tts === 'object' ? root.tts : {}) as Record<string, unknown>;
  const str = (...v: unknown[]) => (v.find((x) => typeof x === 'string' && x) as string | undefined) ?? '';
  const bool = (...v: unknown[]) => v.find((x) => typeof x === 'boolean') as boolean | undefined;
  return {
    enabled: typeof a.enabled === 'boolean' ? a.enabled : DEFAULT_AUDIO.enabled,
    streaming: typeof a.streaming === 'boolean' ? a.streaming : DEFAULT_AUDIO.streaming,
    firstChunkWords: Number(a.firstChunkWords) || DEFAULT_AUDIO.firstChunkWords,
    sentenceMarkers: Array.isArray(a.sentenceMarkers) && a.sentenceMarkers.length ? a.sentenceMarkers : DEFAULT_AUDIO.sentenceMarkers,
    pauseMarkers: Array.isArray(a.pauseMarkers) && a.pauseMarkers.length ? a.pauseMarkers : DEFAULT_AUDIO.pauseMarkers,
    pauseMinWords: Number(a.pauseMinWords) || DEFAULT_AUDIO.pauseMinWords,
    ttsTimeoutMs: Number(a.ttsTimeoutMs) || DEFAULT_AUDIO.ttsTimeoutMs,
    format: str(t.format, root.ttsFormat) || DEFAULT_AUDIO.format,
    instruct: bool(t.instruct, root.ttsInstruct) ?? DEFAULT_AUDIO.instruct,
    instructPrompt: str(t.instructPrompt, root.ttsInstructPrompt),
  };
}

// Texte « lisible » : sans markdown ni URL.
export function toSpeakable(s: string): string {
  return s
    .replace(/<think>[\s\S]*?<\/think>/g, '')
    .replace(/tool@chat\(conversation\)/g, '')
    .replace(/(\d)\.(\d)/g, '$1 point $2')
    .replace(/[\u{1F300}-\u{1FAFF}]/gu, '')
    .replace(/ë/g, 'é')
    .replace(/Ë/g, 'É')
    .replace(/`([^`]*)`/g, '$1')
    .replace(/\[([^\]]+)\]\([^)]+\)/g, '$1')
    .replace(/https?:\/\/\S+/g, ' ')
    .replace(/^\s*#{1,6}\s+/gm, '')
    .replace(/^\s*[-*•]\s+/gm, '')
    .replace(/[*_~>#]/g, '')
    .replace(/\s+/g, ' ')
    .trim();
}

const wordCount = (s: string) => (s.trim() ? s.trim().split(/\s+/).length : 0);

// Requête audio pour un morceau : le son, ou la raison précise de l'échec
// (affichée dans le test de la voix pour diagnostiquer sans script).
async function fetchAudio(text: string, voice: string, cfg: AudioCfg, outer: AbortSignal): Promise<Blob | string> {
  const timeoutMs = cfg.ttsTimeoutMs;
  const ctrl = new AbortController();
  const onAbort = () => ctrl.abort();
  outer.addEventListener('abort', onAbort);
  let timedOut = false;
  const timer = setTimeout(() => {
    timedOut = true;
    ctrl.abort();
  }, timeoutMs);
  try {
    const r = await fetch(TTS, {
      method: 'POST',
      mode: 'same-origin',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        input: cfg.instruct && cfg.instructPrompt ? `${cfg.instructPrompt} ${text}` : text,
        lang: voice,
        format: cfg.format,
      }),
      signal: ctrl.signal,
    });
    const blob = await r.blob();
    if (r.ok && blob.size && blob.type.startsWith('audio')) return blob;
    const body = blob.size < 4000 ? (await blob.text()).replace(/\s+/g, ' ').trim().slice(0, 160) : '';
    if (!r.ok) return `HTTP ${r.status}${body ? ` — ${body}` : ''}`;
    if (!blob.size) return 'empty';
    return `type ${blob.type || '?'}${body ? ` — ${body}` : ''}`;
  } catch {
    // codes traduits par l'interface : 'timeout' | 'network'
    return timedOut ? 'timeout' : 'network';
  } finally {
    clearTimeout(timer);
    outer.removeEventListener('abort', onAbort);
  }
}

export interface Chunk {
  start: number; // position dans le texte brut du message (pour le surlignage)
  end: number;
  text: string; // texte envoyé à la voix
}

export interface QueueEvents {
  onChunk?: (c: Chunk) => void; // un morceau commence à être lu
  onDone?: (ok: boolean, error?: string) => void; // ok=false : aucun son ; error = dernière raison
}

// File de lecture : on lui donne le texte au fil du stream, elle découpe et lit dans l'ordre.
export class SpeechQueue {
  private consumed = 0; // index dans le texte brut déjà découpé
  private items: { chunk: Chunk; blob?: Promise<Blob | string> }[] = [];
  private lastError = '';
  private playing = false;
  private finished = false;
  private stopped = false;
  private played = 0;
  private audio: HTMLAudioElement | null = null;
  private url = '';
  private ctrl = new AbortController();

  constructor(
    private voice: string, // identifiant de voix : 'fr' (Célia) ou 'en' (Alex)
    private cfg: AudioCfg,
    private ev: QueueEvents = {},
  ) {}

  // Texte complet du message à cet instant (il ne fait que grandir pendant le stream).
  update(full: string): void {
    if (this.stopped) return;
    this.cut(full, false);
  }

  // Fin du stream : on lit ce qui reste.
  finish(full: string): void {
    if (this.stopped) return;
    this.cut(full, true);
    this.finished = true;
    this.pump();
  }

  private paused = false;

  isPaused(): boolean {
    return this.paused;
  }

  // Pause / reprise de la lecture en cours (bouton ⏸ de l'ancienne version).
  togglePause(): boolean {
    this.paused = !this.paused;
    if (this.audio) {
      if (this.paused) this.audio.pause();
      else this.audio.play().catch(() => undefined);
    }
    return this.paused;
  }

  stop(): void {
    this.stopped = true;
    this.ctrl.abort();
    if (this.audio) this.audio.pause();
    if (this.url) URL.revokeObjectURL(this.url);
    this.audio = null;
    this.url = '';
    this.items = [];
  }

  private cut(full: string, final: boolean): void {
    for (;;) {
      const rest = full.slice(this.consumed);
      if (!rest) return;

      // Bloc de code : jamais lu. On attend sa fermeture puis on le saute.
      const fence = rest.indexOf('```');
      const zoneEnd = fence === -1 ? rest.length : fence;
      if (zoneEnd === 0) {
        const close = rest.indexOf('```', 3);
        if (close === -1) {
          if (final) this.consumed = full.length;
          return;
        }
        this.consumed += close + 3;
        continue;
      }

      const zone = rest.slice(0, zoneEnd);
      const cutAt = this.findCut(zone, final || fence !== -1);
      if (cutAt <= 0) return;

      const raw = zone.slice(0, cutAt);
      const start = this.consumed;
      this.consumed += cutAt;
      const text = toSpeakable(raw);
      if (text) this.enqueue({ start, end: start + cutAt, text });
    }
  }

  // Position de coupe dans la zone (0 = attendre plus de texte).
  private findCut(zone: string, zoneIsComplete: boolean): number {
    const first = this.items.length === 0 && this.played === 0;
    const { sentenceMarkers, pauseMarkers, pauseMinWords, firstChunkWords } = this.cfg;

    // 1) fin de phrase suivie d'un espace / retour ligne
    for (let i = 0; i < zone.length; i++) {
      const ch = zone[i] ?? '';
      if (sentenceMarkers.includes(ch)) {
        const next = zone[i + 1];
        if (next === undefined ? zoneIsComplete : /\s/.test(next)) return i + 1;
      }
      if (ch === '\n' && zone[i + 1] === '\n') return i + 2; // fin de paragraphe
    }
    // 2) phrase trop longue : coupe sur une pause
    const limit = first ? Math.min(firstChunkWords, pauseMinWords) : pauseMinWords;
    if (wordCount(zone) >= limit) {
      for (let i = zone.length - 1; i > 0; i--) {
        if (pauseMarkers.includes(zone[i] ?? '') && /\s/.test(zone[i + 1] ?? ' ')) return i + 1;
      }
      const sp = zone.lastIndexOf(' ');
      if (sp > 0) return sp + 1;
    }
    // 3) fin du texte
    return zoneIsComplete ? zone.length : 0;
  }

  private enqueue(chunk: Chunk): void {
    this.items.push({ chunk });
    this.pump();
  }

  private prefetch(i: number): void {
    const it = this.items[i];
    if (it && !it.blob) it.blob = fetchAudio(it.chunk.text, this.voice, this.cfg, this.ctrl.signal);
  }

  private async pump(): Promise<void> {
    if (this.playing || this.stopped) return;
    this.playing = true;
    while (!this.stopped && this.items.length) {
      this.prefetch(0);
      this.prefetch(1); // le morceau suivant se prépare pendant la lecture
      const it = this.items[0]!;
      const blob = await it.blob!;
      if (this.stopped) break;
      this.items.shift();
      if (typeof blob === 'string') {
        this.lastError = blob;
        continue;
      }
      while (this.paused && !this.stopped) await new Promise((r) => setTimeout(r, 120));
      if (this.stopped) break;
      this.ev.onChunk?.(it.chunk);
      await this.play(blob);
      this.played++;
    }
    this.playing = false;
    if (!this.stopped && this.finished && this.items.length === 0) this.ev.onDone?.(this.played > 0, this.lastError || undefined);
  }

  private play(blob: Blob): Promise<void> {
    return new Promise((resolve) => {
      this.url = URL.createObjectURL(blob);
      const audio = new Audio(this.url);
      this.audio = audio;
      const end = () => {
        URL.revokeObjectURL(audio.src);
        if (this.audio === audio) {
          this.audio = null;
          this.url = '';
        }
        resolve();
      };
      audio.onended = end;
      audio.onerror = end;
      audio.onpause = () => {
        if (this.stopped) end();
      };
      audio.play().catch(end);
    });
  }
}

// Lecture ponctuelle d'un texte complet (bouton « Écouter », test de la voix).
export function speakAll(text: string, voice: string, cfg: AudioCfg, ev: QueueEvents = {}): SpeechQueue {
  const q = new SpeechQueue(voice, cfg, ev);
  q.finish(text);
  return q;
}
