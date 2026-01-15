<?php

/**
 * PHPUnit Bootstrap File
 * Sets up the testing environment
 */

// Load the autoloader
require_once __DIR__ . '/../src/core/Autoloader.php';
$autoloader = new Autoloader(__DIR__ . '/../src');
$autoloader->register();

// Load test configuration
$testConfig = [
    'kelio_url' => 'https://daryl.kelio.io',
    'pause_time' => 7,
    'start_limit_minutes' => 8 * 60 + 30,
    'end_limit_minutes' => 18 * 60 + 30,
    'morning_break_threshold' => 11 * 60,
    'afternoon_break_threshold' => 16 * 60,
    'noon_minimum_break' => 60,
    'noon_break_start' => 12 * 60,
    'noon_break_end' => 14 * 60,
    'enable_form_access' => false,
    'encryption_key' => 'TEST_KEY_FOR_UNIT_TESTS_ONLY_32CHARS',
    'debug_mode' => true,
    'rate_limit_max_attempts' => 5,
    'rate_limit_window' => 300,
    'admin_username' => 'test_admin',
    'admin_password' => 'test_password',
];

// Make config available globally for tests
define('TEST_CONFIG', $testConfig);

// Set timezone
date_default_timezone_set('Europe/Paris');
