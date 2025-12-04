<?php

/*
 * Quel.io API - MVC Architecture with Auto-loading & Auto-wiring
 *
 * Features:
 * - PSR-4 Autoloading: All classes are loaded automatically
 * - Auto-wiring: Dependencies are injected automatically
 * - Convention-based routing: Clean and simple route definitions
 * - Action Controllers: ?action= parameters dispatch to methods
 *
 * Routes examples:
 * GET  /               -> HomeController::indexAction()
 * POST /               -> LoginController::dispatch('login')
 * POST /?action=update_preferences -> LoginController::updatePreferencesAction()
 * GET  /icon.svg       -> IconController::indexAction()
 * GET  /manifest.json  -> ManifestController::indexAction()
 */

// ========================================
// 1. Bootstrap Application
// ========================================

// Load core dependencies (only 3 files needed!)
require_once __DIR__ . '/src/core/Container.php';
require_once __DIR__ . '/src/core/Autoloader.php';
require_once __DIR__ . '/src/core/ServiceProvider.php';

// Setup autoloader
$autoloader = new Autoloader(__DIR__ . '/src');
$autoloader->register();

// Load configuration
$config = require __DIR__ . '/config.php';

// Validate configuration
$requiredKeys = [
    'kelio_url' => 'string',
    'pause_time' => 'integer',
    'start_limit_minutes' => 'integer',
    'end_limit_minutes' => 'integer',
    'morning_break_threshold' => 'integer',
    'afternoon_break_threshold' => 'integer',
    'enable_form_access' => 'boolean',
    'encryption_key' => 'string',
    'debug_mode' => 'boolean',
    'rate_limit_max_attempts' => 'integer',
    'rate_limit_window' => 'integer'
];

$errors = [];

foreach ($requiredKeys as $key => $expectedType) {
    if (!isset($config[$key])) {
        $errors[] = "Missing required configuration: $key";
        continue;
    }

    $actualType = gettype($config[$key]);

    // Map PHP types to expected types
    $typeMap = [
        'integer' => 'integer',
        'string' => 'string',
        'boolean' => 'boolean',
        'double' => 'number'
    ];

    $expectedPhpType = $typeMap[$expectedType] ?? $expectedType;

    if ($actualType !== $expectedPhpType) {
        $errors[] = "Configuration '$key' must be of type $expectedType (got $actualType)";
    }
}

// Validate specific values
if (isset($config['encryption_key']) && strlen($config['encryption_key']) < 16) {
    $errors[] = "Configuration 'encryption_key' must be at least 16 characters long";
}

if (isset($config['pause_time']) && ($config['pause_time'] < 0 || $config['pause_time'] > 120)) {
    $errors[] = "Configuration 'pause_time' must be between 0 and 120 minutes";
}

if (isset($config['start_limit_minutes']) && ($config['start_limit_minutes'] < 0 || $config['start_limit_minutes'] > 1440)) {
    $errors[] = "Configuration 'start_limit_minutes' must be between 0 and 1440";
}

if (isset($config['end_limit_minutes']) && ($config['end_limit_minutes'] < 0 || $config['end_limit_minutes'] > 1440)) {
    $errors[] = "Configuration 'end_limit_minutes' must be between 0 and 1440";
}

if (isset($config['rate_limit_max_attempts']) && $config['rate_limit_max_attempts'] < 1) {
    $errors[] = "Configuration 'rate_limit_max_attempts' must be at least 1";
}

if (isset($config['rate_limit_window']) && $config['rate_limit_window'] < 1) {
    $errors[] = "Configuration 'rate_limit_window' must be at least 1 second";
}

if (!empty($errors)) {
    http_response_code(500);
    header('Content-Type: application/json');
    die(json_encode([
        'error' => 'Configuration validation failed',
        'details' => $errors
    ], JSON_PRETTY_PRINT));
}

// ========================================
// 2. Configure Services (Auto-wiring)
// ========================================

$container = new Container();
$serviceProvider = new ServiceProvider($container, $config);
$serviceProvider->register();

// ========================================
// 3. Configure Routes (Convention-based)
// ========================================

$router = new Router();
$router->setContainer($container);

// Home (GET)
$router->route('GET', '/', HomeController::class, [AuthMiddleware::class]);

// Login/Auth (POST with actions)
$router->route('POST', '/', LoginController::class, [AuthMiddleware::class]);

// PWA Routes
$router->route('GET', '/icon.svg', IconController::class);
$router->route('GET', '/manifest.json', ManifestController::class);

// ========================================
// Example: Add a protected route
// ========================================
// $router->route('POST', '/stats', StatsController::class, [AuthMiddleware::class]);

// ========================================
// 4. Run Application
// ========================================

$router->run();
