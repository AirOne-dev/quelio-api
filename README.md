# Quelio API

API PHP pour récupérer et calculer les heures de travail depuis Kelio.

## Description

Ce projet est une API simple qui permet de :
- Se connecter à votre compte Kelio
- Récupérer automatiquement vos heures de travail badgées
- Calculer le total des heures travaillées (effectif et payé)
- Sauvegarder les données en cache pour un accès hors ligne
- Retourner les résultats au format JSON

L'API gère automatiquement les pauses et calcule deux totaux :
- **Heures effectives** : temps réellement travaillé sans les pauses
- **Heures payées** : temps travaillé avec les pauses incluses

## Architecture

L'API est organisée selon une architecture modulaire et orientée objet pour faciliter la maintenance et l'évolution du code.

### Structure des fichiers

```
api/
├── index.php                  # Point d'entrée principal (routing)
├── config.php                 # Configuration de l'application
├── .htaccess                  # Règles de sécurité Apache
├── nginx.conf                 # Configuration Nginx
├── nginx-security.conf        # Règles de sécurité Nginx
└── src/                       # Code source de l'application
    ├── Router.php             # Gestionnaire de routes
    ├── Auth.php               # Authentification et tokens
    ├── Storage.php            # Gestion du stockage des données
    ├── KelioClient.php        # Client pour l'API Kelio
    ├── TimeCalculator.php     # Calculs des heures de travail
    └── routes/                # Définitions des routes
        ├── home.php           # GET / - Formulaire de connexion
        ├── login.php          # POST / - Authentification
        ├── preferences.php    # POST / - Gestion des préférences
        ├── icon.php           # GET /icon.svg - Icône PWA dynamique
        └── manifest.php       # GET /manifest.json - Manifest PWA
```

### Classes principales

#### **Router** (`src/Router.php`)
Gestionnaire de routes avec support des middlewares. Permet de définir facilement des routes GET/POST et d'appliquer des middlewares d'authentification.

#### **Auth** (`src/Auth.php`)
Gestion de l'authentification et des tokens de session. Fournit des méthodes pour générer et valider les tokens, ainsi qu'un middleware `requireAuth()`.

#### **Storage** (`src/Storage.php`)
Gestion du stockage des données utilisateur dans un fichier JSON. Gère automatiquement les emplacements de fichiers et les permissions.

#### **KelioClient** (`src/KelioClient.php`)
Client pour interagir avec l'API Kelio. Gère la connexion, la récupération du token CSRF et l'extraction des données de pointage.

#### **TimeCalculator** (`src/TimeCalculator.php`)
Calcule les heures de travail en tenant compte des pauses et des seuils horaires configurés.

### Routes disponibles

| Méthode | Route | Description | Authentification |
|---------|-------|-------------|------------------|
| GET | `/` | Affiche le formulaire de connexion | Non |
| POST | `/` | Authentification et récupération des heures | Non (credentials Kelio) |
| POST | `/` (action=update_preferences) | Mise à jour des préférences | Oui (token requis) |
| GET | `/icon.svg` | Génère l'icône PWA dynamique | Non |
| GET | `/manifest.json` | Génère le manifest PWA | Non |

### Sécurité

L'architecture implémente plusieurs niveaux de sécurité :

1. **Routing centralisé** : Toutes les requêtes passent par `index.php`
2. **Blocage des fichiers sensibles** : Les fichiers `.htaccess` et `nginx.conf` bloquent l'accès direct à tous les fichiers PHP sauf `index.php`
3. **URLs propres** : Les routes `/icon.svg` et `/manifest.json` sont redirigées en interne vers le router
4. **Authentification par token** : Les endpoints sensibles nécessitent un token de session valide
5. **Validation des données** : Tous les paramètres sont validés et sanitizés

### Ajouter de nouvelles routes

L'architecture modulaire permet d'ajouter facilement de nouvelles routes en 2 étapes :

#### 1. Créer le fichier de route

