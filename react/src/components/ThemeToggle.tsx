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
