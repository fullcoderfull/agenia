'use client';

import { useEffect, useRef, useState } from 'react';
import type { EnvStrings } from '@/i18n/app-strings';
import type { Environment } from '@/lib/apiClient';
import { listProviderModels, loadSensitive, saveSensitive, testConnections, type SensitiveConfig, type TestResult } from '@/lib/apiClient';
import { mergeAudio, speakAll, type SpeechQueue } from '@/lib/tts';
import { getVoice, getVoicePrefs, isVoiceId, onVoicePrefsChange, setVoice, setVoicePrefs, VOICE_IDS, type VoicePrefs, type VoiceId } from '@/lib/voicePref';

// Volet admin : fournisseur/modèle/clé, voix (Célia / Alex), test.
// Aucune URL ni nom de moteur affiché : ils restent dans config/settings.json.
// Branché sur config_load_sensitive / config_save_sensitive / test_connections.
// Les modules et les capacités (déverrouillage temporaire) viendront au temps 2.

type ProviderList = Environment['providers']['llm']['list'];

const PHP = (process.env.NEXT_PUBLIC_PHP_ORIGIN ?? '').replace(/\/+$/, '');

function providerOptions(list: ProviderList): { slug: string; label: string }[] {
  return Object.entries(list).map(([slug, v]) => ({ slug, label: v.label }));
}