Créez un fichier dans `src/routes/` qui retourne une fonction anonyme :

```php
<?php
// src/routes/my-route.php

/**
 * Ma nouvelle route
 * GET /my-route
 */
return function () {
    global $storage, $auth; // Accès aux dépendances si nécessaire

    header('Content-Type: application/json');

    // Votre logique ici
    echo json_encode([
        'message' => 'Hello from my route!'
    ], JSON_PRETTY_PRINT);
};
```

#### 2. Enregistrer la route dans `index.php`

Ajoutez la route après les autres définitions :

```php
// Route simple
$router->get('/my-route', require __DIR__ . '/src/routes/my-route.php');

// Route avec authentification (token requis)
$router->post('/protected-route',
    require __DIR__ . '/src/routes/protected.php',
    [$auth, 'requireAuth']  // Middleware d'authentification
);
```

#### Exemple complet : Route protégée

```php
// src/routes/get-user-data.php
<?php
return function () {
    global $storage;

    $username = $_POST['username'] ?? '';
    $userData = $storage->getUserData($username);

    header('Content-Type: application/json');
    echo json_encode($userData ?? ['error' => 'User not found'], JSON_PRETTY_PRINT);
};

// Dans index.php
$router->post('/user-data',
    require __DIR__ . '/src/routes/get-user-data.php',
    [$auth, 'requireAuth']
);
```

#### Accès aux dépendances

Les routes ont accès aux objets globaux suivants :
- `$config` : Configuration de l'application
- `$storage` : Gestionnaire de stockage
- `$auth` : Gestionnaire d'authentification
- `$kelioClient` : Client Kelio
- `$timeCalculator` : Calculateur d'heures

## Configuration

1. **Copiez le fichier de configuration d'exemple** :
```bash
cp config.example.php config.php
```

2. **Modifiez le fichier `config.php`** avec vos paramètres :

### Paramètres obligatoires

```php
'kelio_url' => 'https://your-company.kelio.io',
```
Remplacez par l'URL de votre instance Kelio (par exemple : `https://entreprise.kelio.io`).
<br /><br />

### Paramètres optionnels

```php
'pause_time' => 7, // Durée de la pause en minutes
```
Durée de la pause à ajouter au calcul des heures payées (par défaut 7 minutes par pause).
<br /><br />

```php
'start_limit_minutes' => 8 * 60 + 30, // 8h30
'end_limit_minutes' => 18 * 60 + 30,   // 18h30
```
Heures de début et fin de journée. Les badgeages en dehors de ces limites seront ramenés à ces valeurs.
<br /><br />

```php
'morning_break_threshold' => 11 * 60,  // 11h00
'afternoon_break_threshold' => 16 * 60, // 16h00
```
Heures à partir desquelles les pauses sont ajoutées au calcul des heures payées.
<br /><br />

```php
'enable_form_access' => true, // true ou false
```
Active ou désactive l'accès au formulaire HTML via GET. Si `false`, seul l'accès POST (API) est autorisé. **Recommandé : `false` pour un usage en production** pour des raisons de sécurité.
<br /><br />

### Fichier de cache

Le script tente automatiquement de créer un fichier de cache dans plusieurs emplacements :
- `./data.json` (répertoire courant)
- `/tmp/kelio_data.json` (répertoire temporaire)

Le fichier de cache permet de récupérer les dernières données connues en cas d'échec de connexion à Kelio.

## Installation

1. Placez les fichiers sur votre serveur web avec PHP 8.0 ou supérieur
2. Copiez `config.example.php` vers `config.php`
3. Configurez les paramètres dans `config.php` comme indiqué ci-dessus
4. Configurez votre serveur web (voir section ci-dessous)
5. Assurez-vous que PHP a les droits d'écriture pour créer le fichier de cache

### Prérequis

- PHP 8.0 ou supérieur
- Extension cURL activée
- Extension DOM activée
- Serveur web : Apache (avec mod_rewrite) ou Nginx

### Configuration du serveur web

