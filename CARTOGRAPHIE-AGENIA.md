# Cartographie du projet Agenia (interne : VoxMind)

> Document de reprise. Objectif : permettre à n'importe qui de comprendre, installer,
> modifier et exploiter le projet sans contexte préalable.
> **Confidentiel.** Les noms de technologies cités ici (moteurs LLM/voix) ne doivent
> **jamais** apparaître côté navigateur (voir § 7 — Protection de la propriété intellectuelle).

Dernière mise à jour : 2026-10-12.

---

## 1. Vue d'ensemble

Agenia est une application d'assistant IA (chat + voix + agent) pour décideurs.
Migration en cours : l'**interface** passe de PHP+JS à **Next.js** ; le **backend PHP existant
reste l'API**. Bilingue **FR/EN**, **UTF‑8 strict**.

Positionnement produit : outil orienté décideurs, avec **observabilité / KPI** de premier plan
et un **modèle de sécurité** explicite (lecture à la volée, écriture sous contrôle, capacités
dangereuses désactivées par défaut).

| Couche | Technologie | Rôle |
|---|---|---|
| Interface | Next.js 15 (App Router), export statique | UI chat, voix, réglages, audit |
| API | PHP (existant) | Authentification, config, streaming LLM, agent, audit |
| Moteur LLM local | Ollama (`127.0.0.1:11434`) | Réponses IA locales |
| Moteurs LLM cloud | OpenRouter (et assimilés) | Réponses IA cloud (clé requise) |
| Synthèse vocale (TTS) | Kokoro | Voix serveur (voix « Célia » / « Alex » côté UI) |
| Reconnaissance vocale (STT) | Whisper | Dictée micro |
| Terminal / agent | Service exécuteur HTTP (`127.0.0.1:5000`) | Exécution de commandes par l'agent |
| Hébergement | IIS (Windows) | Sert le front statique + le PHP |

> ⚠️ **Masquage obligatoire** : « Ollama », « Kokoro », « Whisper », « OpenRouter », les URL et
> les IP ne doivent jamais être exposés au front. Le front n'affiche que `local` / `cloud` et
> des libellés neutres (« Célia », « Alex »).

---

## 2. Topologie de déploiement (IIS)

Deux arbres parallèles sur le serveur :

- **`agenia-dev\`** — arbre de travail historique (ancien front PHP, et à un moment le projet
  `react\`). **Non servi** pour la nouvelle UI.
- **`web-dev\`** — **arbre servi par IIS** (port 8081, hôte `agent.datacorners.com`).
  C'est le seul qui compte pour la mise en production du nouveau front.

Points clés :

- Le front Next.js est buildé en **export statique** (`react\out\`) puis **copié dans `web-dev\app\`**.
- `next.config` : `output: 'export'`, **`basePath: '/app'`**, `trailingSlash: true`,
  `images.unoptimized`. Aucune logique serveur Next : **le PHP reste l'API**.
- URL publique du chat : `https://agent.datacorners.com/app/fr/chat/`.
- `index.php` à la racine de `web-dev` redirige vers `/app`.
- Les appels internes PHP→PHP doivent porter l'en‑tête **`Host: agent.datacorners.com`**
  (IIS rejette `localhost` : « Bad Request - Invalid Hostname »).
