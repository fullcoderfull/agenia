// Accès au backend PHP existant depuis l'interface Next.js (même domaine, cookie de session).

const PHP_ORIGIN = (process.env.NEXT_PUBLIC_PHP_ORIGIN ?? '').replace(/\/+$/, '');
const API = `${PHP_ORIGIN}/backend/api.php`;

export type ProviderType = 'llm' | 'tts' | 'stt';

export interface Environment {
  role: string;
  agentEnabled: boolean;
  // Session de démonstration : l'agent est visible et son exécution est simulée.
  demo: boolean;
  version: string;
  providers: Record<ProviderType, { active: string; list: Record<string, { label: string; requiresKey: boolean }> }>;
  // Ajouté côté PHP (config_load) pour la session d'essai ; null si non invité.
  guest: { messagesUsed: number; maxMessages: number; expiresAt: number } | null;
  // Réponse brute de config_load : les réglages voix / micro y sont lus
  // (blocs "audio", "tts", "stt" de settings.json, ou clés à plat de l'ancien front).
  raw: unknown;
  upload: UploadCfg;
}

export interface UploadCfg {
  enabled: boolean;
  maxTokens: number;
  maxPages: number;
  maxSizeMb: number;
  acceptedTypes: string[];
}

// Valeurs de config/settings.json (bloc "upload") — utilisées si le serveur ne les transmet pas.
export const DEFAULT_UPLOAD: UploadCfg = {
  enabled: true,
  maxTokens: 10000,
  maxPages: 2,
  maxSizeMb: 20,
  acceptedTypes: [
    'image/*',
    'application/pdf',
    'text/plain',
    'text/markdown',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
  ],
};

function mergeUpload(raw: unknown): UploadCfg {
  if (!raw || typeof raw !== 'object') return DEFAULT_UPLOAD;
  const r = raw as Partial<UploadCfg>;
  return {
    enabled: typeof r.enabled === 'boolean' ? r.enabled : DEFAULT_UPLOAD.enabled,
    maxTokens: Number(r.maxTokens) || DEFAULT_UPLOAD.maxTokens,
    maxPages: Number(r.maxPages) || DEFAULT_UPLOAD.maxPages,
    maxSizeMb: Number(r.maxSizeMb) || DEFAULT_UPLOAD.maxSizeMb,
    acceptedTypes: Array.isArray(r.acceptedTypes) && r.acceptedTypes.length ? r.acceptedTypes : DEFAULT_UPLOAD.acceptedTypes,
  };
}

interface ConfigLoadRaw {
  [k: string]: unknown;
  role?: string;
  agentEnabled?: boolean;
  demo?: boolean;
  version?: string;
  providers?: Environment['providers'];
  guest?: Environment['guest'];
  audio?: unknown;
  upload?: unknown;
  error?: unknown;
}

// Délai au-delà duquel on cesse d'attender le backend (évite le « Chargement… »
// infini quand config_load accepte la connexion mais ne répond jamais).
const API_TIMEOUT_MS = 15000;

async function apiCall<T>(action: string, body: Record<string, unknown> = {}): Promise<T> {
  let r: Response;
  const ctrl = new AbortController();
  const timer = setTimeout(() => ctrl.abort(), API_TIMEOUT_MS);
  try {
    r = await fetch(API, {
      method: 'POST',
      mode: 'same-origin',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action, ...body }),
      signal: ctrl.signal,
    });
  } catch (e) {
    throw (e as { name?: string }).name === 'AbortError' ? 'timeout' : 'network';
  } finally {
    clearTimeout(timer);
  }
  if (r.status === 401 || r.status === 403) throw 'unauth';
  // Session expirée (essai invité terminé, déconnexion) : le serveur redirige vers
  // la page de connexion, qui arrive ici en HTML. C'est une fin de session, pas une panne.
  if (r.redirected || (r.ok && (r.headers.get('content-type') ?? '').includes('text/html'))) throw 'unauth';
  if (r.status >= 500) throw 'server';
  let data: unknown;
  try {
    data = await r.json();
  } catch {
    throw 'parse';
  }
  if (data && typeof data === 'object' && 'error' in data && (data as { error: unknown }).error) {
    throw 'unauth';
  }
  return data as T;
}

// Local = fournisseur sans clé d'accès (settings.json : requiresKey=false).
// Aucun nom de techno dans le code du front.
export function isLocalProvider(_slug: string, requiresKey: boolean): boolean {
  return requiresKey === false;
}

