# Agenia

Assistant IA pour décideurs : chat, voix et agent, avec un journal d'audit de chaque action.
L'interface est en **Next.js** (export statique), l'API en **PHP**, le tout servi par **IIS**.

> **Dépôt privé — confidentiel.** Architecture complète, flux, sécurité et historique des lots :
> [`docs/CARTOGRAPHIE-AGENIA.md`](docs/CARTOGRAPHIE-AGENIA.md).

## Structure

```
web-dev\            racine servie par IIS (hôte agent.datacorners.com)
├─ backend\api.php  routeur de l'API
├─ check.php        authentification (cookie HMAC, invité, partenaire)
├─ proxy-*.php      LLM, terminal (agent), synthèse vocale
├─ lib\             audit, plan PRO, base de données…
├─ config\          settings.json (versionné) + secrets (NON versionnés)
├─ react\           sources du front Next.js
└─ app\             front compilé (généré, non versionné)
```

## Installation sur un nouveau serveur

1. **Cloner** le dépôt dans le dossier servi par IIS.
2. **Recréer les fichiers secrets** à partir des modèles, puis les remplir :
   ```powershell
   Copy-Item .\config\secrets.example.php .\config\secrets.php
   Copy-Item .\config\users.example.php   .\config\users.php
   ```
   `config/partners.php` et `data/` ne sont pas versionnés : les reprendre de l'ancien serveur.
3. **Compiler et publier le front** dans `app\` :
   ```powershell
   $web = (Get-Location).Path
   Push-Location .\react ; npm install ; $env:AGENIA_WEB_ROOT = $web ; npm run deploy ; Pop-Location
   ```
4. Vérifier : `https://<hôte>/app/fr/` (accueil) et `https://<hôte>/demo.php` (démonstration).

## Règles de contribution

- **Aucun secret dans le code.** Clés et mots de passe vont dans `config/secrets.php`,
  `config/users.php` ou `data/` (tous ignorés par Git).
- **Aucun nom de technologie, aucune URL ni IP interne côté front.** Le navigateur ne voit
  que `local` / `cloud`.
- Avant chaque `git push` : `git status` et la recherche de secrets décrite dans la cartographie.
