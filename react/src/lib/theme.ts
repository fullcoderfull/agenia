// Partagé entre le layout (serveur) et ThemeToggle (client).
export const THEME_STORAGE_KEY = 'agenia-theme';

// Exécuté avant l'affichage pour éviter le flash de thème.
export const themeInitScript = `(function(){try{var t=localStorage.getItem('${THEME_STORAGE_KEY}');if(t!=='light'&&t!=='dark'){t=window.matchMedia('(prefers-color-scheme: light)').matches?'light':'dark'}document.documentElement.setAttribute('data-theme',t)}catch(e){document.documentElement.setAttribute('data-theme','dark')}})();`;
