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
 * POST /               -> BaseGuestController::dispatch('login')
 * POST /?action=update_preferences -> BaseController::updatePreferencesAction()
 * GET  /icon.svg       -> IconController::indexAction()
 * GET  /manifest.json  -> ManifestController::indexAction()
 */

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
    'rate_limit_window' => 'integer',
    'admin_username' => 'string',
    'admin_password' => 'string'
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

if (!empty($errors)) {
    http_response_code(500);
    header('Content-Type: application/json');
    die(json_encode([
        'error' => 'Configuration validation failed',
        'details' => $errors
    ], JSON_PRETTY_PRINT));
}

$container = new Container();

(new ServiceProvider($container, $config))->register();
(new Router())
    ->setContainer($container)
    ->get('/', BaseGuestController::class)
    ->post('/', BaseController::class, [AuthMiddleware::class])
    ->get('/icon.svg', IconController::class)
    ->get('/manifest.json', ManifestController::class)
    ->getAndPost('/data.json', DataController::class, [[AuthMiddleware::class, 'admin']])
    ->run();