Pour des raisons de sécurité, il est **fortement recommandé** de configurer votre serveur web pour bloquer l'accès à tous les fichiers sauf `index.php`. Cela empêche l'accès direct à des fichiers sensibles comme `config.php`, `data.json`, etc.

#### Apache

Le fichier `.htaccess` est déjà inclus dans le projet. Il sera automatiquement utilisé par Apache si `mod_rewrite` est activé.

**Vérifier que mod_rewrite est activé** :
```bash
# Sur Ubuntu/Debian
sudo a2enmod rewrite
sudo systemctl restart apache2

# Vérifier
apache2ctl -M | grep rewrite
```

**Configuration Apache (si .htaccess n'est pas supporté)** :

Ajoutez ceci dans votre VirtualHost :
```apache
<Directory /var/www/quelio-api>
    AllowOverride All
    Require all granted
</Directory>
```

Le fichier `.htaccess` bloquera automatiquement :
- Les fichiers cachés (`.git`, `.gitignore`, etc.)
- Les fichiers de configuration (`config.php`, `config.example.php`)
- Les fichiers de données (`data.json`)
- Les fichiers de documentation (`README.md`)
- Tous les fichiers PHP sauf `index.php`

#### Nginx

Deux options sont disponibles :

**Option 1 : Configuration complète** (recommandé pour une nouvelle installation)

Utilisez le fichier `nginx.conf` fourni comme base pour votre configuration :

```bash
# Copiez et adaptez le fichier
sudo cp nginx.conf /etc/nginx/sites-available/quelio-api
sudo nano /etc/nginx/sites-available/quelio-api

# Modifiez les paramètres suivants :
# - server_name : votre domaine
# - root : chemin vers votre projet
# - fastcgi_pass : socket PHP-FPM (selon votre version PHP)

# Activez le site
sudo ln -s /etc/nginx/sites-available/quelio-api /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

**Option 2 : Règles de sécurité réutilisables** (pour ajouter à une configuration existante)

Utilisez le fichier `nginx-security.conf` :

```bash
# Copiez le fichier
sudo cp nginx-security.conf /etc/nginx/conf.d/quelio-api-security.conf

# Dans votre configuration Nginx, ajoutez :
server {
    # ... votre configuration existante ...

    include /etc/nginx/conf.d/quelio-api-security.conf;
}

# Rechargez Nginx
sudo nginx -t
sudo systemctl reload nginx
```

**Vérifier la version de PHP-FPM** :
```bash
# Trouver le socket PHP-FPM
ls -la /var/run/php/

# Ajustez dans la configuration Nginx :
# fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
```

#### Test de sécurité

Après configuration, testez que les fichiers sensibles sont bien bloqués :

```bash
# Ces requêtes doivent retourner 404
curl -I http://votre-serveur.com/config.php
curl -I http://votre-serveur.com/data.json
curl -I http://votre-serveur.com/.git/config
curl -I http://votre-serveur.com/README.md
curl -I http://votre-serveur.com/src/Router.php
curl -I http://votre-serveur.com/src/routes/login.php

# Ces requêtes doivent fonctionner (200 OK)
curl -I http://votre-serveur.com/index.php  # 200 OK ou 403 si formulaire désactivé
curl -I http://votre-serveur.com/icon.svg   # 200 OK (génération d'icône SVG)
curl -I http://votre-serveur.com/manifest.json  # 200 OK (génération de manifest)

# Ces requêtes doivent retourner 404 (accès direct bloqué)
curl -I http://votre-serveur.com/icon.php
curl -I http://votre-serveur.com/manifest.php
```

### Permissions des fichiers

```bash
# Définir les permissions appropriées
chmod 644 index.php
chmod 600 config.php  # Fichier sensible, accès restreint
chmod 755 .  # Répertoire du projet

# Le serveur web doit pouvoir écrire dans le répertoire pour data.json
# Option 1 : Donner les droits au groupe www-data (recommandé)
chown -R votre-user:www-data .
chmod 775 .

# Option 2 : Utiliser /tmp (déjà configuré par défaut dans le script)
# Le script utilisera automatiquement /tmp si le répertoire n'est pas accessible en écriture
```

## Utilisation

### Interface web (GET)

**Note** : L'accès au formulaire peut être désactivé dans la configuration (`enable_form_access = false`).

Si l'accès au formulaire est activé, accédez simplement à l'URL de votre script dans un navigateur :
```
http://votre-serveur.com/index.php
```

Un formulaire de connexion s'affichera pour entrer vos identifiants Kelio.

Si l'accès est désactivé, vous recevrez une erreur 403 :
```json
{
    "error": "Form access is disabled. Please use POST method to access the API."
}
```

### API (POST)

#### Authentification et récupération des heures

L'API supporte deux méthodes d'authentification :

1. **Avec identifiants** (username + password) : Récupère les données fraîches depuis Kelio
2. **Avec token** (username + token) : Utilise les données en cache (plus rapide)

#### Méthode 1 : Authentification avec identifiants (première connexion)

##### Avec cURL

```bash
curl --location 'http://votre-serveur.com/index.php' \
--form 'username="VOTRE_IDENTIFIANT_KELIO"' \
--form 'password="VOTRE_MOT_DE_PASSE_KELIO"'
```

**Réponse** : Vous recevrez un `token` dans la réponse que vous pourrez réutiliser.

#### Méthode 2 : Authentification avec token (requêtes suivantes)

##### Avec cURL

```bash
# Utilisez le token obtenu lors de la première connexion
curl --location 'http://votre-serveur.com/index.php' \
--form 'username="VOTRE_IDENTIFIANT_KELIO"' \
--form 'token="VOTRE_TOKEN_DE_SESSION"'
```

**Avantages** :
- ✅ Plus rapide (pas de connexion à Kelio)
- ✅ Pas besoin d'envoyer le mot de passe à chaque requête
- ✅ Utilise les données en cache
- ✅ Réduit la charge sur le serveur Kelio

##### Avec HTTPie

```bash
# Avec identifiants
http -f POST http://votre-serveur.com/index.php \
username="VOTRE_IDENTIFIANT_KELIO" \
password="VOTRE_MOT_DE_PASSE_KELIO"

# Avec token
http -f POST http://votre-serveur.com/index.php \
username="VOTRE_IDENTIFIANT_KELIO" \
token="VOTRE_TOKEN_DE_SESSION"
```

##### Avec Postman

- Méthode : `POST`
- URL : `http://votre-serveur.com/index.php`
- Body : `form-data`

  **Option 1 : Avec identifiants**
  - `username` : votre identifiant Kelio
  - `password` : votre mot de passe Kelio

  **Option 2 : Avec token**
  - `username` : votre identifiant Kelio
  - `token` : votre token de session

#### Mise à jour des préférences utilisateur

Pour mettre à jour les préférences (thème, heures hebdomadaires), utilisez l'endpoint avec un token de session :

```bash
curl --location 'http://votre-serveur.com/index.php' \
--form 'action="update_preferences"' \
--form 'username="VOTRE_IDENTIFIANT"' \
--form 'token="VOTRE_TOKEN_DE_SESSION"' \
--form 'theme="ocean"' \
--form 'minutes_objective="2280"'
```

**Paramètres** :
- `action` : doit être `update_preferences`
- `username` : votre identifiant Kelio
- `token` : token de session obtenu lors de l'authentification
- `theme` (optionnel) : nom du thème (`midnight`, `light`, `abyss`, `ocean`, `forest`, `sunset`, `lavender`)
- `minutes_objective` (optionnel) : objectif hebdomadaire en minutes (ex: 2280 = 38h)

**Sécurité** : Le token est obligatoire et est validé côté serveur. Sans token valide, la requête sera refusée avec une erreur 401.

### Routes PWA (Progressive Web App)

L'API fournit deux endpoints pour la génération dynamique d'icônes et de manifest PWA avec personnalisation des couleurs.

#### Génération d'icône SVG

```bash
# Icône avec couleurs par défaut (midnight theme)
curl http://votre-serveur.com/icon.svg

# Icône avec couleurs personnalisées
curl "http://votre-serveur.com/icon.svg?primary=4F46E5&secondary=6366F1"
```

**Paramètres** :
- `primary` (optionnel) : Couleur primaire en hexadécimal (sans #). Défaut : `4F46E5`
- `secondary` (optionnel) : Couleur secondaire en hexadécimal (sans #). Défaut : `6366F1`

**Réponse** : Image SVG avec gradient utilisant les couleurs spécifiées

#### Génération de manifest PWA

```bash
# Manifest avec couleurs par défaut
curl http://votre-serveur.com/manifest.json

# Manifest avec couleurs personnalisées
curl "http://votre-serveur.com/manifest.json?primary=4F46E5&secondary=6366F1&background=1a1d29"
```

**Paramètres** :
- `primary` (optionnel) : Couleur primaire pour l'icône. Défaut : `4F46E5`
- `secondary` (optionnel) : Couleur secondaire pour l'icône. Défaut : `6366F1`
- `background` (optionnel) : Couleur de fond de l'application. Défaut : `1a1d29`

**Réponse** : Manifest JSON avec les icônes et couleurs configurées

```json
{
    "name": "Quel io",
    "short_name": "Quel io",
    "description": "Suivez vos horaires de travail",
    "start_url": "/",
    "display": "standalone",
    "background_color": "#1a1d29",
    "theme_color": "#1a1d29",
    "orientation": "portrait",
    "icons": [
        {
            "src": "/api/icon.svg?primary=4F46E5&secondary=6366F1",
            "sizes": "512x512",
            "type": "image/svg+xml",
            "purpose": "any maskable"
        }
    ]
}
```

## Réponse de l'API

### Réponse en cas de succès (login)

#### Authentification avec identifiants (données fraîches)

```json
{
    "authenticated_with": "credentials",
    "hours": {
        "21-10-2024": ["08:30", "12:00", "13:00", "18:30"],
        "22-10-2024": ["08:45", "12:15", "13:15", "17:45"],
        "23-10-2024": ["09:00"]
    },
    "total_effective": "15:45",
    "total_paid": "16:13",
    "preferences": {
        "theme": "ocean",
        "minutes_objective": 2280
    },
    "token": "a1b2c3d4e5f6...abc123:1234567890",
    "data_saved": true,
    "data_file_path": "/tmp/kelio_data.json",
    "cache": false
}
```

#### Authentification avec token (données en cache)

```json
{
    "authenticated_with": "token",
    "hours": {
        "21-10-2024": ["08:30", "12:00", "13:00", "18:30"],
        "22-10-2024": ["08:45", "12:15", "13:15", "17:45"],
        "23-10-2024": ["09:00"]
    },
    "total_effective": "15:45",
    "total_paid": "16:13",
    "last_save": "23/10/2024 18:30:15",
    "preferences": {
        "theme": "ocean",
        "minutes_objective": 2280
    },
    "token": "a1b2c3d4e5f6...abc123:1234567890",
    "cache": true
}
```

#### Champs de la réponse

- **authenticated_with** : Méthode d'authentification utilisée (`"credentials"` ou `"token"`)
- **hours** : Objet contenant les heures badgées par jour (format `JJ-MM-AAAA`)
- **total_effective** : Total des heures travaillées sans les pauses (format `HH:MM`)
- **total_paid** : Total des heures payées avec les pauses incluses (format `HH:MM`)
- **preferences** : Préférences utilisateur (thème, objectif hebdomadaire)
- **token** : Token de session pour les requêtes futures (valide jusqu'au changement de mot de passe Kelio)
- **cache** : Indique si les données proviennent du cache (`true`) ou sont fraîches (`false`)
- **last_save** : Date de la dernière sauvegarde (uniquement avec authentification par token)
- **data_saved** : Indique si les données ont été sauvegardées (uniquement avec authentification par credentials)
- **data_file_path** : Chemin du fichier de cache (uniquement avec authentification par credentials)

### Réponse en cas de succès (update_preferences)

```json
{
    "success": true,
    "preferences": {
        "theme": "ocean",
        "minutes_objective": 2280
    }
}
```

### Réponse avec données en cache (fallback)

Si la connexion à Kelio échoue mais que des données en cache existent :

```json
{
    "error": "Failed to fetch fresh data, using cached data",
    "fallback": true,
    "hours": {
        "21-10-2024": ["08:30", "12:00", "13:00", "18:30"]
    },
    "total_effective": "08:00",
    "total_paid": "08:14",
    "last_save": "21/10/2024 18:45:32",
    "preferences": {
        "theme": "ocean",
        "minutes_objective": 2280
    },
    "token": "a1b2c3d4e5f6...abc123:1234567890"
}
```

### Réponse en cas d'erreur

#### Erreurs d'authentification

```json
{
    "error": "username is required"
}
```

```json
{
    "error": "password or token required"
}
```

```json
{
    "error": "Invalid or expired token"
}
```
Statut HTTP : `401 Unauthorized`

```json
{
    "error": "No cached data found for this user"
}
```
Statut HTTP : `404 Not Found` (lors de l'authentification par token sans données en cache)

```json
{
    "error": "No fresh data available and no cached data found"
}
```

#### Erreurs de token (update_preferences)

```json
{
    "error": "Invalid or expired token"
}
```

Statut HTTP : `401 Unauthorized`

**Note** : Un token devient invalide si le mot de passe Kelio change. L'utilisateur doit se reconnecter pour obtenir un nouveau token.

## Fonctionnement détaillé

### Calcul des heures

Le script applique les règles suivantes :
- Les heures sont limitées entre **8h30** et **18h30** (heures de travail standard)
- Les pauses sont ajoutées automatiquement :
  - Pause du matin : ajoutée si vous travaillez après 11h00
  - Pause de l'après-midi : ajoutée si vous travaillez après 16h00
- Pour le jour en cours avec un nombre impair de badgeages, l'heure de fin est l'heure actuelle

### Gestion du cache

- Les données sont sauvegardées après chaque récupération réussie
- En cas d'échec de connexion, les données en cache sont retournées
- Le cache inclut la date et l'heure de la dernière sauvegarde
- Les préférences utilisateur sont également sauvegardées en cache

### Sécurité

#### Authentification Kelio
- Les mots de passe ne sont jamais stockés en clair
- Le script utilise les tokens CSRF de Kelio pour l'authentification
- Le formulaire HTML peut être désactivé (`enable_form_access = false`) pour limiter l'accès à l'API uniquement

#### Tokens de session
- **Génération** : Un token unique est généré à chaque connexion réussie
- **Format** : SHA-256 hash du username, password et timestamp, suivi du timestamp
- **Durée de vie** : Pas d'expiration automatique (Kelio force déjà le changement de mot de passe périodiquement)
- **Sécurité** :
  - Les tokens sont requis pour modifier les préférences utilisateur
  - Chaque utilisateur a son propre token
  - Les tokens sont validés côté serveur avant toute modification
  - Un token invalide retourne une erreur 401
  - Si le mot de passe Kelio change, le token devient automatiquement invalide (nouveau hash)

#### Protection des données
- Les données sont stockées dans `data.json` (non accessible via HTTP si configuré correctement)
- Chaque utilisateur ne peut modifier que ses propres préférences (via token)
- Les préférences incluent : thème de l'interface, objectif d'heures hebdomadaires

## Exemples d'utilisation

### Script bash pour afficher les heures

```bash
#!/bin/bash

KELIO_USER="votre.nom"
KELIO_PASS="votre_mot_de_passe"
API_URL="http://votre-serveur.com/index.php"

response=$(curl -s --form "username=$KELIO_USER" --form "password=$KELIO_PASS" "$API_URL")

echo "Heures effectives : $(echo $response | jq -r '.total_effective')"
echo "Heures payées : $(echo $response | jq -r '.total_paid')"
```

### Script Python

```python
import requests

url = "http://votre-serveur.com/index.php"
data = {
    "username": "votre.nom",
    "password": "votre_mot_de_passe"
}

response = requests.post(url, data=data)
result = response.json()

print(f"Heures effectives : {result['total_effective']}")
print(f"Heures payées : {result['total_paid']}")
```

### JavaScript (Node.js)

```javascript
const axios = require('axios');
const FormData = require('form-data');

const form = new FormData();
form.append('username', 'votre.nom');
form.append('password', 'votre_mot_de_passe');

axios.post('http://votre-serveur.com/index.php', form, {
    headers: form.getHeaders()
})
.then(response => {
    console.log('Heures effectives :', response.data.total_effective);
    console.log('Heures payées :', response.data.total_paid);
})
.catch(error => console.error(error));
```

## Dépannage

### ⚠️ IMPORTANT : Blocage de compte Kelio

**Kelio peut bloquer temporairement votre compte si vous effectuez trop de tentatives de connexion avec des identifiants incorrects.**

Symptômes :
- L'API retourne une erreur générique (souvent "Login failed" ou "Unable to get CSRF token")
- Le script ne parvient pas à scrapper les données
- Vous ne pouvez plus vous connecter même avec les bons identifiants

Solutions :
1. **Vérifiez vos identifiants** en vous connectant manuellement sur le site Kelio
2. **Attendez quelques minutes** (généralement 15-30 minutes) avant de réessayer
3. **Limitez la fréquence des appels** à l'API pour éviter de déclencher les protections anti-spam de Kelio
4. **Utilisez le cache** : l'API retourne automatiquement les données en cache si la connexion échoue

Recommandations :
- Ne testez pas l'API en boucle avec des identifiants incorrects
- Mettez en place un système de cache côté client pour éviter d'interroger l'API trop souvent
- Utilisez l'API avec parcimonie (1 fois par heure maximum recommandé)

### Le fichier de cache n'est pas créé

Vérifiez les permissions d'écriture :
```bash
chmod 777 /tmp
# ou
chmod 777 /chemin/vers/votre/projet
```

### Erreur "Login failed"

Causes possibles :
- ⚠️ **Votre compte Kelio est temporairement bloqué** (voir section ci-dessus)
- L'URL Kelio est incorrecte dans votre `config.php`
- Vos identifiants sont incorrects
- Un firewall bloque l'accès à Kelio

Solutions :
1. Vérifiez en vous connectant manuellement sur Kelio
2. Attendez 15-30 minutes si le compte est bloqué
3. Vérifiez la configuration dans `config.php`

### Erreur "Unable to get CSRF token"

Causes possibles :
- ⚠️ **Votre compte Kelio est temporairement bloqué** (voir section ci-dessus)
- L'URL Kelio est incorrecte dans votre `config.php`
- L'instance Kelio est indisponible
- Le format de la page de connexion a changé

Solutions :
1. Vérifiez l'URL dans `config.php` (doit être au format `https://entreprise.kelio.io`)
2. Testez l'accès manuel à votre instance Kelio
3. Attendez que le service soit rétabli

### Erreur générique avec données en cache

Si vous recevez :
```json
{
    "error": "Failed to fetch fresh data, using cached data",
    "fallback": true,
    ...
}
```

Cela signifie que :
- La connexion à Kelio a échoué (compte bloqué, identifiants incorrects, service indisponible)
- L'API vous retourne les dernières données sauvegardées
- Les données peuvent ne pas être à jour

Action recommandée :
- Vérifiez la date de la dernière sauvegarde dans le champ `last_save`
- Attendez quelques minutes et réessayez
- Vérifiez que votre compte n'est pas bloqué en vous connectant manuellement

## Licence

Projet libre d'utilisation.
