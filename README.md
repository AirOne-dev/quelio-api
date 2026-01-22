# Quelio API

[![Tests](https://github.com/AirOne-dev/quelio-api/actions/workflows/tests.yml/badge.svg)](https://github.com/AirOne-dev/quelio-api/actions/workflows/tests.yml)
[![Coverage](https://img.shields.io/endpoint?url=https://raw.githubusercontent.com/AirOne-dev/quelio-api/gh-pages/badges/coverage.json)](https://github.com/AirOne-dev/quelio-api)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://www.php.net)
[![Tests](https://img.shields.io/endpoint?url=https://raw.githubusercontent.com/AirOne-dev/quelio-api/gh-pages/badges/tests.json)](https://github.com/AirOne-dev/quelio-api)

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

| Méthode | Route | Description | Auth | Réponse |
|---------|-------|-------------|------|---------|
| GET | `/` | Formulaire de connexion (si activé) | Non | HTML/JSON |
| POST | `/` | Connexion et récupération des heures | Credentials/Token | Données utilisateur complètes |
| POST | `/?action=update_preferences` | Mise à jour des préférences | Token | Données utilisateur complètes |
| GET | `/icon.svg` | Icône PWA dynamique | Non | SVG |
| GET | `/manifest.json` | Manifest PWA | Non | JSON |
| GET/POST | `/data.json` | Accès aux données brutes | Admin | Toutes les données |

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

### Succès (login et update_preferences)

Les deux endpoints retournent la structure complète des données utilisateur:

```json
{
  "preferences": {
    "theme": "ocean",
    "minutes_objective": 2280
  },
  "token": "base64:encrypted:timestamp:signature",
  "weeks": {
    "2026-w-03": {
      "days": {
        "13-01-2026": {
          "hours": ["08:30", "12:00", "13:00", "18:30"],
          "breaks": {
            "morning": "00:00",
            "noon": "01:00",
            "afternoon": "00:00"
          },
          "effective_to_paid": [
            "+ 00:07 => morning break",
            "+ 00:07 => afternoon break"
          ],
          "effective": "09:00",
          "paid": "09:14"
        },
        "14-01-2026": {
          "hours": ["08:30", "12:00", "13:00", "17:30"],
          "breaks": {
            "morning": "00:00",
            "noon": "01:00",
            "afternoon": "00:00"
          },
          "effective_to_paid": [
            "+ 00:07 => morning break",
            "+ 00:07 => afternoon break"
          ],
          "effective": "08:00",
          "paid": "08:14"
        }
      },
      "total_effective": "17:00",
      "total_paid": "17:28"
    },
    "2026-w-04": {
      "days": {
        "20-01-2026": { ... }
      },
      "total_effective": "08:30",
      "total_paid": "08:44"
    }
  }
}
```

**Structure des données:**
- `preferences`: Préférences utilisateur (thème, objectif de minutes)
- `token`: Token d'authentification pour les requêtes suivantes
- `weeks`: Données organisées par semaine (format ISO: YYYY-w-WW)
  - `days`: Détails par jour
    - `hours`: Horaires badgés
    - `breaks`: Pauses détectées (matin, midi, après-midi)
    - `effective_to_paid`: Explications des transformations
    - `effective`: Heures effectives travaillées
    - `paid`: Heures payées (avec bonus pauses)
  - `total_effective`: Total hebdomadaire effectif
  - `total_paid`: Total hebdomadaire payé

### Erreurs

Toutes les erreurs utilisent le champ `error` (singulier):

**Erreur simple:**
```json
{
  "error": "Invalid or expired token"
}
```

**Erreur avec contexte:**
```json
{
  "error": "Failed to fetch data from Kelio. Please login again.",
  "token_invalidated": true
}
```

**Erreur de validation (avec détails par champ):**
```json
{
  "error": "Validation failed",
  "fields": {
    "theme": "Invalid theme format. Only alphanumeric, underscore and dash allowed (max 50 chars)",
    "minutes_objective": "Invalid minutes objective. Must be > 0"
  }
}
```

### Codes d'erreur

| Code | Message type | Cause |
|------|--------------|-------|
| 400 | Bad Request | Paramètres invalides ou manquants |
| 401 | Unauthorized | Token invalide/expiré ou identifiants incorrects |
| 403 | Forbidden | Accès refusé (admin requis) |
| 404 | Not Found | Ressource non trouvée |
| 422 | Validation Error | Erreurs de validation des champs |
| 429 | Too Many Requests | Rate limiting dépassé |
| 500 | Internal Server Error | Erreur serveur ou connexion Kelio échouée |

## Tests

Le projet dispose d'une suite de tests complète avec **242 tests** et **590 assertions** - **100% de réussite**.

### Lancer les tests localement

```bash
# Tous les tests (via Docker, aucune installation PHP requise)
./run-tests.sh

# Tests unitaires uniquement
./run-tests.sh --unit

# Test spécifique
./run-tests.sh --filter TimeCalculatorTest

# Génération du rapport de couverture
./run-tests.sh --coverage
```

### CI/CD GitHub Actions

La CI vérifie automatiquement à chaque push :
- ✅ Tous les tests passent (242/242)
- ✅ Couverture minimale de 80% respectée
- ✅ Aucun test incomplet, risky ou warning
- ✅ Nombre de tests vérifié

### Structure des tests

- **Feature** (15 tests) : Tests end-to-end du routeur HTTP
- **Unit** (227 tests) :
  - Services : Auth, Storage, KelioClient, RateLimiter, **TimeCalculator (avec tests multi-semaines)**
  - Controllers : **Base (avec tests multi-semaines)**, BaseGuest, Data, Icon, Manifest
  - Middleware : AuthMiddleware

### Tests clés ajoutés

- ✅ Validation de la structure hebdomadaire des données
- ✅ Tests de récupération multi-semaines (3+ semaines)
- ✅ Vérification des détails de pauses par jour
- ✅ Validation des transformations effective→paid
- ✅ Persistance des données multi-semaines dans le storage

## Notes techniques

- **Structure hebdomadaire** : Les données sont organisées par semaine ISO (YYYY-w-WW) avec historique complet
- **Détails journaliers** : Chaque jour inclut horaires, pauses détaillées, et transformations effective→paid
- **Multi-semaines** : L'API peut retourner des données couvrant plusieurs semaines dans une seule réponse
- **Calcul des heures** :
  - **Pause midi minimum** : 1 heure obligatoire si travail entre 12h-14h (configurable)
  - **Bonus pauses** : 7 minutes ajoutées si travail après 11h (matin) et 16h (après-midi)
  - Si pause midi < 1h, le temps manquant est déduit des bonus (maximum = total des bonus)
  - Traçabilité complète des transformations dans `effective_to_paid`
- **Cache** : Sauvegarde automatique après chaque récupération réussie avec structure complète
- **Tokens** : AES-256-CBC encryption (username + password + timestamp + signature)
- **Rate limiting** : Protection anti-brute force (5 tentatives / 5 minutes par défaut)
- **Sécurité** : Mots de passe chiffrés dans tokens, CSRF Kelio, headers de sécurité, file locking

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
