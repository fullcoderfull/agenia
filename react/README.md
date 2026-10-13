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
