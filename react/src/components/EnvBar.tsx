'use client';

import { useEffect, useState, type ReactNode } from 'react';
import type { Locale } from '@/i18n/config';
import type { EnvStrings } from '@/i18n/app-strings';
import Link from 'next/link';
import { isLocalProvider, type Environment, type PlanInfo } from '@/lib/apiClient';
import { ThemeToggle } from './ThemeToggle';

// Barre d'environnement : à tout moment, qui je suis, où vont mes données,
// ce qu'il me reste, ce que l'agent peut faire. Chaque bloc a une explication.

function Item({ icon, tone, k, children, tip }: { icon: string; tone: string; k: string; children: ReactNode; tip: string }) {
  return (
    <div className="env" data-tone={tone}>
      <span className="env-ic" aria-hidden="true">
        {icon}
      </span>
      <span className="env-body">
        <span className="env-k">{k}</span>
        <span className="env-v">{children}</span>
      </span>
      <span className="env-q" tabIndex={0} role="note" aria-label={tip}>
        ?
        <span className="env-tip">{tip}</span>
      </span>
    </div>
  );
}

function Countdown({ expiresAt }: { expiresAt: number }) {
  const [now, setNow] = useState(() => Math.floor(Date.now() / 1000));
  useEffect(() => {
    const id = setInterval(() => setNow(Math.floor(Date.now() / 1000)), 1000);
    return () => clearInterval(id);
  }, []);
  const left = Math.max(0, expiresAt - now);
  const m = Math.floor(left / 60);
  const s = left % 60;
  return (
    <span className="mono">
      {m}:{s.toString().padStart(2, '0')}
    </span>
  );
}

const PHP = (process.env.NEXT_PUBLIC_PHP_ORIGIN ?? '').replace(/\/+$/, '');

export function EnvBar({
  env,
  loading,
  locale,
  t,
  plan,
  onSettings,
}: {
  env: Environment | null;
  loading: boolean;
  locale: Locale;
  t: EnvStrings;
  plan: PlanInfo | null;
  onSettings?: () => void;
}) {
  const role = env?.role ?? 'guest';
  const roleName = t.roleNames[role] ?? role;
  const roleTip = t.roleTip[role] ?? t.roleTip._default ?? '';

  const llm = env?.providers.llm;
  const activeSlug = llm?.active ?? '';
  const activeProv = activeSlug ? llm?.list[activeSlug] : undefined;
  const local = activeProv ? isLocalProvider(activeSlug, activeProv.requiresKey) : true;

  const guest = env?.guest ?? null;

  return (
    <header className="envbar">
      <span className="brand">
        Agen<span>ia</span>
      </span>

      {env?.demo && (
        <span className="env-demo" role="note" tabIndex={0} aria-label={t.demoBadgeTip}>
          {t.demoBadge}
          <span className="env-tip">{t.demoBadgeTip}</span>
        </span>
      )}

      {loading ? (
        <span className="env-loading">{t.loading}</span>
      ) : (
        <>
          <Item icon="👤" tone="ok" k={t.roleLabel} tip={roleTip}>
            {roleName}
          </Item>

          <Item icon={local ? '🖥️' : '☁️'} tone={local ? 'local' : 'cloud'} k={t.dataKey} tip={local ? t.dataTipLocal : t.dataTipCloud}>
            <span className={`pill ${local ? 'local' : 'cloud'}`}>
              <span className="pill-dot" aria-hidden="true" />
              {local ? t.local : t.cloud}
            </span>
          </Item>

          {guest && (
            <Item icon="⏳" tone="hold" k={t.leftKey} tip={t.leftTip}>
              <Countdown expiresAt={guest.expiresAt} /> · {Math.max(0, guest.maxMessages - guest.messagesUsed)}/{guest.maxMessages} {t.msgShort}
            </Item>
          )}

          <Item icon="⚡" tone={env?.agentEnabled ? 'cloud' : 'muted'} k={t.agentKey} tip={env?.agentEnabled ? t.agentTipOn : t.agentTipOff}>
            {env?.agentEnabled ? t.agentOn : t.agentOff}
          </Item>
        </>
      )}

      <span className="env-spacer" />
      {plan?.upgrade && (
        <a className="env-upgrade" href={plan.upgrade.href} title={plan.upgrade.kind === 'pro' ? t.upgradeProTip : t.createAccountTip}>
          <svg className="env-upgrade-ic" viewBox="0 0 20 20" width="15" height="15" aria-hidden="true">
            <path d="M10 1.8l1.9 5.1 5.3 1.1-5.3 1.9L10 15.2l-1.9-5.3L2.8 8l5.3-1.1z" fill="currentColor" />
            <path d="M16 13.5l.7 1.8 1.8.7-1.8.7-.7 1.8-.7-1.8-1.8-.7 1.8-.7z" fill="currentColor" opacity=".7" />
          </svg>
          <span>{t.upgradePro}</span>
        </a>
      )}
      {onSettings && (
        <button type="button" className="env-settings" onClick={onSettings}>
          <span aria-hidden="true">⚙</span> {t.settings.title}
        </button>
      )}
      {role === 'superadmin' && (
        <a className="env-link" href={`${PHP}/superadmin-front.php`}>
          <span aria-hidden="true">⚡</span> {t.superAdmin}
        </a>
      )}
      {/* Journal d'audit = fonctionnalité PRO (vitrine visible en démo).
          Sans accès : visible mais grisé, avec invitation ; le clic mène à la page
          d'invitation du journal (créer un compte / passer à PRO). */}
      {env?.demo || plan?.paid ? (
        <a className="env-link" href={`${PHP}/superadmin-journal.php`}>
          <span aria-hidden="true">🔍</span> {t.auditJournal}
        </a>
      ) : (
        !loading &&
        (plan !== null || role === 'guest') && (
          <a
            className="env-link env-locked"
            href={`${PHP}/superadmin-journal.php`}
            aria-label={`${t.auditJournal} — ${role === 'guest' ? t.proLockGuest : t.proLockFree}`}
          >
            <span aria-hidden="true">🔒</span> {t.auditJournal} <span className="env-pro">{t.proTag}</span>
            <span className="env-tip">{role === 'guest' ? t.proLockGuest : t.proLockFree}</span>
          </a>
        )
      )}
      <Link className="env-lang" href={`/${locale === 'fr' ? 'en' : 'fr'}/chat/`} hrefLang={locale === 'fr' ? 'en' : 'fr'} aria-label={t.langSwitch}>
        {locale === 'fr' ? 'EN' : 'FR'}
      </Link>
      <ThemeToggle toLight={t.themeToLight} toDark={t.themeToDark} />
      <a className="env-logout" href={`${PHP}/logout.php?lang=${locale}`} aria-label={t.logout} title={t.logout}>
        <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
          <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
          <polyline points="16 17 21 12 16 7" />
          <line x1="21" y1="12" x2="9" y2="12" />
        </svg>
      </a>
    </header>
  );
}
