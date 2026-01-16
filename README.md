# Quelio API

[![Tests](https://github.com/YOUR_USERNAME/YOUR_REPO/actions/workflows/tests.yml/badge.svg)](https://github.com/YOUR_USERNAME/YOUR_REPO/actions/workflows/tests.yml)
[![Coverage](https://img.shields.io/badge/coverage-95%25-brightgreen.svg)](https://github.com/YOUR_USERNAME/YOUR_REPO)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-blue.svg)](https://www.php.net)
[![Tests](https://img.shields.io/badge/tests-177%20passed-brightgreen.svg)](https://github.com/YOUR_USERNAME/YOUR_REPO)

API PHP pour récupérer et calculer les heures de travail depuis Kelio.

## Fonctionnalités

- Connexion à Kelio et récupération automatique des heures badgées
- Calcul des heures effectives et payées (avec gestion des pauses)
- Cache local pour accès hors ligne
- Authentification par token (pas besoin de renvoyer le mot de passe à chaque requête)
- Rate limiting (protection anti-brute force)
- Génération dynamique d'icônes et manifest PWA
- Endpoint admin pour accéder aux données brutes

## Architecture

Architecture MVC avec injection de dépendances et auto-loading PSR-4.

```
api/
├── index.php                  # Point d'entrée
├── config.php                 # Configuration
├── .htaccess / nginx.conf     # Configuration serveur
└── src/
    ├── core/                  # Router, Container, ServiceProvider, Autoloader
    ├── http/                  # JsonResponse, ActionController, AuthContext
    ├── controllers/           # BaseController, IconController, DataController, etc.
    ├── middleware/            # AuthMiddleware, RateLimiter
    └── services/              # Auth, Storage, KelioClient, TimeCalculator
```

### Routes

| Méthode | Route | Description | Auth |
|---------|-------|-------------|------|
| GET | `/` | Formulaire de connexion (si activé) | Non |
| POST | `/?action=login` | Connexion et récupération des heures | Credentials Kelio |
| POST | `/?action=update_preferences` | Mise à jour des préférences | Token |
| GET | `/icon.svg` | Icône PWA dynamique | Non |
| GET | `/manifest.json` | Manifest PWA | Non |
| GET/POST | `/data.json` | Accès aux données brutes | Admin |

## Configuration

```bash
cp config.example.php config.php
```

Paramètres principaux dans `config.php` :

| Paramètre | Description | Défaut |
|-----------|-------------|--------|
| `kelio_url` | URL de votre instance Kelio | **Obligatoire** |
| `admin_username` / `admin_password` | Identifiants admin pour `/data.json` | **À configurer** |
| `encryption_key` | Clé de chiffrement des tokens | **À changer** |
| `pause_time` | Durée de pause en minutes | 7 |
| `start_limit_minutes` / `end_limit_minutes` | Plage horaire de travail | 8h30 - 18h30 |
| `enable_form_access` | Activer le formulaire HTML | `true` |
| `rate_limit_max_attempts` | Tentatives max avant blocage | 5 |
| `rate_limit_window` | Fenêtre de temps (secondes) | 300 |

Le cache est automatiquement créé dans `./data.json` ou `/tmp/kelio_data.json`.

## Installation

### Prérequis
- PHP 8.0+ avec extensions cURL et DOM
- Apache (mod_rewrite) ou Nginx

### Configuration serveur

**Apache** : Le fichier `.htaccess` est déjà configuré. Activez `mod_rewrite` :
```bash
sudo a2enmod rewrite && sudo systemctl restart apache2
```

**Nginx** : Utilisez le fichier `nginx.conf` fourni comme base.

**Plesk/sous-répertoire** : Ajoutez toujours un `/` final à l'URL (ex: `/api-test/`) pour éviter les redirections 301 POST→GET.

### Permissions
```bash
chmod 600 config.php
chmod 775 .  # Pour permettre l'écriture de data.json
```

## Utilisation

### 1. Connexion et récupération des heures

**Avec identifiants Kelio** (données fraîches) :
```bash
curl -X POST http://votre-serveur.com/ \
  -F 'username=VOTRE_IDENTIFIANT' \
  -F 'password=VOTRE_MOT_DE_PASSE'
```

**Avec token** (cache, plus rapide) :
```bash
curl -X POST http://votre-serveur.com/ \
  -F 'username=VOTRE_IDENTIFIANT' \
  -F 'token=VOTRE_TOKEN'
```

### 2. Mise à jour des préférences

```bash
curl -X POST http://votre-serveur.com/?action=update_preferences \
  -F 'username=VOTRE_IDENTIFIANT' \
  -F 'token=VOTRE_TOKEN' \
  -F 'theme=ocean' \
  -F 'minutes_objective=2280'
```

### 3. Accès admin aux données brutes

```bash
curl http://votre-serveur.com/data.json?username=admin&password=ADMIN_PASSWORD
```

### 4. PWA (Icône et Manifest)

```bash
# Icône SVG personnalisable
curl "http://votre-serveur.com/icon.svg?primary=4F46E5&secondary=059669"

# Manifest PWA
curl "http://votre-serveur.com/manifest.json?primary=4F46E5&secondary=059669&background=1a1d29"
```

## Réponses API

### Succès (login)

```json
{
    "authenticated_with": "credentials",
    "hours": {"21-10-2024": ["08:30", "12:00", "13:00", "18:30"]},
    "total_effective": "15:45",
    "total_paid": "16:13",
    "preferences": {"theme": "ocean", "minutes_objective": 2280},
    "token": "a1b2c3d4...abc123:1234567890",
    "cache": false
}
```

### Erreurs communes

| Code | Message | Cause |
|------|---------|-------|
| 401 | Invalid or expired token | Token invalide ou mot de passe Kelio changé |
| 404 | No cached data found | Première connexion avec token sans cache |
| 429 | Too many requests | Rate limiting dépassé |
| 500 | Internal server error | Erreur serveur ou connexion Kelio échouée |

## Tests

Le projet dispose d'une suite de tests complète avec **177 tests** et **~95% de couverture de code**.

### Lancer les tests localement

```bash
# Tous les tests (via Docker, aucune installation PHP requise)
./run-tests.sh

# Tests unitaires uniquement
./run-tests.sh --unit

# Test spécifique
./run-tests.sh --filter AuthTest

# Génération du rapport de couverture
./run-tests.sh --coverage
```

### CI/CD GitHub Actions

La CI vérifie automatiquement à chaque push :
- ✅ Tous les tests passent (177 tests sur PHP 8.0, 8.1, 8.2, 8.3)
- ✅ Couverture minimale de 90% respectée
- ✅ Aucun test incomplet, risky ou warning
- ✅ Upload du rapport de couverture sur Codecov

### Structure des tests

- **Feature** (15 tests) : Tests end-to-end du routeur HTTP
- **Unit** (162 tests) :
  - Services : Auth, Storage, KelioClient, RateLimiter, TimeCalculator
  - Controllers : Base, BaseGuest, Data, Icon, Manifest
  - Middleware : AuthMiddleware

## Notes techniques

- **Calcul des heures** : Pause midi minimum de 7 minutes si travail entre 11h-14h, pauses auto ajoutées selon seuils
- **Cache** : Sauvegarde automatique après chaque récupération réussie, fallback en cas d'échec de connexion
- **Tokens** : SHA-256 hash (username + password + timestamp), invalide si mot de passe Kelio change
- **Rate limiting** : Protection anti-brute force configurée dans `config.php`
- **Sécurité** : Mots de passe jamais stockés, tokens CSRF Kelio, blocage fichiers sensibles via `.htaccess`/`nginx.conf`

## Dépannage

### ⚠️ Blocage compte Kelio

**Symptôme** : Erreurs "Login failed" ou "Unable to get CSRF token" même avec bons identifiants.

**Cause** : Trop de tentatives de connexion échouées déclenchent le blocage temporaire Kelio (15-30 min).

**Solutions** :
- Vérifier identifiants manuellement sur Kelio
- Attendre 15-30 minutes
- Utiliser l'authentification par token (évite les connexions répétées)
- Limiter la fréquence d'appels API (max 1x/heure recommandé)

### Erreurs communes

| Problème | Solution |
|----------|----------|
| Cache non créé | `chmod 775 .` pour permettre l'écriture |
| 401 Unauthorized | Vérifier token ou identifiants |
| 429 Too Many Requests | Rate limiting atteint, patienter |
| Fallback cache | Normal si Kelio indisponible, vérifier `last_save` |
| POST → GET (301) | Ajouter `/` final à l'URL (ex: `/api-test/`) |

## Licence

Projet libre d'utilisation.