export async function loadEnvironment(): Promise<Environment> {
  const raw = await apiCall<ConfigLoadRaw>('config_load');
  const providers = raw.providers ?? {
    llm: { active: '', list: {} },
    tts: { active: '', list: {} },
    stt: { active: '', list: {} },
  };
  return {
    role: raw.role ?? 'guest',
    agentEnabled: Boolean(raw.agentEnabled),
    demo: Boolean(raw.demo),
    version: raw.version ?? '',
    providers,
    guest: raw.guest ?? null,
    raw,
    upload: mergeUpload(raw.upload),
  };
}

// ─── Volet admin : réglages sensibles (admin/superadmin) ─────────────────────

export interface SensitiveConfig {
  llmUrl: string;
  llmModel: string;
  visionModel: string;
  systemPrompt: string;
  ttsUrl: string;
  ttsModel: string;
  ttsVoice: string;
  ttsFormat: string;
  sttUrl: string;
  sttModel: string;
  llmKeyMasked: string;
  sttBearerMasked: string;
}

export interface TestResult {
  label: string;
  status: 'ok' | 'warn' | 'error';
  dot?: string;
  msg?: string;
}

// Appel admin qui renvoie l'erreur au lieu de la transformer en 'unauth'.
async function adminCall<T>(action: string, body: Record<string, unknown> = {}): Promise<T> {
  const r = await fetch(API, {
    method: 'POST',
    mode: 'same-origin',
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action, ...body }),
  });
  const data = (await r.json().catch(() => ({ error: true, message: 'Réponse invalide' }))) as Record<string, unknown>;
  if (data.error) throw new Error(typeof data.message === 'string' ? data.message : 'Erreur');
  return data as T;
}

export async function loadSensitive(): Promise<SensitiveConfig> {
  const raw = await adminCall<{ sensitive: Partial<SensitiveConfig> }>('config_load_sensitive');
  const s = raw.sensitive ?? {};
  return {
    llmUrl: s.llmUrl ?? '',
    llmModel: s.llmModel ?? '',
    visionModel: s.visionModel ?? '',
    systemPrompt: s.systemPrompt ?? '',
    ttsUrl: s.ttsUrl ?? '',
    ttsModel: s.ttsModel ?? '',
    ttsVoice: s.ttsVoice ?? '',
    ttsFormat: s.ttsFormat ?? 'mp3',
    sttUrl: s.sttUrl ?? '',
    sttModel: s.sttModel ?? '',
    llmKeyMasked: s.llmKeyMasked ?? '',
    sttBearerMasked: s.sttBearerMasked ?? '',
  };
}

// N'envoie que les champs réellement modifiés (jamais une valeur masquée).
export async function saveSensitive(changes: Record<string, string>): Promise<void> {
  await adminCall('config_save_sensitive', { sensitive: changes });
}

export async function testConnections(): Promise<TestResult[]> {
  const raw = await adminCall<{ results?: TestResult[] }>('test_connections');
  return raw.results ?? [];
}

export async function listProviderModels(slug: string): Promise<{ id: string; name: string }[]> {
  const raw = await adminCall<{ models?: { id: string; name: string }[] }>('provider_models_list', { slug });
  return raw.models ?? [];
}

// ─── Offre de l'utilisateur (app-plan.php) ───────────────────────────────────

export interface PlanInfo {
  plan: string; // free | pro | enterprise | white_label | internal
  paid: boolean;
  upgrade: { kind: 'register' | 'pro'; href: string } | null;
}

// null si l'information n'est pas disponible : on n'affiche alors aucun bouton.
export async function loadPlan(): Promise<PlanInfo | null> {
  try {
    const r = await fetch(`${PHP_ORIGIN}/app-plan.php`, { credentials: 'same-origin', cache: 'no-store' });
    if (!r.ok || r.redirected) return null;
    const d = (await r.json()) as Partial<PlanInfo>;
    if (typeof d.paid !== 'boolean') return null;
    const up = d.upgrade;
    const upgrade =
      up && (up.kind === 'register' || up.kind === 'pro') && typeof up.href === 'string' && up.href.startsWith('/')
        ? { kind: up.kind, href: `${PHP_ORIGIN}${up.href}` }
        : null;
    return { plan: typeof d.plan === 'string' ? d.plan : 'free', paid: d.paid, upgrade };
  } catch {
    return null;
  }
}