- Le projet `react\` a été déplacé **dans `web-dev\`** : s'assurer qu'IIS **n'expose pas** le
  dossier source `web-dev\react\` (seul `web-dev\app\` doit être public).

Script de publication : `react\scripts\deploy.mjs` copie `react\out` → `%AGENIA_WEB_ROOT%\app`
(sinon dossier parent de `react\`). `npm run deploy` = `next build` + cette copie.

---

## 3. Backend PHP

Racine servie : `web-dev\`.

```
web-dev\
├─ index.php                 redirection vers /app
├─ login.php / logout.php / register.php
├─ check.php                 AUTH : définit les globals de session (voir § 5)
├─ backend\
│   └─ api.php               routeur d'actions (cœur de l'API)
├─ proxy-llm.php             streame vers le moteur LLM (local/cloud)
├─ proxy-mcp.php             proxy vers le service terminal (agent)
├─ proxy-tts.php             synthèse vocale (TTS)
├─ superadmin-front.php      tableau de bord super‑admin (contrôle)
├─ superadmin-journal.php    JOURNAL D'AUDIT (KPI + chronologie + CSV)
├─ lib\
│   ├─ debug.php             logs techniques
│   ├─ audit.php             JOURNAL d'audit (écriture/lecture JSONL)   ← ajout observabilité
│   ├─ db.php / db_helpers.php
├─ config\
│   ├─ settings.json         config NON sensible (voir § 10)
│   └─ secrets.php           secrets / define()
├─ data\
│   └─ admin_sensitive.json  réglages sensibles (URL, modèles, clés)
└─ app\                      ← FRONT buildé (copié depuis react\out)
```

### 3.1 `backend\api.php` — routeur d'actions

Entrée : POST JSON `{ action, ... }`. `$action = $body['action']`. `switch ($action)` :

| action | fonction | rôle |
|---|---|---|
| `probe` | `handleProbe` | ping/état |
| `test_connections` | `handleTestConnections` | test des fournisseurs (pastilles Réglages) |
| `llm_direct` | `handleLlmDirect` | réponse LLM non‑streamée |
| `llm_stream` | `handleLlmStream` | **réponse LLM streamée + détection agent** (§ 4.1) |
| `mcp` | `handleMcp` | appel d'outil terminal (MCP) |
| `config_load` | `handleConfigLoad` | config front (rôle, agentEnabled, providers, guest, demo…) |
| `config_load_sensitive` | `handleConfigLoadSensitive` | réglages sensibles (admin+) |
| `config_save_sensitive` | `handleConfigSaveSensitive` | sauvegarde réglages sensibles (admin+) |
| `provider_prefs_save/load` | `handleProviderPrefs*` | préférences fournisseur/modèle |
| `provider_models_list` | `handleProviderModelsList` | liste des modèles d'un fournisseur |
| `capacites_list/save` | `handleCapacites*` | capacités par rôle (temps 2 : déverrouillage) |
| *(défaut)* | `handleAgentRun` | **exécution de l'action agent validée** (§ 4.1) |

`roleHasCapacity(string $slug, string $role): bool` — autorise une capacité (ex. `terminal`)
selon le rôle.

### 3.2 `proxy-llm.php`
Streame la réponse du moteur LLM (Ollama local ou cloud). **Pose les en‑têtes**
`X-Agenia-Provider`, `X-Agenia-Local`, `X-Agenia-Model` → lus par `api.php` pour l'audit
(et **retirés** avant d'atteindre le front). Reçoit `X-Trace-Id` pour corréler (§ 6).

### 3.3 `proxy-mcp.php`
Proxy HTTP vers le **service terminal** (`127.0.0.1:5000`, endpoint `/api/Terminal/linux`).
Durci (marqueur `AGENIA-502`) : `display_errors` off, `timeout 20 + ignore_errors`,
`EmailRepId = u ?? authUser ?? ''`, et **échec → HTTP 502 mais JSON valide**.

### 3.4 `proxy-tts.php`
Synthèse vocale serveur. Contrat `{ input, lang, format }`. La voix réelle est choisie dans
`settings.json` ; le front n'envoie qu'un identifiant de voix neutre.

---

## 4. Flux de données

### 4.1 Chat + Agent (le flux critique)

```
Front (chat) ──POST llm_stream──▶ api.php:handleLlmStream
        │                               │  (génère Trace‑Id)
        │                               ├─▶ proxy-llm.php ─▶ moteur LLM (local/cloud)
        │                               │        ◀── tokens streamés
        │   ◀────────── NDJSON ─────────┤   (texte « sûr » uniquement)
        │
   Si le LLM émet le protocole interne « @terminal action=… objectif=… mode=admin » :
        │                               │  détection côté serveur, JAMAIS renvoyée au front
        │   ◀── {clear:true} + carte ───┤   (carte d'accord si autorisé/démo ; refus sinon)
        │
 L'utilisateur ACCEPTE  ──POST (defaut)──▶ api.php:handleAgentRun
        │                               ├─ démo  → résultat SIMULÉ (rien n'est exécuté)
        │                               └─ admin → proxy-mcp.php ─▶ service terminal
        │   ◀────────── résultat ───────┘
```

**Anti‑fuite (marqueur `AGENIA-LEAK`)** dans `handleLlmStream` : un tampon de rédaction
retient la plus longue queue qui pourrait devenir `@terminal`. Dès que le marqueur apparaît,
plus rien n'est émis ; sur correspondance complète, on envoie `{clear:true}` + la carte agent.
**Le protocole `@terminal` n'atteint jamais le navigateur** (propriété intellectuelle).

### 4.2 Voix
- **TTS** (`tts.ts`) : lecture serveur via `proxy-tts.php`, file d'attente `SpeechQueue`,
  streaming phrase par phrase. **Pas de voix navigateur.**
- **STT** (`stt.ts`) : micro + détection de voix (VAD) → Whisper côté serveur.

### 4.3 Config au démarrage
Front appelle `config_load` → reçoit `{ role, agentEnabled, demo, version, providers, guest, upload, audio… }`.
`apiClient.loadEnvironment()` mappe ça en `Environment` (timeout 15 s ; redirection/HTML ⇒ non‑authentifié).

---

## 5. Modèle de sécurité & rôles

`check.php` définit les globals de session :
`authRole`, `authUser`, `u` (email), `isAdmin`, `isSuperAdmin`, `isGuest`, `isDemo`.
`isSuperAdmin = (authUser === SUPERADMIN_USER)` avec `define('SUPERADMIN_USER','mike')`.

| Rôle | Chat | Agent | Réglages sensibles | Prompt système | Journal d'audit |
|---|---|---|---|---|---|
| `guest` (essai) | quota + expiration | ✗ | ✗ | ✗ | incitation à créer un compte |
| `demo` (vitrine) | ✓ | **visible, exécution SIMULÉE** | lecture (à décider) | ✓ (exemples, étiqueté « DÉMO ») |
| `user` (inscrit) | ✓ | ✗ (par défaut) | ✗ | sa propre activité |
| `admin` | ✓ | ✓ (réel) | ✓ | — |
| `superadmin` (`mike`) | ✓ | ✓ (réel) | ✓ + prompt système | **tout** + dashboard contrôle |

Principes :
- **Lecture / analyse à la volée** ; **écriture sous contrôle + observabilité maximale**.
- Capacités dangereuses (suppression, MCP) **désactivées par défaut**.
- **Break‑glass** (temps 2) : déverrouillage temporaire avec phrase de confirmation +
  **expiration serveur à 2 min**.
- Le **prompt système** n'est visible/modifiable que par le super‑admin (le serveur ne
  l'envoie ni ne l'accepte pour les autres rôles).

---

## 6. Observabilité / Audit  (marqueur `AGENIA-OBS`)

**`lib/audit.php`** — journal **JSONL** `logs/audit_AAAA-MM-JJ.jsonl`.
- `audit_trace_id()` : en‑tête `X-Trace-Id` ou `random_bytes(8)` (16 hex).
- `audit_write($event, $data)` : append JSONL ; tronque les chaînes > 500.
- `audit_read($date)` : lit un jour, ordre inversé.
- **Faits uniquement — aucun contenu de conversation.**

Événements : `llm_call`, `agent_proposed`, `agent_run`, `agent_decision`.
Champs : `trace, user, role, status, provider, model, local, duration_ms, ttft_ms,
tokens_in/out, command, goal, decision`.
**Corrélation** : `Trace‑Id` relie `api.php` ↔ `proxy-llm.php` (en‑tête `X-Trace-Id`).

**`superadmin-journal.php`** — page d'audit :
- Indicateurs du jour (réponses, durée moyenne, p95, TTFT, % local, tokens, erreurs,
  agent proposées/accordées/refusées, personnes actives).
- Filtres (date, événement, personne/trace/commande), **export CSV**.
- **Portée** : super‑admin voit tout ; inscrit voit **sa seule activité** ; invité → page
  d'incitation à créer un compte ; **démo** → données réelles **+ exemples**
  (`journal_sample_rows`), bandeau « DÉMO ».

---

## 7. Protection de la propriété intellectuelle (PI)

1. **Masquage des technos** : le front n'affiche jamais Ollama/Kokoro/Whisper/OpenRouter,
   ni URL, ni IP. Dans l'audit renvoyé au front, `model`/`provider` sont **retirés**
   (seul `local: true/false` subsiste).
2. **Anti‑fuite protocole agent** : `@terminal …` est traité **côté serveur** et n'apparaît
   jamais dans la réponse réseau (vérifiable en F12/Network). Voir § 4.1.
3. URL et secrets vivent dans `config/settings.json` et `data/admin_sensitive.json`,
   jamais en dur dans le front.

---

## 8. Mode démonstration (vitrine)  (marqueur `AGENIA-DEMO`)

But : **montrer toutes les fonctionnalités sans risque** pour la vente.
- **Agent visible** : la carte d'accord s'affiche, puis l'exécution est **simulée**
  (`handleAgentRun` renvoie « DÉMONSTRATION - commande non exécutée », audit `status=demo`).
  Rien n'atteint le vrai terminal.
- `config_load` renvoie `agentEnabled=true` et `demo=true` en démo ; le routeur laisse la
  démo atteindre `handleAgentRun`.
- **Journal d'audit visible** en démo (exemples + bandeau « DÉMO »).
- Front : badge « Démonstration » dans la barre + lien Journal ouvert aux démo/inscrits.

---

## 9. Front Next.js  (`web-dev\react\src\`)

```
app/(root)/layout.tsx, page.tsx      page d'accueil (landing + vidéo démo + capacités)
app/[locale]/layout.tsx, page.tsx    landing localisée
app/[locale]/chat/page.tsx           ÉCRAN CHAT (audit, agent, micro, pièces jointes)
components/
  EnvBar.tsx           barre d'environnement (rôle, local/cloud, agent, badge démo, liens)
  SettingsPanel.tsx    réglages (IA/Test = admin ; Voix = tous ; prompt système = super‑admin)
  AuditTrail.tsx       fil d'audit côté chat
  MessageContent.tsx   rendu des messages
  SiteHeader.tsx / ThemeToggle.tsx / Icon.tsx
lib/
  apiClient.ts   accès PHP : loadEnvironment, loadPlan, sensitive, providers, test (timeout 15 s)
  chatClient.ts  streaming chat, ChatImage, ServerAudit, événements trace/audit, recordAgentDecision
  tts.ts         TTS serveur (SpeechQueue, streaming)   stt.ts  micro/VAD
  voicePref.ts   préférences de voix     attachments.ts  pièces jointes
  php.ts  theme.ts
i18n/
  app-strings.ts (EnvStrings…)  config.ts  dictionaries.ts  messages/fr.json  messages/en.json
styles/globals.css
```

`Environment` (apiClient) : `{ role, agentEnabled, demo, version, providers, guest, raw, upload }`.

---

## 10. Configuration

- **`config/settings.json`** (non sensible) :
  `llm.url/model/noThink`, `providers.{llm,tts,stt}.{active,list}` (chaque entrée :
  `label`, `url`, `requiresKey`), blocs `audio`/`upload`.
  Fournisseur LLM par défaut : `ollama` (`127.0.0.1:11434`) ; TTS `kokoro` ; STT `whisper`.
- **`data/admin_sensitive.json`** : réglages sensibles (URL, modèles, clés) — jamais renvoyés
  en clair ; les clés sont masquées (`•`) côté UI.
- **`config/secrets.php`** : `define()` (dont `SUPERADMIN_USER`).
- Règle utilisateur : **ne pas inventer de config** — toujours s'appuyer sur `settings.json`
  (clé historique conservée : `instructPrompt`).

---

## 11. Lots livrés & patchs PowerShell

Tous les patchs sont **idempotents** (marqueur de garde) et créent une **sauvegarde**
`*.avant-<lot>-AAAAMMJJ-HHMM` avant d'écrire. UTF‑8/BOM préservé.

| Lot | Fichiers | Marqueur | Effet |
|---|---|---|---|
| Observabilité | `patch-obs.ps1`, `lib/audit.php`, `superadmin-journal.php` | `AGENIA-OBS` | journal + page audit |
| Fuite / 502 | `patch-leak.ps1`, `snippets/handleLlmStream.php` | `AGENIA-LEAK`, `AGENIA-502` | anti‑fuite + proxy‑mcp durci |
| Rôle super‑admin | one‑liner | — | `config_load` renvoie `role=superadmin` ; `SUPERADMIN_USER='mike'` |
| Démo vitrine | `patch-demo.ps1`, `snippets/handleLlmStream.php`, `superadmin-journal.php` | `AGENIA-DEMO` | agent visible + simulé, journal démo, flag `demo` |

Conventions PowerShell retenues (pour tout nouveau patch) :
- lignes **simples, indépendantes**, compatibles **Windows PowerShell 5.1** (collage interactif) ;
- pas de `if` comme expression ; `if/else` sur **une seule ligne** si nécessaire ;
- tests via `$(Test-Path …)` et `$([bool](Select-String -Path X -Pattern Y -SimpleMatch))` ;
- `FnEnd` = recherche d'accolade fermante par appariement avec casts `[int]` ;
- sauvegarde + conservation du BOM (`SaveSame`).

---

## 12. Installation / build / déploiement

**Backend** (depuis `web-dev\`, un patch à la fois) :
```powershell
Expand-Archive .\<lot>.zip . -Force
Copy-Item .\<lot>\serveur\<page>.php . -Force          # pages (journal, etc.)
powershell -ExecutionPolicy Bypass -File .\<lot>\serveur\<patch>.ps1   # patch api.php/proxy
```

**Front** (depuis `web-dev\`, le projet est dans `web-dev\react\`) :
```powershell
$web = (Get-Location).Path ; $react = Join-Path $web 'react'
Copy-Item .\<lot>\front\src\* "$react\src" -Recurse -Force
Push-Location $react ; $env:AGENIA_WEB_ROOT = $web ; npm run deploy ; Pop-Location
```
Publie `react\out` → `web-dev\app`. Puis **Ctrl+F5**.

**Vérifications rapides** (ligne de commande) :
- `index.php` redirige vers `/app` ; `web-dev\app\` contient le build à jour.
- Connexion démo : badge « Démonstration » + lien « Journal d'audit » visibles.
- Agent en démo : carte d'accord → résultat « DÉMONSTRATION … » ; **aucun `@terminal`** en F12/Network.
- Service terminal joignable en `127.0.0.1:5000` (pas via l'IP LAN).

---

## 13. Points de vigilance & reste à faire

**Vigilance**
- IIS : ne pas exposer `web-dev\react\` (source) ; seul `web-dev\app\` est public.
- Appels PHP→PHP : en‑tête `Host: agent.datacorners.com` obligatoire.
- Terminal : utiliser `127.0.0.1:5000` (l'IP LAN `172.17.164.70:5000` ne répond pas).
- Toujours masquer les technos côté front (§ 7).

**Temps 2 (priorité 2/3)**
- Journal : graphes / agrégation ; suivi des **coûts** ; **alertes** ; log
  **inviolable** (tamper‑evident) ; **rétention**.
- **Connecteur Notion** (payant) pour visualiser/agréger les audits.
- **Break‑glass** des capacités dangereuses (phrase de confirmation + expiration 2 min).
- Décision ouverte : panneau Réglages en **lecture seule** en démo ?

---

*Fin de la cartographie.*
