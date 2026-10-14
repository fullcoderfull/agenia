import type { Locale } from '@/i18n/config';

// Origine du backend PHP. Vide en production (même domaine que les pages).
const PHP_ORIGIN = (process.env.NEXT_PUBLIC_PHP_ORIGIN ?? '').replace(/\/+$/, '');

// Pages PHP existantes vers lesquelles le site renvoie. Liste fermée : aucune URL libre.
const PHP_PAGES = {
  login: '/login.php',
  register: '/register.php',
  guest: '/guest-init.php',
  demo: '/demo.php',
  privacy: '/rgpd.php',
} as const;

export type PhpPage = keyof typeof PHP_PAGES;

export function phpHref(page: PhpPage, locale: Locale): string {
  const url = `${PHP_ORIGIN}${PHP_PAGES[page]}`;
  return `${url}?lang=${encodeURIComponent(locale)}`;
}

// Fichiers statiques servis par le site PHP (même domaine). Liste fermée.
const PHP_ASSETS = {
  demoVideoFr: '/assets/video/agenia-teaser-fr.mp4',
  demoVideoEn: '/assets/video/agenia-teaser-en.mp4',
  demoPosterFr: '/assets/video/agenia-teaser-fr.jpg',
  demoPosterEn: '/assets/video/agenia-teaser-en.jpg',
} as const;

export function phpAsset(asset: keyof typeof PHP_ASSETS): string {
  return `${PHP_ORIGIN}${PHP_ASSETS[asset]}`;
}
