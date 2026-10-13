// Micro / dictée — repris de l'ancienne version (js/main.js : toggleMic, startMic,
// vadStart, vadCleanup, stopMic, transcribeSTT).
//   - détection de voix (VAD) : l'enregistrement démarre quand on parle
//     (seuil 0.01) et s'arrête après 1,5 s de silence ;
//   - transcription : POST multipart vers proxy-stt.php
//       file (audio.<ext>), model, language, response_format=json
//     réponse JSON → champ "text" ;
//   - les transcriptions parasites (« merci », « you », sous-titrage…) sont ignorées.
// Aucune URL n'est envoyée par le front : proxy-stt.php la lit dans config/settings.json.

const PHP_ORIGIN = (process.env.NEXT_PUBLIC_PHP_ORIGIN ?? '').replace(/\/+$/, '');
const STT = `${PHP_ORIGIN}/proxy-stt.php`;

const MIME_CANDIDATES = ['audio/webm;codecs=opus', 'audio/webm', 'audio/ogg', 'audio/mp4'];
const VAD_THRESHOLD = 0.01;
const VAD_SILENCE_MS = 1500;
const STT_NOISE = ['sous-titrage', 'sous-titre', 'soustitrage', 'st 501', 'st501', 'merci', 'you', 'the', 'i', 'a', '...', '..', '.'];

export type MicState = 'idle' | 'listening' | 'recording' | 'transcribing';
export type MicError = 'unsupported' | 'denied' | 'expired' | 'unavailable' | 'network';

export interface MicHandlers {
  onState: (s: MicState) => void;
  onSpeechStart?: () => void; // on coupe la voix / la réponse en cours (comme l'ancienne version)
  onText: (text: string) => void;
  onError: (e: MicError) => void;
}

export interface SttCfg {
  model: string; // vide = le serveur applique celui de settings.json
  language: string;
}

export function micSupported(): boolean {
  return typeof window !== 'undefined' && !!navigator.mediaDevices?.getUserMedia && typeof MediaRecorder !== 'undefined';
}

// Lit le bloc "stt" de settings.json (ou la clé à plat sttModel de l'ancien front).
export function mergeStt(raw: unknown, fallbackLang: string): SttCfg {
  const root = raw && typeof raw === 'object' ? (raw as Record<string, unknown>) : {};
  const st = (root.stt && typeof root.stt === 'object' ? root.stt : {}) as Record<string, unknown>;
  const model = typeof st.model === 'string' ? st.model : typeof root.sttModel === 'string' ? root.sttModel : '';
  return { model, language: fallbackLang };
}

export function isNoise(text: string): boolean {
  const t = text.trim().toLowerCase();
  return t.length < 3 || STT_NOISE.includes(t) || STT_NOISE.includes(t.replace(/[.!?…\s]+$/, ''));
}

export class Mic {
  private stream: MediaStream | null = null;
  private recorder: MediaRecorder | null = null;
  private chunks: Blob[] = [];
  private ctx: AudioContext | null = null;
  private raf = 0;
  private recording = false;
  private lastVoice = 0;
  private active = false;
  private mime = '';

  constructor(
    private cfg: SttCfg,
    private h: MicHandlers,
  ) {}

  isActive(): boolean {
    return this.active;
  }

  async start(): Promise<void> {
    if (this.active) return;
    if (!micSupported()) {
      this.h.onError('unsupported');
      return;
    }
    try {
      this.stream = await navigator.mediaDevices.getUserMedia({ audio: true });
    } catch {
      this.h.onError('denied');
      return;
    }
    this.active = true;
    this.mime = MIME_CANDIDATES.find((m) => MediaRecorder.isTypeSupported(m)) ?? '';
    this.recorder = this.mime ? new MediaRecorder(this.stream, { mimeType: this.mime }) : new MediaRecorder(this.stream);
    this.chunks = [];
    this.recorder.ondataavailable = (e) => {
      if (e.data && e.data.size) this.chunks.push(e.data);
    };
    this.recorder.onstop = () => {
      const type = this.recorder?.mimeType || this.mime || 'audio/webm';
      const blob = new Blob(this.chunks, { type });
      this.chunks = [];
      this.release();
      if (blob.size) void this.transcribe(blob);
      else this.h.onState('idle');
    };
    this.h.onState('listening');
    this.vadStart();
  }

