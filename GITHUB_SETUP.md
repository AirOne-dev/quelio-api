# Configuration du Repository GitHub

Ce guide explique comment configurer votre repository GitHub pour activer la CI/CD et les badges.

## 1. Créer le Repository GitHub

1. Aller sur https://github.com/new
2. Nommer le repository (ex: `quelio-api`)
3. Choisir **Public** (pour les badges gratuits)
4. Ne pas initialiser avec README (on a déjà le code)
5. Cliquer sur **Create repository**

## 2. Pousser le Code

```bash
# Si c'est un nouveau repository
git remote add origin https://github.com/YOUR_USERNAME/quelio-api.git
git branch -M main
git push -u origin main

# Ou si vous avez déjà un remote
git push origin bugfix/noon-minimum-break
```

## 3. Configurer les Badges dans README.md

Remplacer `YOUR_USERNAME` et `YOUR_REPO` dans `README.md` :

```markdown
[![Tests](https://github.com/YOUR_USERNAME/YOUR_REPO/actions/workflows/tests.yml/badge.svg)](https://github.com/YOUR_USERNAME/YOUR_REPO/actions/workflows/tests.yml)
```

Par exemple :
```markdown
[![Tests](https://github.com/erwanmarchand/quelio-api/actions/workflows/tests.yml/badge.svg)](https://github.com/erwanmarchand/quelio-api/actions/workflows/tests.yml)
```

## 4. Vérifier que la CI Fonctionne

1. Aller sur `https://github.com/YOUR_USERNAME/YOUR_REPO/actions`
2. Vous devriez voir le workflow **Tests** en cours d'exécution
3. Cliquer dessus pour voir les détails

### Les 3 jobs de la CI :

1. **test** : Lance les tests sur PHP 8.0, 8.1, 8.2, 8.3
2. **coverage-check** : Vérifie que la couverture est ≥ 90%
3. **quality** : Vérifie qu'il n'y a pas de tests incomplete/risky/warnings

## 5. Configuration Branch Protection (Recommandé)

Pour forcer les tests à passer avant de merger :

1. Aller sur `https://github.com/YOUR_USERNAME/YOUR_REPO/settings/branches`
2. Cliquer sur **Add rule**
3. Branch name pattern : `main`
4. Cocher :
   - ✅ Require status checks to pass before merging
   - ✅ Require branches to be up to date before merging
5. Dans "Status checks", chercher et sélectionner :
   - `test (8.0)`
   - `test (8.1)`
   - `test (8.2)`
   - `test (8.3)`
   - `coverage-check`
   - `quality`
6. Cliquer sur **Create**

## 6. Résultat Final

Une fois configuré, vous aurez :

✅ **CI/CD automatique** à chaque push (100% gratuit)
✅ **177 tests** lancés sur 4 versions de PHP
✅ **Couverture minimale de 90%** vérifiée
✅ **Badges** sur le README montrant le statut
✅ **Protection de la branche main** (tests obligatoires)
✅ **Aucun service externe requis** (tout dans GitHub Actions)

## Exemple de Workflow

```bash
# 1. Créer une branche
git checkout -b feature/nouvelle-fonctionnalite

# 2. Développer et tester localement
./run-tests.sh

# 3. Commit et push
git add .
git commit -m "Add nouvelle fonctionnalite"
git push origin feature/nouvelle-fonctionnalite

# 4. Créer une Pull Request sur GitHub
# → La CI lance automatiquement les tests
# → Les badges montrent le statut (✅ ou ❌)
# → Les checks doivent être verts pour merger

# 5. Si tout est vert, merger dans main
```

## Aide

**Les tests échouent en CI mais passent en local ?**
- Vérifier la version de PHP (`php -v`)
- Vérifier que Composer est à jour
- Vérifier les permissions des fichiers

**Le badge ne s'affiche pas ?**
- Attendre que le premier workflow se termine
- Vérifier que le chemin vers le workflow est correct
- Essayer de vider le cache du navigateur

**Le coverage n'est pas calculé ?**
- Vérifier que Xdebug est bien installé (automatique dans la CI)
- Vérifier les logs du job `coverage-check` dans Actions
