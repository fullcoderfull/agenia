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