export function SettingsPanel({
  env,
  t,
  isAdmin,
  open,
  onClose,
}: {
  env: Environment;
  t: EnvStrings;
  isAdmin: boolean; // admin / super-admin : onglets IA et Test ; sinon onglet Voix seul
  open: boolean;
  onClose: () => void;
}) {
  const s = t.settings;
  const [cfg, setCfg] = useState<SensitiveConfig | null>(null);
  const [loadErr, setLoadErr] = useState('');
  const [edited, setEdited] = useState<Record<string, string>>({});
  const [status, setStatus] = useState<'idle' | 'saving' | 'saved' | 'error'>('idle');
  const [tests, setTests] = useState<TestResult[] | null>(null);
  const [testing, setTesting] = useState(false);
  const [llmProv, setLlmProv] = useState(env.providers.llm.active);
  const [models, setModels] = useState<{ id: string; name: string }[] | null>(null);
  const [modelsLoading, setModelsLoading] = useState(false);
  const [modelQuery, setModelQuery] = useState('');
  // Recherche de modèles : affichée dès que le serveur renvoie une liste pour ce fournisseur.
  const searchable = modelsLoading || (models !== null && models.length > 0);
  const [tab, setTab] = useState<'ia' | 'tts' | 'test'>(isAdmin ? 'ia' : 'tts');
  const [prefs, setPrefs] = useState<VoicePrefs>({ auto: true, stream: null });
  useEffect(() => onVoicePrefsChange(setPrefs), []);
  const [voiceState, setVoiceState] = useState<'idle' | 'playing' | 'fail'>('idle');
  const [voiceDetail, setVoiceDetail] = useState('');
  const voiceQ = useRef<SpeechQueue | null>(null);
  const [voiceId, setVoiceId] = useState<VoiceId>('fr');

  function onVoiceUiChange(v: string) {
    if (!isVoiceId(v)) return;
    setVoiceId(v);
    setVoice(v);
  }

  function testVoice() {
    if (voiceState === 'playing') {
      voiceQ.current?.stop();
      voiceQ.current = null;
      setVoiceState('idle');
      return;
    }
    setVoiceState('playing');
    setVoiceDetail('');
    const audio = mergeAudio(env.raw);
    // Même chemin que le chat : le serveur applique la voix de config/settings.json.
    voiceQ.current = speakAll(s.voiceSamples[voiceId], voiceId, audio, {
      onDone: (ok, err) => {
        voiceQ.current = null;
        setVoiceState(ok ? 'idle' : 'fail');
        setVoiceDetail(
          err === 'timeout'
            ? s.voiceErrTimeout.replace('{s}', String(Math.round(audio.ttsTimeoutMs / 1000)))
            : err === 'network'
              ? s.voiceErrNetwork
              : err === 'empty'
                ? s.voiceErrEmpty
                : (err ?? ''),
        );
      },
    });
  }

  async function fetchModels(slug: string) {
    setModels(null);
    setModelsLoading(true);
    try {
      setModels(await listProviderModels(slug));
    } catch {
      setModels([]);
    } finally {
      setModelsLoading(false);
    }
  }

  const loaded = useRef(false);
  useEffect(() => {
    if (open && !loaded.current) {
      loaded.current = true;
      setPrefs(getVoicePrefs());
      setVoiceId(getVoice('fr')); // settings.json ui.lang = fr
    }
    if (open && isAdmin && !cfg && !loadErr) {
      loadSensitive()
        .then((c) => {
          setCfg(c);
          setLoadErr('');
        })
        .catch((e: unknown) => {
          setCfg(null);
          setLoadErr(e instanceof Error && e.message ? e.message : 'config_load_sensitive');
        });
      fetchModels(env.providers.llm.active);
    }
    if (!open) {
      voiceQ.current?.stop();
      voiceQ.current = null;
      setVoiceState('idle');
    }
  }, [open]);

  function set(field: string, value: string) {
    setEdited((p) => ({ ...p, [field]: value }));
    setStatus('idle');
  }
  const val = (field: keyof SensitiveConfig) => edited[field] ?? cfg?.[field] ?? '';

  async function save() {
    setStatus('saving');
    try {
      // On n'envoie que les champs modifiés (les clés masquées ne sont jamais renvoyées).
      const changes: Record<string, string> = {};
      for (const [k, v] of Object.entries(edited)) {
        if (!v.includes('•')) changes[k] = v;
      }
      await saveSensitive(changes);
      setStatus('saved');
      setEdited({});
    } catch {
      setStatus('error');
    }
  }

  async function runTest() {
    setTesting(true);
    setTests(null);
    try {
      setTests(await testConnections());
    } catch {
      setTests([{ label: 'API', status: 'error' }]);
    } finally {
      setTesting(false);
    }
  }

  const llmProviders = providerOptions(env.providers.llm.list);

  return (
    <>
      <div className={`panel-scrim ${open ? 'open' : ''}`} onClick={onClose} aria-hidden={!open} />
      <aside className={`panel ${open ? 'open' : ''}`} role="dialog" aria-label={s.title} aria-hidden={!open}>
        <header className="panel-head">
          <h2>⚙ {s.title}</h2>
          <button type="button" className="panel-collapse" onClick={onClose}>
            {s.close} <span aria-hidden="true">⟩</span>
          </button>
        </header>

        <nav className="panel-tabs" role="tablist">
          {(isAdmin
            ? ([['ia', s.iaTab], ['tts', s.ttsTab], ['test', s.testTab]] as const)
            : ([['ia', s.iaTab], ['tts', s.ttsTab]] as const)
          ).map(([id, label]) => (
            <button key={id} type="button" role="tab" aria-selected={tab === id} className={tab === id ? 'active' : ''} onClick={() => setTab(id)}>
              {!isAdmin && id === 'ia' && <span aria-hidden="true">🔒 </span>}
              {label}
            </button>
          ))}
        </nav>

        <div className="panel-body">
          {loadErr && (
            <p className="panel-err" role="alert">
              {s.loadErr} {loadErr}
            </p>
          )}
          {isAdmin && tab === 'ia' && (
          <section className="panel-sec">
            <h3>{s.ia}</h3>
            <label>
              {s.provider}
              <select
                value={llmProv}
                onChange={(e: { target: { value: string } }) => {
                  setLlmProv(e.target.value);
                  set('llmProvider', e.target.value);
                  setModelQuery('');
                  fetchModels(e.target.value);
                }}
              >
                {llmProviders.map((p) => (
                  <option key={p.slug} value={p.slug}>
                    {p.label}
                  </option>
                ))}
              </select>
            </label>
            <label>
              {s.model}
              <input
                id="cfg-llm-model"
                value={val('llmModel')}
                placeholder={s.modelDefault}
                onChange={(e: { target: { value: string } }) => set('llmModel', e.target.value)}
              />
            </label>
            {searchable && (
              <div className="model-search">
                <input
                  className="model-search-input"
                  placeholder={s.searchModel}
                  value={modelQuery}
                  onChange={(e: { target: { value: string } }) => setModelQuery(e.target.value)}
                />
                {modelsLoading && <p className="model-hint">{s.searching}</p>}
                {models && models.length > 0 && (
                  <ul className="model-list">
                    {models
                      .filter((m) => (modelQuery ? (m.id + m.name).toLowerCase().includes(modelQuery.toLowerCase()) : true))
                      .slice(0, 40)
                      .map((m) => (
                        <li key={m.id}>
                          <button type="button" onClick={() => set('llmModel', m.id)}>
                            {m.name || m.id}
                          </button>
                        </li>
                      ))}
                  </ul>
                )}
              </div>
            )}
            <label>
              {s.visionModel}
              <input
                id="cfg-vision-model"
                value={val('visionModel')}
                placeholder={s.visionDefault}
                onChange={(e: { target: { value: string } }) => set('visionModel', e.target.value)}
              />
            </label>
            {/* Prompt système : visible et modifiable par le super-admin uniquement
                (le serveur ne l'envoie ni ne l'accepte pour les autres rôles). */}
            {env.role === 'superadmin' && (
            <label>
              {s.systemPrompt}
              <textarea
                id="cfg-system-prompt"
                rows={6}
                value={val('systemPrompt')}
                onChange={(e: { target: { value: string } }) => set('systemPrompt', e.target.value)}
              />
            </label>
            )}
            {env.providers.llm.list[llmProv]?.requiresKey && (
            <label>
              {s.key}
              <input type="password" placeholder={cfg?.llmKeyMasked || '••••'} onChange={(e: { target: { value: string } }) => set('llmKey', e.target.value)} />
              <small>{s.keyKept}</small>
            </label>
            )}
          </section>
          )}
          {/* Sans accès : l'onglet IA est montré grisé, avec invitation (fonction PRO). */}
          {!isAdmin && tab === 'ia' && (
          <section className="panel-sec pro-lock" aria-labelledby="pro-ia-title">
            <h3 id="pro-ia-title">
              🔒 {s.proIaTitle} <span className="env-pro">PRO</span>
            </h3>
            <p className="pro-lock-text">{env.role === 'guest' || env.demo ? s.proIaGuest : s.proIaFree}</p>
            <div className="pro-lock-preview" aria-hidden="true">
              <label>
                {s.provider}
                <select disabled>
                  <option>{s.proProviders}</option>
                </select>
              </label>
              <ul className="pro-lock-list">
                {Object.entries(env.providers.llm.list)
                  .filter(([, v]) => v.requiresKey)
                  .map(([slug, v]) => (
                    <li key={slug}>{v.label}</li>
                  ))}
              </ul>
              <label>
                {s.model}
                <input disabled placeholder={s.modelDefault} />
              </label>
            </div>
            <a
              className="btn-primary btn-sm pro-lock-cta"
              href={env.role === 'guest' || env.demo ? `${PHP}/register.php` : `${PHP}/checkout.php?plan=pro_monthly`}
            >
              {env.role === 'guest' || env.demo ? s.proCtaRegister : s.proCtaPro}
            </a>
          </section>
          )}
          {tab === 'tts' && (
          <section className="panel-sec">
            <h3>{s.voiceUi}</h3>
            <label>
              {s.voice}
              <select id="cfg-tts-voice-ui" value={voiceId} onChange={(e: { target: { value: string } }) => onVoiceUiChange(e.target.value)}>
                {VOICE_IDS.map((id) => (
                  <option key={id} value={id}>
                    {s.voiceNames[id]}
                  </option>
                ))}
              </select>
            </label>
            <div className="voice-test">
              <button type="button" className="btn-quiet btn-sm" onClick={testVoice}>
                {voiceState === 'playing' ? `⏹ ${s.voiceStop}` : `🔊 ${s.voiceTest}`}
              </button>
              {voiceState === 'fail' && (
                <span className="voice-test-msg">
                  {s.voiceFail}
                  {voiceDetail ? ` — ${voiceDetail}` : ''}
                </span>
              )}
            </div>

            <h3 className="panel-sub">{s.readingOptions}</h3>
            <label className="toggle-row">
              <input type="checkbox" checked={prefs.auto} onChange={(e: { target: { checked: boolean } }) => setVoicePrefs({ auto: e.target.checked })} />
              <span>{s.optAutoVoice}</span>
            </label>
            <label className="toggle-row">
              <input
                type="checkbox"
                checked={prefs.stream ?? mergeAudio(env.raw).streaming}
                disabled={!prefs.auto}
                onChange={(e: { target: { checked: boolean } }) => setVoicePrefs({ stream: e.target.checked })}
              />
              <span>{s.optStreamVoice}</span>
            </label>

          </section>
          )}
          {isAdmin && tab === 'test' && (
          <section className="panel-sec">
            <button type="button" className="btn-quiet btn-sm" onClick={runTest} disabled={testing}>
              {testing ? s.testing : s.test}
            </button>
            {tests && (
              <ul className="test-pills">
                {tests.map((r, i) => (
                  <li key={i} className={`test-pill ${r.status}`} title={r.msg}>
                    {r.status === 'ok' ? '✓' : r.status === 'warn' ? '~' : '✗'} {r.label}
                  </li>
                ))}
              </ul>
            )}
          </section>
          )}
        </div>

        {isAdmin && (
        <footer className="panel-foot">
          <span className={`save-state ${status}`}>
            {status === 'saved' ? s.saved : status === 'error' ? s.saveError : ''}
          </span>
          <button type="button" className="btn-primary btn-sm" onClick={save} disabled={status === 'saving' || Object.keys(edited).length === 0}>
            {status === 'saving' ? s.saving : s.save}
          </button>
        </footer>
        )}
      </aside>
    </>
  );
}