  // Arrêt demandé (clic) : si on parlait, la phrase est transcrite.
  stop(): void {
    if (!this.active) return;
    this.active = false;
    this.vadCleanup();
    if (this.recorder && this.recorder.state === 'recording') {
      this.recorder.stop(); // → onstop → transcription
    } else {
      this.release();
      this.h.onState('idle');
    }
  }

  // Abandon sans transcription (changement de page, etc.).
  cancel(): void {
    this.active = false;
    this.vadCleanup();
    if (this.recorder) {
      this.recorder.onstop = null;
      if (this.recorder.state === 'recording') this.recorder.stop();
    }
    this.release();
    this.h.onState('idle');
  }

  private vadStart(): void {
    if (!this.stream) return;
    const AC = window.AudioContext ?? (window as unknown as { webkitAudioContext?: typeof AudioContext }).webkitAudioContext;
    if (!AC) {
      this.beginRecording();
      return;
    }
    this.ctx = new AC();
    const src = this.ctx.createMediaStreamSource(this.stream);
    const analyser = this.ctx.createAnalyser();
    analyser.fftSize = 512;
    src.connect(analyser);
    const data = new Uint8Array(analyser.frequencyBinCount);
    const tick = () => {
      if (!this.active) return;
      analyser.getByteFrequencyData(data);
      let sum = 0;
      for (let i = 0; i < data.length; i++) sum += data[i] ?? 0;
      const level = sum / data.length / 255;
      const now = performance.now();
      if (level > VAD_THRESHOLD) {
        this.lastVoice = now;
        if (!this.recording) this.beginRecording();
      } else if (this.recording && now - this.lastVoice > VAD_SILENCE_MS) {
        this.stop();
        return;
      }
      this.raf = requestAnimationFrame(tick);
    };
    this.raf = requestAnimationFrame(tick);
  }

  private beginRecording(): void {
    if (!this.recorder || this.recording) return;
    this.recording = true;
    this.lastVoice = performance.now();
    this.recorder.start();
    this.h.onSpeechStart?.();
    this.h.onState('recording');
  }

  private vadCleanup(): void {
    if (this.raf) cancelAnimationFrame(this.raf);
    this.raf = 0;
    this.recording = false;
  }

  private release(): void {
    this.stream?.getTracks().forEach((t) => t.stop());
    this.stream = null;
    if (this.ctx) void this.ctx.close().catch(() => undefined);
    this.ctx = null;
  }

  private async transcribe(blob: Blob): Promise<void> {
    this.h.onState('transcribing');
    const ext = (blob.type.split('/')[1] ?? 'webm').split(';')[0] || 'webm';
    const form = new FormData();
    form.append('file', blob, `audio.${ext}`);
    if (this.cfg.model) form.append('model', this.cfg.model);
    form.append('language', this.cfg.language);
    form.append('response_format', 'json');
    try {
      const r = await fetch(STT, { method: 'POST', mode: 'same-origin', credentials: 'same-origin', body: form });
      if (r.status === 401) return this.fail('expired');
      if (!r.ok) return this.fail('unavailable');
      const data = (await r.json().catch(() => ({}))) as { text?: unknown };
      const text = typeof data.text === 'string' ? data.text.trim() : '';
      this.h.onState('idle');
      if (text && !isNoise(text)) this.h.onText(text);
    } catch {
      this.fail('network');
    }
  }

  private fail(e: MicError): void {
    this.h.onState('idle');
    this.h.onError(e);
  }
}
