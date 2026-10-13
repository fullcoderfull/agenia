# Conventions de contribution — Agenia

Ce document fixe les règles d'écriture du dépôt : branches, messages de commit, commentaires
et versions. Il s'applique à toute modification, humaine ou générée.

---

## 1. Branches

| Branche | Rôle |
|---|---|
| `main` | Version stable, déployable à tout moment. |
| `release/vX.Y.Z` | Version livrée, figée (corrections uniquement). |
| `feature/<sujet>` | Développement d'une fonctionnalité. |
| `fix/<sujet>` | Correction d'une anomalie. |
| `security/<sujet>` | Correctif de sécurité (fusion prioritaire). |

- Noms en minuscules, mots séparés par des tirets : `feature/audit-export-pdf`.
- Pas de nom générique ou personnel (`test`, `propre`, `mike-wip`).
- Toute branche est fusionnée dans `main` par une demande de fusion (pull request) relue.

---

## 2. Messages de commit

Format **Conventional Commits**, rédigé en **anglais** :

```
type(scope): short imperative summary

Optional body explaining why the change is needed and what it affects.

Refs: #123
```

### Types

| Type | Usage |
|---|---|
| `feat` | Nouvelle fonctionnalité. |
| `fix` | Correction d'anomalie. |
| `security` | Correctif de sécurité. |
| `perf` | Amélioration de performance. |
| `refactor` | Restructuration sans changement de comportement. |
| `docs` | Documentation uniquement. |
| `test` | Ajout ou correction de tests. |
| `build` | Build, dépendances, déploiement. |
| `chore` | Maintenance du dépôt ou de la configuration. |
| `revert` | Annulation d'un commit précédent. |

### Périmètres (scope)

`api`, `auth`, `agent`, `audit`, `plan`, `demo`, `llm`, `voice`, `front`, `i18n`, `config`, `iis`, `repo`.

### Règles

- **Sujet** : impératif présent (*add*, *fix*, *remove*), 72 caractères maximum, sans point final.
- **Corps** : explique le *pourquoi* et l'impact, pas le *comment* (le code le montre).
- **Un commit = un changement cohérent.** Pas de commit fourre-tout.
- **Rupture de compatibilité** : ajouter `!` après le type et une ligne `BREAKING CHANGE:` dans le corps.
- Aucun nom de technologie interne, aucune adresse IP, aucun secret dans un message.

### Exemples

```
feat(demo): add time-limited demo entry point
fix(plan): treat expired subscriptions as non-PRO
security(iis): block public access to configuration and runtime folders
docs(repo): add contribution guidelines
chore(repo): exclude build artifacts from version control
```

À éviter : `update`, `fix stuff`, `wip`, `ok`, `import propre`, `test 2`.

---

## 3. Commentaires dans le code

- Rédigés en **français**, phrases complètes, ton neutre et factuel.
- Expliquent **l'intention, la règle métier ou la contrainte** ; ne paraphrasent pas le code.
- Un marqueur de modification livrée garde la forme `AGENIA-<LOT>` (ex. `AGENIA-PRO`),
  suivi d'une explication courte.
- `TODO` et `FIXME` sont suivis d'un responsable ou d'une référence : `TODO(#42): …`.
- Aucun commentaire familier, ironique ou personnel ; aucun code mort commenté
  (l'historique Git le conserve).

```php
// Correct
// Un abonnement expiré n'ouvre plus l'accès au journal d'audit.

// À éviter
// truc bizarre, ça marche pas sans ça lol
// $old = …;   (code mort)
```

---

## 4. Versions et étiquettes

- Numérotation **SemVer** : `MAJEUR.MINEUR.CORRECTIF`
  - MAJEUR : rupture de compatibilité ;
  - MINEUR : fonctionnalité compatible ;
  - CORRECTIF : correction compatible.
- Chaque livraison reçoit une étiquette annotée :
  `git tag -a v1.0.0 -m "Release 1.0.0"` puis `git push origin v1.0.0`.

---

## 5. Sécurité — avant chaque envoi

1. `git status` : aucun fichier de `config/` (hors `*.example.php`), `data/`, `logs/`, `business/`.
2. Recherche de secrets :
   `git grep --cached -nIE "sk_live|sk_test|sk-or-|sk-ant-|whsec_|AKIA|PRIVATE KEY"` doit être vide.
3. Aucun fichier de plus de 50 Mo.
4. Aucun module PRO ni documentation interne (voir `.gitignore`).

Un secret envoyé par erreur doit être **révoqué et remplacé**, pas seulement supprimé du dépôt.
