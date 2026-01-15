# Testing Documentation

Guide complet pour exécuter et maintenir les tests de l'API Quel.io.

## Vue d'ensemble

Le projet utilise **PHPUnit 10.5** avec une architecture de tests en 3 niveaux :
- **Unit Tests** : Tests unitaires des services et contrôleurs
- **Integration Tests** : Tests d'intégration multi-composants
- **Feature Tests** : Tests end-to-end (à venir)

## Installation

### Prérequis
- Docker installé et en cours d'exécution
- Make (optionnel, pour les commandes simplifiées)

### Installer les dépendances

```bash
make install
# Ou directement :
docker run --rm -v "$(pwd):/app" -w /app composer:latest install
```

## Exécution des Tests

### Commandes rapides (Makefile)

```bash
# Afficher toutes les commandes disponibles
make help

# Exécuter tous les tests
make test

# Tests unitaires uniquement
make test-unit

# Tests d'intégration uniquement
make test-integration

# Test spécifique
make test-filter FILTER=TimeCalculatorTest

# Générer un rapport de couverture
make test-coverage
```

### Commandes détaillées (run-tests.sh)

```bash
# Tous les tests
./run-tests.sh

# Tests unitaires
./run-tests.sh --unit

# Tests d'intégration
./run-tests.sh --integration

# Test spécifique
./run-tests.sh --filter TimeCalculatorTest

# Avec couverture de code
./run-tests.sh --coverage
```

### Exécution directe avec Docker

```bash
# PHPUnit via Docker
docker run --rm -v "$(pwd):/app" -w /app php:8.2-cli \
  ./vendor/bin/phpunit --testdox

# Avec options
docker run --rm -v "$(pwd):/app" -w /app php:8.2-cli \
  ./vendor/bin/phpunit --testdox --filter=AuthTest
```

## Structure des Tests

```
tests/
├── bootstrap.php              # Configuration des tests
├── TestCase.php               # Classe de base avec helpers
├── Mocks/
│   └── KelioMock.php         # Mocks pour l'API Kelio
├── Unit/
│   ├── Services/
│   │   ├── TimeCalculatorTest.php
│   │   ├── AuthTest.php
│   │   ├── StorageTest.php
│   │   └── RateLimiterTest.php
│   └── Controllers/
│       ├── IconControllerTest.php
│       └── ManifestControllerTest.php
└── Integration/
    ├── AuthenticationFlowTest.php
    └── TimeCalculationFlowTest.php
```

## Tests Disponibles

### Services (Unit Tests)

#### TimeCalculatorTest
- ✓ Fusion des heures par jour
- ✓ Calcul des heures effectives
- ✓ Calcul des heures payées avec bonus
- ✓ Règle de la pause minimum du midi
- ✓ Gestion des longues pauses
- ✓ Calcul multi-jours
- ✓ Limitation de déduction au montant des bonus

#### AuthTest
- ✓ Génération de tokens
- ✓ Validation de tokens
- ✓ Extraction du nom d'utilisateur
- ✓ Extraction du mot de passe
- ✓ Présence du timestamp
- ✓ Présence de la signature

#### StorageTest
- ✓ Sauvegarde et chargement de données
- ✓ Gestion des fichiers inexistants
- ✓ Préférences utilisateur
- ✓ Mise à jour des tokens de session
- ✓ Invalidation des tokens
- ✓ Pretty-print en mode debug
- ✓ Minification en mode production

#### RateLimiterTest
- ✓ Autorisation de la première tentative
- ✓ Blocage après le nombre maximum de tentatives
- ✓ Réinitialisation après succès
- ✓ Indépendance entre IPs
- ✓ Nettoyage des tentatives expirées

### Contrôleurs (Unit Tests)

#### IconControllerTest
- ✓ Génération d'icône SVG
- ✓ Couleurs par défaut
- ✓ Validation des couleurs hexadécimales
- ✓ Suppression du préfixe #
- ✓ Présence du gradient
- ✓ Présence de l'icône horloge

#### ManifestControllerTest
- ✓ Génération d'un manifest valide
- ✓ Utilisation de couleurs personnalisées
- ✓ Validation du format des couleurs
- ✓ Inclusion des URLs d'icônes
- ✓ Mode d'affichage standalone
- ✓ Orientation portrait

### Integration Tests

#### AuthenticationFlowTest
- ✓ Flux de connexion complet
- ✓ Flux d'invalidation de token
- ✓ Flux de mise à jour des préférences

