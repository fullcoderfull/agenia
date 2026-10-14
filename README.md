# Agenia

**Un assistant IA pour les équipes, sous contrôle** : l'agent prépare, une personne décide.
Chaque échange est tracé et son coût est connu. Le modèle d'IA peut tourner en local ou dans le cloud.

> **Installation en 5 étapes, environ 15 minutes.**
> Deux éléments sont **obligatoires** : le script **`setup.sh`** (étape 2) et le **serveur MCP terminal** (étape 3).
> Sans eux, l'application ne fonctionne pas.

---

## Prérequis

| Élément | Version | Rôle |
|---|---|---|
| Serveur web | IIS 10 (Windows) ou Apache 2.4 (Linux) | sert le site |
| PHP | 8.0 minimum, 8.2 recommandé, avec `pdo_mysql`, `mbstring`, `openssl`, `curl` | API de l'application |
| MySQL ou MariaDB | 8.0 / 10.6 ou plus | comptes et abonnements |
| Node.js | 20 LTS, **sur le poste de compilation uniquement** | construction de l'interface |
| Bash | Linux, macOS, ou **Git Bash** / WSL sous Windows | lancement de `setup.sh` |
| HTTPS | certificat valide | obligatoire : les cookies de session sont sécurisés |
| Serveur MCP terminal | voir l'étape 3 | exécution des actions de l'agent |

Vérifier PHP :

```bash
php -v
php -m | grep -Ei "pdo_mysql|mbstring|openssl|curl"
```

---

## Installation

### 1. Récupérer le code

```bash
git clone https://github.com/fullcoderfull/agenia.git
cd agenia
```

Placez le dossier là où votre serveur web le servira, ou clonez directement à cet endroit.

### 2. Lancer `setup.sh` (obligatoire)

```bash
bash setup.sh
```

`setup.sh` prépare l'application par **copie sécurisée** : il crée les fichiers de
configuration à partir des modèles fournis, sans jamais écraser un fichier existant, et
restreint leurs droits d'accès. **L'application ne démarre pas sans cette étape** : les
fichiers qu'il crée ne sont pas dans le dépôt, volontairement.

> Sous Windows, lancez la commande depuis **Git Bash** (installé avec Git for Windows) ou WSL,
> dans le dossier du projet.

Complétez ensuite les valeurs demandées dans `config/secrets.php` : base de données, clés,
secret de session. **Ne réutilisez jamais les valeurs d'exemple**, et ne versionnez jamais
ce fichier.

### 3. Installer le serveur MCP terminal (obligatoire)

L'agent d'Agenia exécute ses actions, toujours après accord humain, par l'intermédiaire
d'une **API MCP** : le **serveur MCP terminal**. Il doit tourner sur la même machine que le
site.

> Dépôt du serveur MCP terminal : **[mcp-terminal-server](https://github.com/fullcoderfull/mcp-terminal-server)** *(lien provisoire)*

- Il écoute **en local uniquement**, sur le port `5000` par défaut. Ne l'exposez jamais sur Internet.
- Sans lui, le chat fonctionne, mais les actions de l'agent échouent.
- Installez-le **avant** de tester le mode agent.

### 4. Construire l'interface

```bash
cd react
npm ci
AGENIA_WEB_ROOT=.. npm run deploy
cd ..
```

Sous Windows (PowerShell) :

```powershell
cd react ; npm ci ; $env:AGENIA_WEB_ROOT = '..' ; npm run deploy ; cd ..
```

L'interface compilée est copiée dans `app/`.

### 5. Configurer le serveur web

Le site doit être servi en HTTPS, avec les dossiers internes **invisibles depuis Internet** :
`config`, `data`, `logs`, `lib`, `react`, `docs`, `deploy`.

| Serveur | Fichiers fournis |
|---|---|
| IIS | `web.config` (règles de protection) |
| Apache | `.htaccess` et `deploy/apache/agenia.conf` |

Les instructions détaillées sont dans **[DEPLOY.md](DEPLOY.md)**.

Donnez au compte du serveur web le droit d'**écrire uniquement** dans `data/` et `logs/`.

---

## Vérifier l'installation

Ouvrez ces adresses (remplacez `votre-domaine`) :

| Adresse | Résultat attendu |
|---|---|
| `https://votre-domaine/app/fr/` | la page d'accueil |
| `https://votre-domaine/login.php` | la page de connexion |
| `https://votre-domaine/demo.php` | une session de démonstration s'ouvre |
| `https://votre-domaine/config/settings.json` | **404** : le dossier est bien protégé |
| `https://votre-domaine/logs/` | **404** |

Un **404** sur les deux dernières adresses est le bon résultat. Si le fichier s'affiche,
le site expose sa configuration : corrigez l'étape 5 avant toute mise en service.

---

## Problèmes fréquents

| Symptôme | Cause probable | Solution |
|---|---|---|
| Erreur 500 sur toutes les pages PHP | `setup.sh` n'a pas été lancé : `config/secrets.php` est absent | lancer `bash setup.sh` (étape 2) |
| « Failed opening required … secrets.php » | idem | idem |
| Les actions de l'agent échouent | serveur MCP terminal arrêté ou absent | démarrer le serveur MCP terminal (étape 3) |
| Page blanche sur `/app/fr/` | interface non construite | étape 4 |
| Erreur 500.19 (IIS) | le compte du site ne peut pas lire le dossier | donner la lecture au pool d'applications |
| Avertissement de sécurité dans le navigateur | certificat HTTPS invalide ou expiré | installer un certificat valide |

---

## Sécurité

- **Aucun secret dans le dépôt.** Clés et mots de passe vivent uniquement dans `config/secrets.php`,
  créé par `setup.sh` et ignoré par Git.
- **Le serveur MCP terminal reste local.** Il ne doit jamais être joignable depuis Internet.
- **Signaler une vulnérabilité** : écrivez-nous en privé plutôt que d'ouvrir une issue publique.

## Contribuer

Les conventions (branches, messages de commit) sont décrites dans **[CONTRIBUTING.md](CONTRIBUTING.md)**.
