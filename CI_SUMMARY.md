# ✅ CI/CD Configurée - Résumé

## Ce qui a été ajouté

### 1. GitHub Actions Workflow (`.github/workflows/tests.yml`)

**3 jobs automatiques** à chaque push/PR :

#### Job 1: Tests Multi-versions PHP
- ✅ Lance 177 tests sur **PHP 8.1, 8.2, 8.3**
- ✅ Rapide (pas de génération de coverage)
- ℹ️ PHP 8.0 non supporté (PHPUnit 10.5 nécessite PHP 8.1+)

#### Job 2: Vérification Coverage
- ✅ **Minimum 90% de couverture requis**
- ✅ Échoue si coverage < 90%
- ✅ Affiche le % exact dans les logs
- ✅ Génère les variables pour badge dynamique

#### Job 3: Quality Checks
- ✅ Vérifie qu'il n'y a **aucun test incomplete**
- ✅ Vérifie qu'il n'y a **aucun test risky**
- ✅ Vérifie qu'il n'y a **aucun warning**
- ✅ Vérifie que **177 tests minimum** sont présents

### 2. README.md mis à jour

Badges ajoutés (tous dynamiques et mis à jour automatiquement) :
- [![Tests](badge)](lien) - Statut de la CI (GitHub Actions)
- [![Coverage](badge)](lien) - % de couverture (généré et déployé sur gh-pages)
- [![PHP](badge)](lien) - Versions supportées (statique)
- [![Tests](badge)](lien) - Nombre de tests (généré et déployé sur gh-pages)

Section complète sur les tests et la CI.

### 3. Fichiers de configuration

- `.gitattributes` : Exclut les fichiers dev des releases
- `GITHUB_SETUP.md` : Guide complet pour configurer le repo

## Prochaines Étapes

### Immédiat

```bash
# Pousser sur GitHub
git push origin bugfix/noon-minimum-break

# Créer une PR vers main
# → La CI se lance automatiquement
```

### Configuration GitHub

1. **Remplacer les placeholders dans README.md** :
   - `YOUR_USERNAME` → votre username GitHub
   - `YOUR_REPO` → nom du repository

2. **Activer GitHub Pages** (pour les badges dynamiques) :
   - Settings → Pages
   - Source : branche `gh-pages`, dossier `/ (root)`
   - La branche sera créée automatiquement au premier push sur main

3. **Activer Branch Protection** (optionnel mais recommandé) :
   - Settings → Branches → Add rule
   - Cocher "Require status checks to pass before merging"
   - Sélectionner les 5 jobs (test x3 + coverage-check + quality)

## Résultats Attendus

Une fois configuré, **à chaque push** :

1. ⚡ La CI se lance automatiquement (100% gratuit)
2. 🧪 177 tests s'exécutent sur 3 versions PHP (8.1, 8.2, 8.3) (< 2 min)
3. 📊 La couverture est calculée et vérifiée (≥90%)
4. ✅ Les badges se mettent à jour automatiquement
5. 🚫 Impossible de merger si un test échoue (avec branch protection)

## Exemple de Pull Request

```
PR: Add new feature

Checks:
✅ test (8.1) — 177 tests passed
✅ test (8.2) — 177 tests passed
✅ test (8.3) — 177 tests passed
✅ coverage-check — 93.2% coverage (target: 90%)
✅ quality — No incomplete/risky/warnings
```

## Commandes Locales

Avant de push, toujours vérifier localement :

```bash
# Tous les tests
./run-tests.sh

# Tests unitaires uniquement
./run-tests.sh --unit

# Coverage (si Xdebug installé)
./run-tests.sh --coverage
```

## Maintenance

La CI est **zéro maintenance** :
- Pas de serveur à gérer
- Gratuit pour les repos publics
- Se lance automatiquement
- Cache Composer pour être plus rapide

Seules actions possibles :
- Ajuster le seuil de coverage dans `.github/workflows/tests.yml`
- Modifier les versions PHP testées
- Ajouter/retirer des quality checks

## Support

Documentation complète dans `GITHUB_SETUP.md`.

En cas de problème, vérifier :
1. Les logs dans l'onglet "Actions" de GitHub
2. Que toutes les dépendances sont dans `composer.json`
3. Que le workflow YAML est valide (indentation)
