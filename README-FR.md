# VoxMind

Interface web conversationnelle IA — LLM + TTS + STT + Vision, 100% local et open source.

## Fonctionnalités

- **LLM** — compatible Ollama et tout provider OpenAI-compatible
- **TTS** — synthèse vocale (Kokoro) avec surlignage mot par mot
- **STT** — transcription vocale (Faster-Whisper) avec VAD automatique
- **Vision** — analyse d'images via webcam (caméra avant/arrière)
- **Upload** — PDF, DOCX, TXT, images envoyés au LLM
- **Multi-utilisateurs** — admin, demo, partenaires avec quotas
- **RGPD** — IP pseudonymisée, logs rotatifs, politique de confidentialité intégrée
- **Modules** — architecture extensible (terminal, SSO, billing...)
- **100% local** — aucune donnée envoyée à des services externes

## Prérequis

- PHP 8.1+ avec FastCGI (IIS ou Nginx)
- Ollama (LLM)
- Kokoro TTS (optionnel)
- Faster-Whisper via Docker (optionnel)
- MySQL 8+ (optionnel — mode fichier par défaut)

## Installation rapide

```bash
# 1. Cloner le repo
git clone https://github.com/votre-compte/voxmind.git
cd voxmind

# 2. Générer la configuration (Linux/Mac)
bash setup.sh

# 3. Windows IIS
setup-iis.bat        # génère secrets.php
icacls-setup.bat     # configure les droits NTFS
```

## Configuration

Éditer `config/secrets.php` après génération :

```php
define('LLM_URL',   'http://172.17.164.70:11434');  // URL Ollama
define('LLM_MODEL', 'llama3.1');                // modèle
define('TTS_URL',   'http://172.17.164.70:8880');   // URL Kokoro
define('STT_URL',   'http://172.17.164.70:8990');   // URL Faster-Whisper
```

## Authentification

Mode fichier (par défaut) : éditer `config/users.php`

Mode MySQL : `define('AUTH_BACKEND', 'mysql')` puis importer `sql/schema.sql`

## Modules

Activer/désactiver dans `config/modules.php` :

```php
'terminal' => ['enabled' => true, 'roles' => ['admin']]
'sso'      => ['enabled' => false]
'billing'  => ['enabled' => false]
```

Créer un module : implémenter `VoxModule` dans `modules/mon_module/module.php`

## Structure

```
├── config/          ← configuration (jamais committé)
├── lib/             ← auth, sécurité, modules
├── modules/         ← modules extensibles
├── js/              ← frontend vanilla JS
├── sql/             ← schéma MySQL
├── logs/            ← logs auto-purgés (30 jours)
└── docker-compose.yml ← Faster-Whisper GPU
```

## Sécurité

- Sessions HMAC-SHA256 signées
- Rate limiting par IP (30 req/min)
- Lockout progressif (1min → 15min → 1h → 24h)
- CSP, X-Frame-Options, HSTS
- IP pseudonymisée avant stockage

## Licence

MIT — libre d'utilisation, de modification et de distribution.

La version cloud hébergée sur [vovox.io](https://vovox.io) est soumise à des conditions spécifiques.



# Activer flushNamedPipe sur PHP FastCGI — clé du streaming IIS
Set-WebConfiguration -PSPath "IIS:\" `
  -Filter "system.webServer/fastCgi/application[@fullPath='E:\php\php8.3\php-cgi.exe']" `
  -Value @{flushNamedPipe="True"}

# Vérifier
Get-WebConfiguration -PSPath "IIS:\" `
  -Filter "system.webServer/fastCgi/application[@fullPath='E:\php\php8.3\php-cgi.exe']" | Select flushNamedPipe