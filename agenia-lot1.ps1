<#
  agenia-lot1.ps1 — génère le dossier react\ (Next.js, export statique) à la racine du site.
  Ne modifie aucun fichier PHP. À lancer depuis la racine IIS :
    powershell -ExecutionPolicy Bypass -File .\agenia-lot1.ps1
  Option -Force : réécrit les fichiers du lot s'ils existent déjà.
#>
param([switch]$Force)
$ErrorActionPreference = 'Stop'
$utf8 = New-Object System.Text.UTF8Encoding($false)
$base = (Get-Location).Path

# Vérification de Node : informative uniquement, ne bloque jamais le script.
try {
  $nodeOut = (& node -v 2>$null | Out-String).Trim()
  if ($nodeOut -match '(\d+)\.(\d+)\.(\d+)') {
    $v = New-Object System.Version ([int]$Matches[1]), ([int]$Matches[2]), ([int]$Matches[3])
    if ($v -lt (New-Object System.Version 18, 18, 0)) { Write-Warning "Node $v trop ancien : Next.js demande 18.18 minimum (20 LTS conseillé)." }
    else { Write-Host "Node $v détecté." }
  } else { Write-Warning "Version de Node illisible : '$nodeOut'. Vérifiez avec : node -v" }
} catch {
  Write-Warning 'Node.js introuvable : installez Node 20 LTS ou plus récent avant npm install.'
}

function Write-LotFile([string]$rel, [string]$content) {
  $full = Join-Path $base $rel
  if ((Test-Path $full) -and -not $Force) { Write-Host "existe, ignoré : $rel (relancer avec -Force pour réécrire)" -ForegroundColor Yellow; return }
  $dir = Split-Path $full -Parent
  if (-not (Test-Path $dir)) { New-Item -ItemType Directory -Force -Path $dir | Out-Null }
  [IO.File]::WriteAllText($full, (($content -replace "`r`n", "`n") + "`n"), $utf8)
  Write-Host "écrit : $rel"
}
Write-LotFile 'react\.env.development' @'
# En développement (npm run dev), les liens vers le PHP pointent vers le site réel.
# En production (npm run deploy), ne rien définir : même domaine que le PHP.
NEXT_PUBLIC_PHP_ORIGIN=https://agenia.datacorners.com
'@
Write-LotFile 'react\.gitignore' @'
node_modules/
.next/
out/
*.tsbuildinfo
next-env.d.ts
.env*.local
'@
Write-LotFile 'react\README.md' @'
# Agenia — front Next.js

Export statique servi par IIS sous `/app/`. Le PHP existant reste l'API : aucune logique serveur ici.

## Commandes (depuis `react\`)

| Commande | Effet |
|---|---|
| `npm install` | Installe les dépendances |
| `npm run dev` | Serveur local : http://localhost:3000/app/ |
| `npm run typecheck` | Vérifie les types (dont la parité des traductions) |
| `npm run deploy` | Compile puis publie `out\` vers `..\app\` |

## Arborescence

```
react\
├── public\web.config        en-têtes de sécurité de /app (copié au déploiement)
├── web.config               bloque tout accès HTTP au dossier source
├── scripts\deploy.mjs       copie out\ -> ..\app\
└── src\
    ├── app\(root)\          /app/ : redirection selon la langue du navigateur
    ├── app\[locale]\        /app/fr/, /app/en/ (pages générées à la compilation)
    ├── components\          composants d'interface
    ├── i18n\                langues + messages\*.json
    ├── lib\php.ts           liens vers les pages PHP (liste fermée)
    └── styles\globals.css   design tokens (thèmes sombre/clair)
