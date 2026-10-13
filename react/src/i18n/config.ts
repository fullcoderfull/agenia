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
