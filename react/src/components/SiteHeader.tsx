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
