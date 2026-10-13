# Déploiement — Agenia

Agenia s'installe sur **IIS (Windows)** ou **Apache 2.4 (Linux)**.
L'interface est un export statique Next.js (`app/`), l'API est en PHP ; aucun serveur Node.js
n'est nécessaire en production.

## Prérequis communs

| Élément | Version |
|---|---|
| PHP | 8.0 minimum, 8.2 recommandé (extensions : `pdo_mysql`, `mbstring`, `openssl`, `curl`, `json`) |
| MySQL / MariaDB | base contenant `waitlist_subscribers` (voir `lib/schema.sql`) |
| Node.js | 20 LTS, **uniquement sur le poste de build** (`npm run build`) |
| HTTPS | obligatoire : les cookies de session sont `Secure` |

## 1. Récupérer le code et recréer la configuration

```bash
git clone https://github.com/fullcoderfull/agenia.git
cp config/secrets.example.php config/secrets.php
cp config/users.example.php   config/users.php
```

Renseigner les valeurs `CHANGEME`. Les dossiers `data/` et `logs/` sont créés vides ;
`config/partners.php` et l'API de paiement (`business/`) ne sont pas versionnés.

## 2. Construire l'interface

```bash
cd react
npm ci
AGENIA_WEB_ROOT=.. npm run deploy      # Windows : $env:AGENIA_WEB_ROOT = '..' ; npm run deploy
```

Le build est copié dans `app/` à la racine du site.

## 3a. Apache 2.4 (Linux)

1. Copier le site dans `/var/www/agenia`.
2. Activer les modules et l'hôte virtuel :
   ```bash
   sudo a2enmod ssl headers proxy proxy_fcgi alias setenvif
   sudo cp deploy/apache/agenia.conf /etc/apache2/sites-available/
   sudo a2ensite agenia && sudo apachectl configtest && sudo systemctl reload apache2
   ```
3. Droits : seul l'utilisateur du serveur web écrit dans `data/` et `logs/`.
   ```bash
   sudo chown -R root:www-data /var/www/agenia
   sudo find /var/www/agenia -type d -exec chmod 750 {} \; -o -type f -exec chmod 640 {} \;
   sudo chmod 770 /var/www/agenia/data /var/www/agenia/logs
   ```
4. Le fichier `.htaccess` fourni à la racine bloque les dossiers internes, les sauvegardes et
   les pages de test. Il exige `AllowOverride All` ; l'hôte virtuel reprend les mêmes règles
   en cas de désactivation.

> **Point d'attention Apache** : une extension inconnue (`api.php.avant-demo`, `.bak`…) est
> servie **en texte brut** et exposerait le code PHP. Ne jamais retirer les règles `FilesMatch`.
>
> **Linux est sensible à la casse** : vérifier que les `require` correspondent exactement aux
> noms de fichiers.

## 3b. IIS (Windows)

1. Créer le site IIS pointant sur le dossier, avec PHP via FastCGI et une liaison HTTPS.
2. Appliquer les protections (console administrateur), après avoir renseigné `$site` :
   `deploy/iis/securite-iis.ps1`. Les adresses sensibles doivent répondre **404**.
3. Droits : couper l'héritage sur `config`, `data`, `logs`, `business` et n'accorder que
   SYSTEM, Administrateurs et `IIS AppPool\<pool>` (lecture sur `config`, modification sur
   `data` et `logs`).

> **Point d'attention IIS** : si l'authentification anonyme utilise l'identité du pool, les
> droits NTFS ne suffisent pas à bloquer un fichier : seules les règles `hiddenSegments`
> le font.

## 4. Vérifications après déploiement

| Adresse | Attendu |
|---|---|
| `/app/fr/` | 200 — page d'accueil |
| `/login.php` | 200 |
| `/demo.php` | 302 vers le chat (session de démonstration) |
| `/config/settings.json`, `/data/`, `/logs/`, `/lib/debug.php`, `/react/package.json` | **404** |
| `/business/business-api/appsettings.json` | **404** |

## 5. API de paiement (.NET)

Elle ne doit pas se trouver dans le dossier publié. L'exécuter comme service séparé
(service Windows ou `systemd`), à l'écoute de `127.0.0.1` uniquement ; si PHP l'appelle en
HTTP, l'exemple `ProxyPass` de `deploy/apache/agenia.conf` reste interne.