#### TimeCalculationFlowTest
- ✓ Calcul et stockage complets des heures
- ✓ Accumulation hebdomadaire

## Écrire de Nouveaux Tests

### Structure de base

```php
<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use MyService;

class MyServiceTest extends TestCase
{
    private MyService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MyService($this->getConfig());
    }

    public function test_does_something(): void
    {
        $result = $this->service->doSomething();

        $this->assertEquals('expected', $result);
    }
}
```

### Helpers disponibles

```php
// Configuration
$config = $this->getConfig();

// Fichiers temporaires
$file = $this->createTempFile('content');
$this->cleanupTempFiles([$file]);

// Mocking
$mock = $this->mockMethod(MyClass::class, 'method', 'returnValue');

// Assertions
$this->assertArrayHasKeys(['key1', 'key2'], $array);

// Capture de sortie
$output = $this->captureOutput(function() {
    echo "test";
});
```

### Mocks Kelio

```php
use Tests\Mocks\KelioMock;

// Page de login
$html = KelioMock::getLoginPage();

// Page d'heures
$html = KelioMock::getHoursPage([
    '13-01-2026' => ['08:30', '18:30']
]);

// Données mockées
$hours = KelioMock::getMockHoursData();

// Cookie de session
$cookie = KelioMock::getMockSessionCookie();
```

## Couverture de Code

Pour générer un rapport de couverture :

```bash
make test-coverage
```

Le rapport HTML sera généré dans `coverage/index.html`.

## Bonnes Pratiques

### Nommage
- Préfixe `test_` pour les méthodes de test
- Noms descriptifs en snake_case
- Exemple : `test_calculates_hours_with_noon_break`

### Organisation
- Un fichier de test par classe
- Tests groupés par fonctionnalité
- Ordre logique : setUp → test → tearDown

### Assertions
- Une assertion principale par test
- Assertions spécifiques (assertArrayHasKey vs assertEquals)
- Messages d'erreur clairs

### Isolation
- Utiliser setUp/tearDown pour la préparation/nettoyage
- Pas d'état partagé entre tests
- Fichiers temporaires nettoyés

### Mock vs Réel
- **Unit Tests** : Mock les dépendances externes
- **Integration Tests** : Utiliser de vraies implémentations
- **Feature Tests** : End-to-end complet

## Debugging

### Afficher les sorties

```bash
# Avec --debug
docker run --rm -v "$(pwd):/app" -w /app php:8.2-cli \
  ./vendor/bin/phpunit --testdox --debug

# Avec var_dump dans les tests (visible en cas d'échec)
var_dump($variable);
```

### Test spécifique

```bash
# Un seul test
make test-filter FILTER=test_generates_valid_token

# Une classe
make test-filter FILTER=AuthTest

# Un namespace
./run-tests.sh --filter="Tests\\Unit\\Services"
```

### Mode verbeux

```bash
# Avec PHPUnit directement
./vendor/bin/phpunit --testdox --verbose
```

## CI/CD

Pour intégrer dans un pipeline CI :

```yaml
# .github/workflows/tests.yml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Run tests
        run: |
          cd api
          ./run-tests.sh
```

## Maintenance

### Ajouter un nouveau test

1. Créer le fichier dans le bon dossier (Unit/Integration/Feature)
2. Étendre `Tests\TestCase`
3. Implémenter les méthodes de test
4. Exécuter : `make test`

### Mettre à jour les mocks

Les mocks Kelio sont dans `tests/Mocks/KelioMock.php`. Mettre à jour si l'API Kelio change.

### Nettoyer

```bash
make clean
```

Cela supprime :
- vendor/
- .phpunit.cache/
- coverage/

## Troubleshooting

### Docker n'est pas accessible

Vérifier que Docker est démarré :
```bash
docker info
```

### Erreur de permissions

```bash
chmod +x run-tests.sh
```

### Tests échouent sur fichiers temporaires

Vérifier que `/tmp` est accessible et inscriptible.

### Composer est lent

Utiliser le cache Docker :
```bash
docker run --rm \
  -v "$(pwd):/app" \
  -v ~/.composer:/tmp/composer \
  -w /app \
  composer:latest install
```

## Statistiques

Commande pour obtenir les statistiques :

```bash
./vendor/bin/phpunit --testdox | grep -E "(Tests:|Assertions:)"
```

## Support

Pour toute question ou problème :
- Lire cette documentation
- Consulter `phpunit.xml` pour la configuration
- Vérifier `tests/bootstrap.php` pour l'initialisation
