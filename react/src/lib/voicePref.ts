// Voix de l'assistant choisie par l'utilisateur — reprise de l'ancienne version :
//   <select id="cfg-tts-voice-ui"> fr = « Célia — Français », en = « Alex — Anglais »
// Seul l'identifiant (fr / en) est envoyé au serveur, qui en déduit la voix réelle.

export type VoiceId = 'fr' | 'en';
export const VOICE_IDS: VoiceId[] = ['fr', 'en'];

const KEY = 'agenia.voiceId';
const EVENT = 'agenia-voice';

export function isVoiceId(v: unknown): v is VoiceId {
  return v === 'fr' || v === 'en';
}

export function getVoice(fallback: VoiceId): VoiceId {
  try {
    const v = localStorage.getItem(KEY);
    if (isVoiceId(v)) return v;
  } catch {
    /* stockage indisponible */
  }
  return fallback;
}

export function setVoice(v: VoiceId): void {
  try {
    localStorage.setItem(KEY, v);
  } catch {
    /* stockage indisponible */
  }
  window.dispatchEvent(new CustomEvent(EVENT, { detail: v }));
}

// Prévient la page de chat quand la voix change dans le volet réglages.
export function onVoiceChange(fn: (v: VoiceId) => void): () => void {
  const h = (e: Event) => {
    const v = (e as CustomEvent).detail;
    if (isVoiceId(v)) fn(v);
  };
  window.addEventListener(EVENT, h);
  return () => window.removeEventListener(EVENT, h);
}

// ── Préférences de lecture (volet Réglages + bouton 🔊 de la saisie) ──

const AUTO_KEY = 'agenia.voice'; // 'on' | 'off' — lecture automatique des réponses
const STREAM_KEY = 'agenia.voiceStream'; // 'on' | 'off' — lire pendant l'écriture
const PREF_EVENT = 'agenia-voice-pref';

export interface VoicePrefs {
  auto: boolean;
  stream: boolean | null; // null = réglage du serveur (settings.json audio.streaming)
}

export function getVoicePrefs(): VoicePrefs {
  try {
    const a = localStorage.getItem(AUTO_KEY);
    const s = localStorage.getItem(STREAM_KEY);
    return { auto: a !== 'off', stream: s === 'on' ? true : s === 'off' ? false : null };
  } catch {
    return { auto: true, stream: null };
  }
}

export function setVoicePrefs(p: Partial<VoicePrefs>): void {
  try {
    if (p.auto !== undefined) localStorage.setItem(AUTO_KEY, p.auto ? 'on' : 'off');
    if (p.stream !== undefined && p.stream !== null) localStorage.setItem(STREAM_KEY, p.stream ? 'on' : 'off');
  } catch {
    /* stockage indisponible */
  }
  window.dispatchEvent(new CustomEvent(PREF_EVENT));
}

export function onVoicePrefsChange(fn: (p: VoicePrefs) => void): () => void {
  const h = () => fn(getVoicePrefs());
  window.addEventListener(PREF_EVENT, h);
  return () => window.removeEventListener(PREF_EVENT, h);
}