```

## Ajouter l'allemand

1. `src\i18n\config.ts` : ajouter `'de'` dans `locales` et dans `localeLabels`.
2. Copier `src\i18n\messages\fr.json` en `de.json` et traduire.
3. `src\i18n\dictionaries.ts` : importer `de.json` et l'ajouter à `dictionaries`.
4. `npm run deploy`.

Tant qu'une clé manque dans une langue, `npm run typecheck` et `npm run build` échouent.

## Encodage

Tous les fichiers sont en UTF-8 sans BOM. Ne pas les enregistrer en ANSI.
'@
Write-LotFile 'react\next.config.mjs' @'
// Export statique servi par IIS sous /app (voir scripts/deploy.mjs).
// Aucune logique serveur Next.js : le PHP existant reste l'API.

/** @type {import('next').NextConfig} */
const nextConfig = {
  output: 'export',
  basePath: '/app',
  trailingSlash: true,
  images: { unoptimized: true },
  poweredByHeader: false,
  reactStrictMode: true,
  // ESLint n'est pas installé dans ce lot : la vérification passe par `npm run typecheck`.
  eslint: { ignoreDuringBuilds: true },
};

export default nextConfig;
'@
Write-LotFile 'react\package.json' @'
{
  "name": "agenia-web",
  "version": "0.2.0",
  "private": true,
  "engines": {
    "node": ">=18.18.0"
  },
  "scripts": {
    "dev": "next dev",
    "build": "next build",
    "typecheck": "tsc --noEmit",
    "deploy": "next build && node scripts/deploy.mjs"
  },
  "dependencies": {
    "@fontsource/instrument-sans": "^5.0.0",
    "@fontsource/jetbrains-mono": "^5.0.0",
    "next": "^15.3.0",
    "react": "^19.1.0",
    "react-dom": "^19.1.0"
  },
  "devDependencies": {
    "@types/node": "^22.0.0",
    "@types/react": "^19.1.0",
    "@types/react-dom": "^19.1.0",
    "typescript": "^5.8.0"
  }
}
'@
Write-LotFile 'react\public\web.config' @'
<?xml version="1.0" encoding="UTF-8"?>
<!-- Copié dans <racine>\app\ par "npm run deploy". Sert le site Next.js statique. -->
<configuration>
  <system.webServer>
    <!-- Le web.config racine limite les gestionnaires : ici, fichiers statiques uniquement (aucun PHP dans app\). -->
    <handlers>
      <clear />
      <add name="AgeniaStatic" path="*" verb="GET,HEAD" modules="StaticFileModule,DefaultDocumentModule" resourceType="Either" requireAccess="Read" />
    </handlers>
    <defaultDocument enabled="true">
      <files>
        <remove value="index.html" />
        <add value="index.html" />
      </files>
    </defaultDocument>
    <staticContent>
      <remove fileExtension=".json" />
      <mimeMap fileExtension=".json" mimeType="application/json; charset=utf-8" />
      <remove fileExtension=".txt" />
      <mimeMap fileExtension=".txt" mimeType="text/plain; charset=utf-8" />
      <remove fileExtension=".woff2" />
      <mimeMap fileExtension=".woff2" mimeType="font/woff2" />
    </staticContent>
    <httpProtocol>
      <customHeaders>
        <remove name="X-Content-Type-Options" />
        <add name="X-Content-Type-Options" value="nosniff" />
        <remove name="X-Frame-Options" />
        <add name="X-Frame-Options" value="SAMEORIGIN" />
        <remove name="Referrer-Policy" />
        <add name="Referrer-Policy" value="strict-origin-when-cross-origin" />
        <remove name="Permissions-Policy" />
        <add name="Permissions-Policy" value="camera=(self), microphone=(self), geolocation=()" />
        <remove name="Content-Security-Policy" />
        <add name="Content-Security-Policy" value="default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; media-src 'self' blob:; connect-src 'self'; font-src 'self'; frame-ancestors 'self'; base-uri 'self'; form-action 'self'; object-src 'none'" />
      </customHeaders>
    </httpProtocol>
  </system.webServer>
</configuration>
'@
Write-LotFile 'react\scripts\deploy.mjs' @'
// Copie le build statique (react/out) dans <dossier servi par IIS>\app.
// Dossier servi : variable AGENIA_WEB_ROOT, sinon le dossier parent de react\.
import { cpSync, existsSync, rmSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const src = path.join(root, 'out');
const webRoot = path.resolve(process.env.AGENIA_WEB_ROOT || path.join(root, '..'));
const dest = path.join(webRoot, 'app');

if (!existsSync(path.join(src, 'index.html'))) {
  console.error('Build introuvable : lancez "npm run build" (out/index.html absent).');
  process.exit(1);
}
if (!existsSync(webRoot)) {
  console.error(`Dossier servi introuvable : ${webRoot}`);
  process.exit(1);
}

rmSync(dest, { recursive: true, force: true });
cpSync(src, dest, { recursive: true });
console.log(`Publié : ${src} -> ${dest}`);
'@
Write-LotFile 'react\src\app\(root)\layout.tsx' @'
import type { ReactNode } from 'react';
import { themeInitScript } from '@/lib/theme';
import '@fontsource/instrument-sans/400.css';
import '@fontsource/instrument-sans/500.css';
import '@fontsource/instrument-sans/600.css';
import '@fontsource/instrument-sans/700.css';
import '@fontsource/jetbrains-mono/400.css';
import '@fontsource/jetbrains-mono/500.css';
import '@/styles/globals.css';

export const metadata = {
  title: 'Agenia',
  robots: { index: false, follow: true },
};

// Layout de la seule page /app/ : redirection vers la langue du navigateur.
export default function RootRedirectLayout({ children }: { children: ReactNode }) {
  return (
    <html lang="fr" data-theme="light" suppressHydrationWarning>
      <head>
        <script dangerouslySetInnerHTML={{ __html: themeInitScript }} />
      </head>
      <body>{children}</body>
    </html>
  );
}
'@
Write-LotFile 'react\src\app\(root)\page.tsx' @'
'use client';

import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useEffect } from 'react';
import { defaultLocale, isLocale, localeLabels, locales, type Locale } from '@/i18n/config';

function preferredLocale(): Locale {
  const langs = typeof navigator === 'undefined' ? [] : navigator.languages ?? [navigator.language];
  for (const tag of langs) {
    const base = tag.toLowerCase().split('-')[0] ?? '';
    if (isLocale(base)) return base;
  }
  return defaultLocale;
}

export default function RootPage() {
  const router = useRouter();

  useEffect(() => {
    router.replace(`/${preferredLocale()}/`);
  }, [router]);

  // Affiché uniquement si JavaScript est désactivé ou pendant la redirection.
  return (
    <main className="root-choice">
      <div>
        <p>Choisissez votre langue · Choose your language</p>
        <ul>
          {locales.map((l) => (
            <li key={l}>
              <Link className="btn btn-ghost" href={`/${l}/`} hrefLang={l} lang={l}>
                {localeLabels[l].name}
              </Link>
            </li>
          ))}
        </ul>
      </div>
    </main>
  );
}
'@
Write-LotFile 'react\src\app\[locale]\layout.tsx' @'
import type { Metadata, Viewport } from 'next';
import { notFound } from 'next/navigation';
import type { ReactNode } from 'react';
import { isLocale, locales } from '@/i18n/config';
import { getDictionary } from '@/i18n/dictionaries';
import { themeInitScript } from '@/lib/theme';
import '@fontsource/instrument-sans/400.css';
import '@fontsource/instrument-sans/500.css';
import '@fontsource/instrument-sans/600.css';
import '@fontsource/instrument-sans/700.css';
import '@fontsource/jetbrains-mono/400.css';
import '@fontsource/jetbrains-mono/500.css';
import '@/styles/globals.css';

type Params = Promise<{ locale: string }>;

export const dynamicParams = false;

export function generateStaticParams() {
  return locales.map((locale) => ({ locale }));
}

export async function generateMetadata({ params }: { params: Params }): Promise<Metadata> {
  const { locale } = await params;
  if (!isLocale(locale)) return {};
  const t = getDictionary(locale);
  return {
    metadataBase: new URL(process.env.NEXT_PUBLIC_SITE_URL ?? 'https://agenia.datacorners.com'),
    title: t.meta.title,
    description: t.meta.description,
    alternates: {
      languages: Object.fromEntries(locales.map((l) => [l, `/app/${l}/`])),
    },
    robots: { index: true, follow: true },
  };
}

export const viewport: Viewport = {
  width: 'device-width',
  initialScale: 1,
  themeColor: [
    { media: '(prefers-color-scheme: dark)', color: '#0b0b12' },
    { media: '(prefers-color-scheme: light)', color: '#f7f7fb' },
  ],
};

export default async function LocaleLayout({ children, params }: { children: ReactNode; params: Params }) {
  const { locale } = await params;
  if (!isLocale(locale)) notFound();

  return (
    <html lang={locale} data-theme="light" suppressHydrationWarning>
      <head>
        <script dangerouslySetInnerHTML={{ __html: themeInitScript }} />
      </head>
      <body>{children}</body>
    </html>
  );
}
'@
Write-LotFile 'react\src\app\[locale]\page.tsx' @'
import { notFound } from 'next/navigation';
import { AuditTrail } from '@/components/AuditTrail';
import { Icon } from '@/components/Icon';
import { SiteHeader } from '@/components/SiteHeader';
import { isLocale } from '@/i18n/config';
import { getDictionary } from '@/i18n/dictionaries';
import { phpHref } from '@/lib/php';

// Page publique : promesse, aperçu du produit, garanties en bénéfices.
// Volontairement aucun chiffre de marché, aucune mécanique interne, aucune feuille de route :
// le détail se découvre dans l'essai.

export default async function HomePage({ params }: { params: Promise<{ locale: string }> }) {
  const { locale } = await params;
  if (!isLocale(locale)) notFound();
  const t = getDictionary(locale);

  return (
    <>
      <a className="skip-link" href="#main">
        {t.nav.skip}
      </a>
      <SiteHeader locale={locale} t={t} />

      <main id="main">
        <div className="wrap">
          <section className="hero" aria-labelledby="hero-title">
            <div>
              <h1 id="hero-title">{t.hero.title}</h1>
              <p className="hero-lead">
                <strong>{t.hero.leadStrong}</strong> {t.hero.lead}
              </p>
              <div className="ctas">
                <a className="btn btn-primary" href={phpHref('guest', locale)}>
                  {t.hero.ctaPrimary}
                </a>
                <a className="btn btn-quiet" href="#controle">
                  {t.hero.ctaSecondary}
                </a>
              </div>
              <p className="reassure">{t.hero.reassure}</p>
            </div>
            <AuditTrail t={t.trail} />
          </section>
        </div>

        <section className="band" id="controle" aria-labelledby="promises-title">
          <div className="wrap">
            <header className="band-head">
              <h2 id="promises-title">{t.promises.title}</h2>
            </header>
            <ul className="promise-list">
              {t.promises.items.map((item) => (
                <li className="promise" key={item.id}>
                  <span className="promise-mark">
                    <Icon name="check" size={18} />
                  </span>
                  <div>
                    <h3>{item.title}</h3>
                    <p>{item.text}</p>
                  </div>
                </li>
              ))}
            </ul>
          </div>
        </section>

        <section className="team" id="equipes" aria-labelledby="team-title">
          <div className="wrap">
            <h2 id="team-title" className="team-statement">
              {t.team.statement}
            </h2>
            <div className="team-body">
              {t.team.points.map((p) => (
                <p key={p}>{p}</p>
              ))}
            </div>
          </div>
        </section>

        <section className="band" id="essai" aria-labelledby="trial-title">
          <div className="wrap trial">
            <div>
              <h2 id="trial-title">{t.trial.title}</h2>
              <p>{t.trial.text}</p>
            </div>
            <div className="ctas">
              <a className="btn btn-primary" href={phpHref('guest', locale)}>
                {t.trial.primary}
              </a>
              <a className="btn btn-quiet" href={phpHref('register', locale)}>
                {t.trial.secondary}
              </a>
            </div>
          </div>
        </section>
      </main>

      <footer className="site-footer">
        <div className="wrap footer-top">
          <span>{t.footer.tagline}</span>
          <span>{t.footer.license}</span>
          <a href={phpHref('privacy', locale)}>{t.footer.privacy}</a>
        </div>
      </footer>
    </>
  );
}
'@
Write-LotFile 'react\src\components\AuditTrail.tsx' @'
import type { CSSProperties } from 'react';
import type { Dictionary } from '@/i18n/dictionaries';
import { Icon } from './Icon';

type T = Dictionary['trail'];
type Plain = { title: string; detail: string; meta: string };

// Piste d'audit d'exemple. Rejouée une seule fois au chargement (CSS),
// état final affiché directement si l'utilisateur limite les animations.
// L'étape de consentement montre « en attente » puis la décision de la personne.

function stepStyle(i: number): CSSProperties {
  return { ['--i' as string]: i } as CSSProperties;
}

function PlainStep({ i, step }: { i: number; step: Plain }) {
  return (
    <li className="step" style={stepStyle(i)}>
      <span className="step-dot">
        <Icon name="check" size={12} />
      </span>
      <span>
        <span className="step-title">{step.title}</span>
        <span className="step-detail">{step.detail}</span>
      </span>
      <span className="step-meta">{step.meta}</span>
    </li>
  );
}

export function AuditTrail({ t }: { t: T }) {
  const c = t.consent;
  return (
    <figure className="trail" aria-labelledby="trail-title">
      <div className="trail-head">
        <h2 id="trail-title">{t.title}</h2>
        <span className="trail-tag">{t.tag}</span>
      </div>
      <p className="trail-ask">
        {t.asker} <span className="trail-q">{t.question}</span>
      </p>
      <ol className="trail-steps">
        <PlainStep i={0} step={t.rights} />
        <PlainStep i={1} step={t.model} />

        <li className="step" style={stepStyle(2)}>
          <span className="swap">
            <span className="step-dot is-before" aria-hidden="true">
              !
            </span>
            <span className="step-dot is-after">
              <Icon name="check" size={12} />
            </span>
          </span>
          <span>
            <span className="swap">
              <span className="step-title is-before" aria-hidden="true">
                {c.titlePending}
              </span>
              <span className="step-title is-after">{c.titleDone}</span>
            </span>
            <span className="step-detail">{c.detail}</span>
            <span className="swap">
              <span className="consent-actions is-before" aria-hidden="true">
                <span className="yes">{c.allow}</span>
                <span className="no">{c.deny}</span>
              </span>
              <span className="consent-by is-after">{c.by}</span>
            </span>
          </span>
          <span className="swap step-meta">
            <span className="is-before" aria-hidden="true">
              {c.metaPending}
            </span>
            <span className="is-after">{c.metaDone}</span>
          </span>
        </li>

        <PlainStep i={3} step={t.log} />
      </ol>
      <div className="trail-foot">
        <span>
          <b>{t.footDurationValue}</b>
          {t.footDuration}
        </span>
        <span>
          <b>{t.footCostValue}</b>
          {t.footCost}
        </span>
        <span>
          <b>{t.footHumanValue}</b>
          {t.footHuman}
        </span>
      </div>
    </figure>
  );
}
'@
Write-LotFile 'react\src\components\Icon.tsx' @'
// Jeu d'icônes SVG inline (aucune dépendance, aucun appel réseau).

const paths = {
  check: 'M4 10.5 8 14.5 16 6',
  arrow: 'M4 10h12M11 5l5 5-5 5',
  user: 'M10 10a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Zm-6 7c0-3 2.7-5 6-5s6 2 6 5',
  shield: 'M10 2.5 3.5 5v4.6c0 4 2.8 7 6.5 7.9 3.7-.9 6.5-3.9 6.5-7.9V5L10 2.5Z',
  server: 'M3.5 4h13v5h-13zM3.5 11h13v5h-13zM6.5 6.5h.01M6.5 13.5h.01',
  cloud: 'M6 15.5h8.5a3.5 3.5 0 0 0 .4-7A5 5 0 0 0 5.2 9.4 3 3 0 0 0 6 15.5Z',
  bolt: 'M11 2.5 4.5 11H10l-1 6.5L15.5 9H10l1-6.5Z',
  log: 'M5 3.5h10v13H5zM7.5 7h5M7.5 10h5M7.5 13h3',
  voice: 'M10 2.5a2.5 2.5 0 0 0-2.5 2.5v5a2.5 2.5 0 0 0 5 0V5A2.5 2.5 0 0 0 10 2.5ZM5 9.5a5 5 0 0 0 10 0M10 14.5v3',
  eye: 'M1.5 10S4.5 4.5 10 4.5 18.5 10 18.5 10 15.5 15.5 10 15.5 1.5 10 1.5 10Zm8.5 2.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z',
  license: 'M4 3.5h12v13H4zM7 7h6M7 10h6M10 13.5l1.5 1.5L14 12.5',
  sun: 'M10 13.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7ZM10 1.5v2M10 16.5v2M1.5 10h2M16.5 10h2M4 4l1.4 1.4M14.6 14.6 16 16M4 16l1.4-1.4M14.6 5.4 16 4',
  moon: 'M16.5 12A7 7 0 0 1 8 3.5a7 7 0 1 0 8.5 8.5Z',
} as const;

export type IconName = keyof typeof paths;

export function Icon({ name, size = 18 }: { name: IconName; size?: number }) {
  return (
    <svg
      width={size}
      height={size}
      viewBox="0 0 20 20"
      fill="none"
      stroke="currentColor"
      strokeWidth={1.6}
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
      focusable="false"
    >
      <path d={paths[name]} />
    </svg>
  );
}
'@
Write-LotFile 'react\src\components\SiteHeader.tsx' @'
import Link from 'next/link';
import { locales, localeLabels, type Locale } from '@/i18n/config';
import type { Dictionary } from '@/i18n/dictionaries';
import { phpHref } from '@/lib/php';
import { ThemeToggle } from './ThemeToggle';

export function SiteHeader({ locale, t }: { locale: Locale; t: Dictionary }) {
  return (
    <header className="site-header">
      <div className="wrap">
        <Link href={`/${locale}/`} className="logo" aria-label="Agenia">
          Agen<span>ia</span>
        </Link>

        <nav className="site-nav" aria-label="Navigation">
          <a href="#controle">{t.nav.control}</a>
          <a href="#equipes">{t.nav.team}</a>
          <a href="#essai">{t.nav.trial}</a>
        </nav>

        <div className="header-tools">
          <nav className="lang-switch" aria-label={t.nav.language}>
            {locales.map((l) => (
              <Link
                key={l}
                href={`/${l}/`}
                hrefLang={l}
                lang={l}
                aria-current={l === locale ? 'true' : undefined}
                title={localeLabels[l].name}
              >
                {localeLabels[l].short}
              </Link>
            ))}
          </nav>
          <ThemeToggle toLight={t.nav.themeToLight} toDark={t.nav.themeToDark} />
          <a className="btn btn-quiet btn-sm" href={phpHref('login', locale)}>
            {t.nav.login}
          </a>
        </div>
      </div>
    </header>
  );
}
'@
Write-LotFile 'react\src\components\ThemeToggle.tsx' @'
'use client';

import { useEffect, useState } from 'react';
import { Icon } from './Icon';

import { THEME_STORAGE_KEY } from '@/lib/theme';

type Theme = 'dark' | 'light';

export function ThemeToggle({ toLight, toDark }: { toLight: string; toDark: string }) {
  const [theme, setTheme] = useState<Theme | null>(null);

  useEffect(() => {
    const current = document.documentElement.getAttribute('data-theme');
    setTheme(current === 'light' ? 'light' : 'dark');
  }, []);

  function toggle() {
    const next: Theme = theme === 'light' ? 'dark' : 'light';
    document.documentElement.setAttribute('data-theme', next);
    try {
      localStorage.setItem(THEME_STORAGE_KEY, next);
    } catch {
      /* stockage indisponible : le thème vaut pour la session */
    }
    setTheme(next);
  }

  const label = theme === 'light' ? toDark : toLight;

  return (
    <button type="button" className="icon-btn" onClick={toggle} aria-label={label} title={label}>
      <Icon name={theme === 'light' ? 'moon' : 'sun'} />
    </button>
  );
}
'@
Write-LotFile 'react\src\i18n\config.ts' @'
// Langues du site. Pour ajouter l'allemand :
//   1. ajouter 'de' dans `locales` et son libellé dans `localeLabels`
//   2. créer src/i18n/messages/de.json (copie de fr.json, traduite)
//   3. l'importer dans src/i18n/dictionaries.ts
// TypeScript refuse la compilation tant qu'une clé manque.

export const locales = ['fr', 'en'] as const;
export type Locale = (typeof locales)[number];

export const defaultLocale: Locale = 'fr';

export const localeLabels: Record<Locale, { short: string; name: string }> = {
  fr: { short: 'FR', name: 'Français' },
  en: { short: 'EN', name: 'English' },
};

export function isLocale(value: string): value is Locale {
  return (locales as readonly string[]).includes(value);
}
'@
Write-LotFile 'react\src\i18n\dictionaries.ts' @'
import type { Locale } from './config';
import fr from './messages/fr.json';
import en from './messages/en.json';

// fr.json est la référence : chaque autre langue doit avoir exactement la même forme.
export type Dictionary = typeof fr;

const dictionaries: Record<Locale, Dictionary> = {
  fr,
  en,
};

export function getDictionary(locale: Locale): Dictionary {
  return dictionaries[locale];
}
'@
Write-LotFile 'react\src\i18n\messages\en.json' @'
{
  "meta": {
    "title": "Agenia — AI at work, under your control",
    "description": "Agenia puts AI agents to work for your teams: the agent prepares, a person decides. Your data can stay on your own servers."
  },
  "nav": {
    "skip": "Skip to content",
    "control": "Guarantees",
    "team": "Your teams",
    "trial": "Trial",
    "login": "Sign in",
    "language": "Language",
    "themeToLight": "Switch to light theme",
    "themeToDark": "Switch to dark theme"
  },
  "hero": {
    "title": "Every action your AI agents take, visible, approved and accounted for.",
    "leadStrong": "The agent prepares, your team decides.",
    "lead": "Agenia puts AI to work in your company, without anyone losing control.",
    "ctaPrimary": "Try it for 20 minutes",
    "ctaSecondary": "See the guarantees",
    "reassure": "No sign-up, no credit card."
  },
  "trail": {
    "title": "Audit trail",
    "tag": "Example",
    "asker": "Karim, IT team:",
    "question": "“Check the server and tell me what's wrong.”",
    "rights": {
      "title": "Permissions checked",
      "detail": "Karim is allowed to make this request",
      "meta": "2 ms"
    },
    "model": {
      "title": "Local model",
      "detail": "The request never leaves your servers",
      "meta": "3.4 s"
    },
    "consent": {
      "titlePending": "Approval requested from Karim",
      "titleDone": "Approval given by Karim",
      "detail": "Read the server status",
      "allow": "Allow",
      "deny": "Deny",
      "by": "Decision made by Karim, not by the agent",
      "metaPending": "waiting",
      "metaDone": "approved"
    },
    "log": {
      "title": "Recorded",
      "detail": "Retrievable at any time",
      "meta": ""
    },
    "footDuration": "total time",
    "footDurationValue": "3.4 s",
    "footCost": "cloud cost",
    "footCostValue": "€0.00",
    "footHuman": "human decision",
    "footHumanValue": "1"
  },
  "promises": {
    "title": "AI at work, without losing control.",
    "items": [
      {
        "id": "consent",
        "title": "Nothing is changed without your approval.",
        "text": "The agent reads and analyses freely. Before changing anything, it shows what will change and waits for your approval."
      },
      {
        "id": "stop",
        "title": "An off switch, always.",
        "text": "An administrator can switch an agent off at any time."
      },
      {
        "id": "data",
        "title": "Your data stays with you.",
        "text": "Agenia can run entirely on your own servers. The cloud only if you choose it."
      },
      {
        "id": "trace",
        "title": "Everything can be traced.",
        "text": "Every action leaves a record. You know what was done, and when."
      }
    ]
  },
  "team": {
    "statement": "An AI agent doesn't replace your team. It works for it.",
    "points": [
      "The agent suggests, the person decides. Nothing is changed on their behalf without their approval.",
      "Everyone keeps their role and their way of working. The tool fits the team you already have, not the other way round."
    ]
  },
  "trial": {
    "title": "Try it with your team",
    "text": "20 minutes of full access, with no sign-up and no credit card.",
    "primary": "Try it for 20 minutes",
    "secondary": "Create an account"
  },
  "footer": {
    "tagline": "Agenia, local and cloud AI interface",
    "license": "Open source (AGPL) or commercial licence",
    "privacy": "Privacy (GDPR)"
  }
}
'@
Write-LotFile 'react\src\i18n\messages\fr.json' @'
{
  "meta": {
    "title": "Agenia — l'IA au travail, sous votre contrôle",
    "description": "Agenia met des agents IA au service de vos équipes : l'agent prépare, une personne décide. Vos données peuvent rester sur vos serveurs."
  },
  "nav": {
    "skip": "Aller au contenu",
    "control": "Garanties",
    "team": "Vos équipes",
    "trial": "Essai",
    "login": "Connexion",
    "language": "Langue",
    "themeToLight": "Passer en thème clair",
    "themeToDark": "Passer en thème sombre"
  },
  "hero": {
    "title": "Chaque action de vos agents IA, visible, autorisée et chiffrée.",
    "leadStrong": "L'agent prépare, votre équipe décide.",
    "lead": "Agenia met l'IA au travail dans votre entreprise, sans que personne ne perde la main.",
    "ctaPrimary": "Essayer 20 minutes",
    "ctaSecondary": "Voir les garanties",
    "reassure": "Sans inscription ni carte bancaire."
  },
  "trail": {
    "title": "Piste d'audit",
    "tag": "Exemple",
    "asker": "Karim, équipe IT :",
    "question": "« Vérifie l'état du serveur et dis-moi ce qui cloche. »",
    "rights": {
      "title": "Droits vérifiés",
      "detail": "Karim peut lancer cette demande",
      "meta": "2 ms"
    },
    "model": {
      "title": "Modèle local",
      "detail": "La requête ne quitte pas vos serveurs",
      "meta": "3,4 s"
    },
    "consent": {
      "titlePending": "Accord demandé à Karim",
      "titleDone": "Accord donné par Karim",
      "detail": "Lire l'état du serveur",
      "allow": "Autoriser",
      "deny": "Refuser",
      "by": "Décision prise par Karim, pas par l'agent",
      "metaPending": "en attente",
      "metaDone": "autorisé"
    },
    "log": {
      "title": "Tracé",
      "detail": "Retrouvable à tout moment",
      "meta": ""
    },
    "footDuration": "durée totale",
    "footDurationValue": "3,4 s",
    "footCost": "coût cloud",
    "footCostValue": "0,00 €",
    "footHuman": "décision humaine",
    "footHumanValue": "1"
  },
  "promises": {
    "title": "L'IA au travail, sans perdre la main.",
    "items": [
      {
        "id": "consent",
        "title": "Rien n'est modifié sans votre accord.",
        "text": "L'agent lit et analyse librement. Avant de modifier quoi que ce soit, il montre ce qui va changer et attend votre accord."
      },
      {
        "id": "stop",
        "title": "Un interrupteur, toujours.",
        "text": "Un administrateur peut couper un agent à tout moment."
      },
      {
        "id": "data",
        "title": "Vos données restent chez vous.",
        "text": "Agenia peut fonctionner entièrement sur vos serveurs. Le cloud, seulement si vous le choisissez."
      },
      {
        "id": "trace",
        "title": "Tout est retrouvable.",
        "text": "Chaque action laisse une trace. Vous savez ce qui a été fait, et quand."
      }
    ]
  },
  "team": {
    "statement": "Un agent IA ne remplace pas votre équipe. Il travaille pour elle.",
    "points": [
      "L'agent propose, la personne décide. Rien n'est modifié en son nom sans son accord.",
      "Chacun garde son rôle et sa façon de travailler. L'outil s'adapte à l'équipe en place, pas l'inverse."
    ]
  },
  "trial": {
    "title": "Essayez avec votre équipe",
    "text": "20 minutes d'accès complet, sans inscription ni carte bancaire.",
    "primary": "Essayer 20 minutes",
    "secondary": "Créer un compte"
  },
  "footer": {
    "tagline": "Agenia, interface IA locale et cloud",
    "license": "Open source (AGPL) ou licence commerciale",
    "privacy": "Confidentialité (RGPD)"
  }
}
'@
Write-LotFile 'react\src\lib\php.ts' @'
import type { Locale } from '@/i18n/config';

// Origine du backend PHP. Vide en production (même domaine que les pages).
const PHP_ORIGIN = (process.env.NEXT_PUBLIC_PHP_ORIGIN ?? '').replace(/\/+$/, '');

// Pages PHP existantes vers lesquelles le site renvoie. Liste fermée : aucune URL libre.
const PHP_PAGES = {
  login: '/login.php',
  register: '/register.php',
  guest: '/guest-init.php',
  privacy: '/rgpd.php',
} as const;

export type PhpPage = keyof typeof PHP_PAGES;

export function phpHref(page: PhpPage, locale: Locale): string {
  const url = `${PHP_ORIGIN}${PHP_PAGES[page]}`;
  return `${url}?lang=${encodeURIComponent(locale)}`;
}
'@
Write-LotFile 'react\src\lib\theme.ts' @'
// Partagé entre le layout (serveur) et ThemeToggle (client).
export const THEME_STORAGE_KEY = 'agenia-theme';

// Exécuté avant l'affichage pour éviter le flash de thème.
export const themeInitScript = `(function(){try{var t=localStorage.getItem('${THEME_STORAGE_KEY}');if(t!=='light'&&t!=='dark'){t=window.matchMedia('(prefers-color-scheme: light)').matches?'light':'dark'}document.documentElement.setAttribute('data-theme',t)}catch(e){document.documentElement.setAttribute('data-theme','dark')}})();`;
'@
Write-LotFile 'react\src\styles\globals.css' @'
/* =============================================================================
   Agenia — charte « papier d'audit »
   Les couleurs ont un sens : vert = autorisé, ambre = en attente d'accord,
   rouge = bloqué, violet = action de l'utilisateur. Rien n'est décoratif.
   ============================================================================= */

:root,
:root[data-theme='light'] {
  color-scheme: light;
  --paper: #f5f6f8;
  --sheet: #ffffff;
  --ink: #1b1f3b;
  --ink-2: #4a5070;
  --ink-3: #6b7190;
  --rule: #dcdfe7;
  --action: #0b63ce;
  --action-hover: #0950a8;
  --line: #0c4a9e;
  --action-ink: #ffffff;
  --ok: #0f7b5f;
  --ok-bg: #e3f3ee;
  --hold: #b45309;
  --hold-bg: #fcefd9;
  --block: #b42318;
  --focus: #0c4a9e;
  --shadow: 0 1px 0 var(--rule), 0 24px 48px -24px rgba(27, 31, 59, 0.25);
}

:root[data-theme='dark'] {
  color-scheme: dark;
  --paper: #0f1226;
  --sheet: #151935;
  --ink: #e8eaf4;
  --ink-2: #b3b8d0;
  --ink-3: #8f95b2;
  --rule: #2a2f52;
  --action: #1f6feb;
  --action-hover: #2f7bde;
  --line: #6ea8fe;
  --action-ink: #ffffff;
  --ok: #3cc59b;
  --ok-bg: rgba(60, 197, 155, 0.14);
  --hold: #f0a54a;
  --hold-bg: rgba(240, 165, 74, 0.14);
  --block: #f2756a;
  --focus: #6ea8fe;
  --shadow: 0 1px 0 var(--rule), 0 24px 48px -24px rgba(0, 0, 0, 0.6);
}

:root {
  --sans: 'Instrument Sans', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
  --data: 'JetBrains Mono', ui-monospace, 'Cascadia Code', Consolas, monospace;
  --max: 1180px;
  --gutter: clamp(16px, 4vw, 32px);
  --radius: 8px;
}

*,
*::before,
*::after {
  box-sizing: border-box;
}

html {
  -webkit-text-size-adjust: 100%;
  scroll-behavior: smooth;
  scroll-padding-top: 88px;
}

body {
  margin: 0;
  background: var(--paper);
  color: var(--ink);
  font-family: var(--sans);
  font-size: 17px;
  line-height: 1.6;
  -webkit-font-smoothing: antialiased;
  overflow-x: hidden;
}

h1,
h2,
h3,
h4 {
  margin: 0;
  font-weight: 600;
  text-wrap: balance;
}

p {
  margin: 0;
  text-wrap: pretty;
}

a {
  color: inherit;
}

:focus-visible {
  outline: 2px solid var(--focus);
  outline-offset: 3px;
  border-radius: 4px;
}

.wrap {
  width: 100%;
  max-width: var(--max);
  margin-inline: auto;
  padding-inline: var(--gutter);
}

.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}

.skip-link {
  position: absolute;
  left: var(--gutter);
  top: -100px;
  z-index: 100;
  background: var(--action);
  color: var(--action-ink);
  padding: 8px 14px;
  border-radius: var(--radius);
  text-decoration: none;
}
.skip-link:focus {
  top: 12px;
}

/* ── Boutons ─────────────────────────────────────────────────────────────── */

.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 46px;
  padding: 0 22px;
  border-radius: var(--radius);
  font: 600 16px/1.2 var(--sans);
  text-decoration: none;
  cursor: pointer;
  border: 0;
  transition: background-color 160ms ease, box-shadow 160ms ease;
}
.btn-primary {
  background: var(--action);
  color: var(--action-ink);
  box-shadow: inset 0 0 0 1.5px var(--line);
}
.btn-primary:hover {
  background: var(--action-hover);
}
.btn-quiet {
  background: transparent;
  color: var(--ink);
  box-shadow: inset 0 0 0 1.5px var(--ink);
}
.btn-quiet:hover {
  box-shadow: inset 0 0 0 2px var(--ink);
}
.btn-sm {
  min-height: 40px;
  padding: 0 16px;
  font-size: 15px;
}

/* ── En-tête ─────────────────────────────────────────────────────────────── */

.site-header {
  position: sticky;
  top: 0;
  z-index: 50;
  background: color-mix(in srgb, var(--paper) 88%, transparent);
  backdrop-filter: blur(10px);
}
.site-header .wrap {
  display: flex;
  align-items: center;
  gap: 24px;
  height: 72px;
}
.logo {
  font-weight: 700;
  font-size: 20px;
  letter-spacing: -0.02em;
  text-decoration: none;
  margin-right: auto;
}
.logo span {
  color: var(--action);
}
.site-nav {
  display: flex;
  gap: 22px;
}
.site-nav a {
  text-decoration: none;
  color: var(--ink-2);
  font-size: 15px;
}
.site-nav a:hover {
  color: var(--ink);
}
.header-tools {
  display: flex;
  align-items: center;
  gap: 14px;
}
.lang-switch {
  display: flex;
  gap: 4px;
  font-size: 14px;
}
.lang-switch a {
  text-decoration: none;
  color: var(--ink-3);
  padding: 4px 2px;
}
.lang-switch a[aria-current='true'] {
  color: var(--ink);
  font-weight: 600;
}
.icon-btn {
  display: inline-grid;
  place-items: center;
  width: 40px;
  height: 40px;
  border-radius: var(--radius);
  border: 0;
  background: transparent;
  color: var(--ink-2);
  cursor: pointer;
}
.icon-btn:hover {
  color: var(--ink);
  background: var(--sheet);
}

@media (max-width: 880px) {
  .site-nav {
    display: none;
  }
}

/* ── Accroche ────────────────────────────────────────────────────────────── */

.hero {
  display: grid;
  grid-template-columns: 1.05fr 0.95fr;
  gap: clamp(32px, 5vw, 64px);
  align-items: center;
  padding-block: clamp(40px, 7vw, 88px) clamp(56px, 8vw, 104px);
}
.hero h1 {
  font-size: clamp(38px, 5.6vw, 64px);
  line-height: 1.03;
  letter-spacing: -0.035em;
  margin-bottom: 24px;
}
.hero-lead {
  font-size: clamp(18px, 1.8vw, 20px);
  color: var(--ink-2);
  max-width: 34ch;
  margin-bottom: 32px;
}
.hero-lead strong {
  color: var(--ink);
  font-weight: 600;
}
.ctas {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}
.reassure {
  margin-top: 18px;
  font-size: 14px;
  color: var(--ink-3);
}

@media (max-width: 960px) {
  .hero {
    grid-template-columns: 1fr;
  }
}

/* ── Piste d'audit (exemple rejoué une fois au chargement) ─────────────────── */

.trail {
  margin: 0;
  background: var(--sheet);
  border-radius: 14px;
  box-shadow: var(--shadow);
  overflow: hidden;
}
.trail-head {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  gap: 12px;
  padding: 18px 22px;
  border-bottom: 1px solid var(--rule);
}
.trail-head h2 {
  font-size: 15px;
}
.trail-tag {
  font-size: 13px;
  color: var(--ink-3);
}
.trail-ask {
  padding: 16px 22px;
  border-bottom: 1px solid var(--rule);
  font-size: 15px;
  color: var(--ink-2);
}
.trail-q {
  color: var(--ink);
}
.trail-steps {
  list-style: none;
  margin: 0;
  padding: 8px 0;
}
.step {
  display: grid;
  grid-template-columns: 22px 1fr auto;
  gap: 12px;
  align-items: start;
  padding: 10px 22px;
  position: relative;
}
.step::before {
  content: '';
  position: absolute;
  left: 32px;
  top: 32px;
  bottom: -10px;
  width: 1.5px;
  background: var(--rule);
}
.step:last-child::before {
  display: none;
}
.step-dot {
  width: 20px;
  height: 20px;
  border-radius: 50%;
  display: grid;
  place-items: center;
  position: relative;
  z-index: 1;
  background: var(--ok-bg);
  color: var(--ok);
}
.step-title {
  font-size: 15px;
  font-weight: 600;
  line-height: 1.35;
}
.step-detail {
  display: block;
  font-size: 13.5px;
  color: var(--ink-3);
}
.step-meta {
  font-family: var(--data);
  font-size: 12px;
  color: var(--ink-3);
  white-space: nowrap;
  padding-top: 2px;
}

/* Étape de consentement : « en attente » puis « autorisé par … » */
.swap {
  display: grid;
}
.swap > * {
  grid-area: 1 / 1;
}
.swap .is-after {
  visibility: visible;
}
.swap .is-before {
  visibility: hidden;
}
.step-dot.is-before {
  background: var(--hold-bg);
  color: var(--hold);
}
.step-title.is-before {
  color: var(--hold);
}
.consent-actions {
  display: flex;
  gap: 8px;
  margin-top: 8px;
}
.consent-actions span {
  font-size: 13px;
  font-weight: 600;
  padding: 5px 12px;
  border-radius: 6px;
}
.consent-actions .yes {
  background: var(--ok);
  color: #fff;
}
.consent-actions .no {
  box-shadow: inset 0 0 0 1px var(--rule);
  color: var(--ink-2);
}
.consent-by {
  margin-top: 8px;
  font-size: 13.5px;
  font-weight: 600;
  color: var(--ok);
}

.trail-foot {
  display: flex;
  flex-wrap: wrap;
  gap: 12px 28px;
  padding: 14px 22px;
  background: var(--paper);
  border-top: 1px solid var(--rule);
  font-size: 13px;
  color: var(--ink-2);
}
.trail-foot b {
  display: block;
  font-family: var(--data);
  font-weight: 500;
  font-size: 14px;
  color: var(--ink);
}

@media (prefers-reduced-motion: no-preference) {
  .step {
    animation: step-in 420ms ease both;
    animation-delay: calc(var(--i) * 420ms + 250ms);
  }
  .swap .is-before {
    animation: swap-out 2400ms linear both;
    animation-delay: calc(var(--i) * 420ms + 250ms);
  }
  .swap .is-after {
    animation: swap-in 2400ms linear both;
    animation-delay: calc(var(--i) * 420ms + 250ms);
  }
}
@keyframes step-in {
  from {
    opacity: 0;
    transform: translateY(6px);
  }
  to {
    opacity: 1;
    transform: none;
  }
}
@keyframes swap-out {
  0%,
  88% {
    visibility: visible;
  }
  100% {
    visibility: hidden;
  }
}
@keyframes swap-in {
  0%,
  88% {
    visibility: hidden;
  }
  100% {
    visibility: visible;
  }
}

/* ── Sections ────────────────────────────────────────────────────────────── */

.band {
  padding-block: clamp(56px, 8vw, 96px);
  border-top: 1px solid var(--rule);
}
.band-head {
  max-width: 680px;
  margin-bottom: clamp(32px, 5vw, 48px);
}
.band-head h2 {
  font-size: clamp(28px, 3.6vw, 40px);
  line-height: 1.1;
  letter-spacing: -0.03em;
  margin-bottom: 12px;
}
.band-head p {
  color: var(--ink-2);
}

/* Garanties : une affirmation, une phrase. Pas de chiffres, pas de mécanique. */
.promise-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  column-gap: clamp(24px, 5vw, 72px);
}
.promise {
  padding-block: 28px;
  border-top: 1px solid var(--rule);
  display: grid;
  grid-template-columns: 22px 1fr;
  gap: 14px;
}
.promise-mark {
  color: var(--ok);
  padding-top: 5px;
}
.promise h3 {
  font-size: clamp(20px, 2.2vw, 24px);
  letter-spacing: -0.015em;
  line-height: 1.25;
  margin-bottom: 6px;
}
.promise p {
  color: var(--ink-2);
}
@media (max-width: 760px) {
  .promise-list {
    grid-template-columns: 1fr;
  }
}

/* L'équipe : déclaration pleine largeur, sans carte */
.team {
  padding-block: clamp(64px, 9vw, 112px);
  border-top: 1px solid var(--rule);
}
.team-statement {
  font-size: clamp(26px, 3.4vw, 40px);
  line-height: 1.2;
  letter-spacing: -0.025em;
  font-weight: 500;
  max-width: 26ch;
}
.team-body {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(min(100%, 280px), 1fr));
  gap: 20px 40px;
  margin-top: 36px;
  max-width: 920px;
}
.team-body p {
  color: var(--ink-2);
}

/* ── Essai ───────────────────────────────────────────────────────────────── */

.trial {
  display: grid;
  grid-template-columns: 1fr auto;
  align-items: end;
  gap: 24px 48px;
}
.trial h2 {
  font-size: clamp(28px, 3.6vw, 40px);
  letter-spacing: -0.03em;
  line-height: 1.1;
  margin-bottom: 10px;
}
.trial p {
  color: var(--ink-2);
  max-width: 48ch;
}
@media (max-width: 760px) {
  .trial {
    grid-template-columns: 1fr;
  }
}

/* ── Pied de page ────────────────────────────────────────────────────────── */

.site-footer {
  border-top: 1px solid var(--rule);
  padding-block: 32px 40px;
  font-size: 14px;
  color: var(--ink-3);
}
.footer-top {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 12px 24px;
}
.footer-top a {
  color: var(--ink-2);
}

/* ── Page racine (choix de langue) ───────────────────────────────────────── */

.root-choice {
  min-height: 100vh;
  display: grid;
  place-items: center;
  text-align: center;
  padding: var(--gutter);
}
.root-choice ul {
  list-style: none;
  padding: 0;
  margin: 16px 0 0;
  display: flex;
  gap: 12px;
  justify-content: center;
}

@media (prefers-reduced-motion: reduce) {
  html {
    scroll-behavior: auto;
  }
}
'@
Write-LotFile 'react\tsconfig.json' @'
{
  "compilerOptions": {
    "target": "ES2020",
    "lib": ["dom", "dom.iterable", "esnext"],
    "allowJs": false,
    "skipLibCheck": true,
    "strict": true,
    "noUncheckedIndexedAccess": true,
    "noEmit": true,
    "esModuleInterop": true,
    "module": "esnext",
    "moduleResolution": "bundler",
    "resolveJsonModule": true,
    "isolatedModules": true,
    "jsx": "preserve",
    "incremental": true,
    "forceConsistentCasingInFileNames": true,
    "plugins": [{ "name": "next" }],
    "paths": { "@/*": ["./src/*"] }
  },
  "include": ["next-env.d.ts", "**/*.ts", "**/*.tsx", ".next/types/**/*.ts"],
  "exclude": ["node_modules", "out"]
}
'@
Write-LotFile 'react\web.config' @'
<?xml version="1.0" encoding="UTF-8"?>
<!-- Dossier source Next.js : aucun fichier ne doit être servi par IIS. -->
<configuration>
  <system.webServer>
    <directoryBrowse enabled="false" />
    <security>
      <requestFiltering>
        <fileExtensions allowUnlisted="false">
          <clear />
        </fileExtensions>
        <hiddenSegments>
          <add segment="node_modules" />
          <add segment="src" />
          <add segment="out" />
          <add segment=".next" />
        </hiddenSegments>
      </requestFiltering>
    </security>
  </system.webServer>
</configuration>
'@

# Fichiers des versions précédentes devenus inutiles (uniquement dans react\)
foreach ($old in @('react\src\components\FlowDiagram.tsx', 'react\src\components\CockpitPreview.tsx')) {
  $full = Join-Path $base $old
  if (Test-Path $full) { Remove-Item $full; Write-Host "supprimé (obsolète) : $old" }
}

Write-Host ''
Write-Host "Lot terminé : 24 fichiers dans $base\react" -ForegroundColor Green
