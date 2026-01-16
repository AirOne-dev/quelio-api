<?php

namespace Tests\Unit\Controllers;

use Tests\TestCase;
use BaseController;
use Storage;
use Auth;
use KelioClient;
use TimeCalculator;
use AuthContext;

/**
 * Unit Tests - BaseController
 * Tests main business logic controller (login, preferences update)
 */
class BaseControllerTest extends TestCase
{
    private BaseController $controller;
    private Storage $storage;
    private Auth $auth;
    private KelioClient $kelioClient;
    private TimeCalculator $timeCalculator;
    private AuthContext $authContext;

    protected function setUp(): void
    {
        parent::setUp();

        // Create dependencies with proper DI
        $config = $this->getConfig();
        $this->storage = new Storage(true);
        $this->auth = new Auth($this->storage, $config['encryption_key']);
        $this->kelioClient = new KelioClient($config['kelio_url']);
        $this->timeCalculator = new TimeCalculator($config);
        $this->authContext = new AuthContext();
        $this->authContext->setServices($this->auth, $this->storage);

        // Create controller
        $this->controller = new BaseController(
            $this->storage,
            $this->auth,
            $this->kelioClient,
            $this->timeCalculator,
            $this->authContext,
            $config
        );

        // Clean up $_POST, $_GET
        $_POST = [];
        $_GET = [];
    }

    protected function tearDown(): void
    {
        // Clean up test files
        $dataFile = $this->storage->getDataFilePath();
        if (file_exists($dataFile)) {
            unlink($dataFile);
        }

        parent::tearDown();
    }

    // ========================================================================
    // UPDATE PREFERENCES - THEME
    // ========================================================================

    public function test_updates_theme_preference(): void
    {
        $username = 'testuser';
        $password = 'testpass';
        $jsessionid = 'TEST_SESSION_123';

        // Setup authenticated context
        $this->authContext->setCredentialsAuth($username, $password, $jsessionid);
        $this->storage->saveUserData($username, [], '00:00', '00:00');

        $_POST['theme'] = 'ocean';

        // Capture JSON output
        ob_start();
        $this->controller->updatePreferencesAction();
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertNotNull($response);
        $this->assertTrue($response['success'] ?? false);
        $this->assertEquals($username, $response['username']);
        $this->assertEquals('ocean', $response['preferences']['theme']);
    }

    public function test_validates_theme_format(): void
    {
        $username = 'testuser';
        $password = 'testpass';
        $jsessionid = 'TEST_SESSION_123';

        $this->authContext->setCredentialsAuth($username, $password, $jsessionid);
        $this->storage->saveUserData($username, [], '00:00', '00:00');

        $_POST['theme'] = 'invalid theme!@#'; // Invalid characters

        ob_start();
        $this->controller->updatePreferencesAction();
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertNotNull($response);
        $this->assertArrayHasKey('error', $response);
        $this->assertArrayHasKey('fields', $response); // Validation errors in 'fields'
        $this->assertArrayHasKey('theme', $response['fields']);
    }

    public function test_accepts_valid_theme_characters(): void
    {
        $username = 'testuser';
        $password = 'testpass';
        $jsessionid = 'TEST_SESSION_123';

        $this->authContext->setCredentialsAuth($username, $password, $jsessionid);
        $this->storage->saveUserData($username, [], '00:00', '00:00');

        $_POST['theme'] = 'dark-mode_2024'; // Valid: alphanumeric, dash, underscore

        ob_start();
        $this->controller->updatePreferencesAction();
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertTrue($response['success'] ?? false);
        $this->assertEquals('dark-mode_2024', $response['preferences']['theme']);
    }

    public function test_rejects_theme_too_long(): void
    {
        $username = 'testuser';
        $password = 'testpass';
        $jsessionid = 'TEST_SESSION_123';

        $this->authContext->setCredentialsAuth($username, $password, $jsessionid);
        $this->storage->saveUserData($username, [], '00:00', '00:00');

        $_POST['theme'] = str_repeat('a', 51); // 51 characters (max is 50)

        ob_start();
        $this->controller->updatePreferencesAction();
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertArrayHasKey('error', $response);
        $this->assertArrayHasKey('fields', $response);
    }

    // ========================================================================
    // UPDATE PREFERENCES - MINUTES OBJECTIVE
    // ========================================================================

    public function test_updates_minutes_objective(): void
    {
        $username = 'testuser';
        $password = 'testpass';
        $jsessionid = 'TEST_SESSION_123';

        $this->authContext->setCredentialsAuth($username, $password, $jsessionid);
        $this->storage->saveUserData($username, [], '00:00', '00:00');

        $_POST['minutes_objective'] = '480'; // 8 hours

        ob_start();
        $this->controller->updatePreferencesAction();
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertTrue($response['success'] ?? false);
        $this->assertEquals(480, $response['preferences']['minutes_objective']);
    }

    public function test_rejects_zero_minutes_objective(): void
    {
        $username = 'testuser';
        $password = 'testpass';
        $jsessionid = 'TEST_SESSION_123';

        $this->authContext->setCredentialsAuth($username, $password, $jsessionid);
        $this->storage->saveUserData($username, [], '00:00', '00:00');

        $_POST['minutes_objective'] = '0';

        ob_start();
        $this->controller->updatePreferencesAction();
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertArrayHasKey('error', $response);
        $this->assertArrayHasKey('fields', $response);
        $this->assertArrayHasKey('minutes_objective', $response['fields']);
    }

    public function test_rejects_negative_minutes_objective(): void
    {
        $username = 'testuser';
        $password = 'testpass';
        $jsessionid = 'TEST_SESSION_123';

        $this->authContext->setCredentialsAuth($username, $password, $jsessionid);
        $this->storage->saveUserData($username, [], '00:00', '00:00');

        $_POST['minutes_objective'] = '-100';

        ob_start();
        $this->controller->updatePreferencesAction();
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertArrayHasKey('error', $response);
        $this->assertArrayHasKey('fields', $response);
    }

    // ========================================================================
    // UPDATE PREFERENCES - COMBINED
    // ========================================================================

    public function test_updates_multiple_preferences_at_once(): void
    {
        $username = 'testuser';
        $password = 'testpass';
        $jsessionid = 'TEST_SESSION_123';

        $this->authContext->setCredentialsAuth($username, $password, $jsessionid);
        $this->storage->saveUserData($username, [], '00:00', '00:00');

        $_POST['theme'] = 'ocean';
        $_POST['minutes_objective'] = '450';

        ob_start();
        $this->controller->updatePreferencesAction();
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertTrue($response['success'] ?? false);
        $this->assertEquals('ocean', $response['preferences']['theme']);
        $this->assertEquals(450, $response['preferences']['minutes_objective']);
    }

    public function test_merges_with_existing_preferences(): void
    {
        $username = 'testuser';
        $password = 'testpass';
        $jsessionid = 'TEST_SESSION_123';

        $this->authContext->setCredentialsAuth($username, $password, $jsessionid);
        $this->storage->saveUserData($username, [], '00:00', '00:00');

        // First update: theme only
        $_POST['theme'] = 'ocean';
        ob_start();
        $this->controller->updatePreferencesAction();
        ob_end_clean();

        // Second update: minutes_objective only
        $_POST = [];
        $_POST['minutes_objective'] = '480';
        ob_start();
        $this->controller->updatePreferencesAction();
        $output = ob_get_clean();

        $response = json_decode($output, true);

        // Both preferences should be present
        $this->assertEquals('ocean', $response['preferences']['theme']);
        $this->assertEquals(480, $response['preferences']['minutes_objective']);
    }

    public function test_rejects_empty_preferences(): void
    {
        $username = 'testuser';
        $password = 'testpass';
        $jsessionid = 'TEST_SESSION_123';

        $this->authContext->setCredentialsAuth($username, $password, $jsessionid);
        $this->storage->saveUserData($username, [], '00:00', '00:00');

        // No preferences provided
        $_POST = [];

        ob_start();
        $this->controller->updatePreferencesAction();
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertArrayHasKey('error', $response);
        $this->assertStringContainsString('No valid preferences', $response['error']);
    }

    public function test_partial_validation_failure(): void
    {
        $username = 'testuser';
        $password = 'testpass';
        $jsessionid = 'TEST_SESSION_123';

        $this->authContext->setCredentialsAuth($username, $password, $jsessionid);
        $this->storage->saveUserData($username, [], '00:00', '00:00');

        $_POST['theme'] = 'invalid!@#';
        $_POST['minutes_objective'] = '-100';

        ob_start();
        $this->controller->updatePreferencesAction();
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertArrayHasKey('error', $response);
        $this->assertArrayHasKey('fields', $response);
        $this->assertArrayHasKey('theme', $response['fields']);
        $this->assertArrayHasKey('minutes_objective', $response['fields']);
    }

    // ========================================================================
    // RESPONSE FORMAT
    // ========================================================================

    public function test_returns_token_in_response(): void
    {
        $username = 'testuser';
        $password = 'testpass';
        $jsessionid = 'TEST_SESSION_123';

        $this->authContext->setCredentialsAuth($username, $password, $jsessionid);
        $this->storage->saveUserData($username, [], '00:00', '00:00');

        $_POST['theme'] = 'ocean';

        ob_start();
        $this->controller->updatePreferencesAction();
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertArrayHasKey('token', $response);
        $this->assertNotEmpty($response['token']);
    }

    public function test_returns_username_in_response(): void
    {
        $username = 'testuser';
        $password = 'testpass';
        $jsessionid = 'TEST_SESSION_123';

        $this->authContext->setCredentialsAuth($username, $password, $jsessionid);
        $this->storage->saveUserData($username, [], '00:00', '00:00');

        $_POST['theme'] = 'ocean';

        ob_start();
        $this->controller->updatePreferencesAction();
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertEquals($username, $response['username']);
    }

    public function test_returns_all_preferences_in_response(): void
    {
        $username = 'testuser';
        $password = 'testpass';
        $jsessionid = 'TEST_SESSION_123';

        $this->authContext->setCredentialsAuth($username, $password, $jsessionid);
        $this->storage->saveUserData($username, [], '00:00', '00:00');

        // Set initial preferences
        $this->storage->saveUserPreferences($username, ['theme' => 'dark', 'minutes_objective' => 420]);

        // Update one preference
        $_POST['theme'] = 'ocean';

        ob_start();
        $this->controller->updatePreferencesAction();
        $output = ob_get_clean();

        $response = json_decode($output, true);

        // Should return all preferences, not just the updated one
        $this->assertArrayHasKey('preferences', $response);
        $this->assertEquals('ocean', $response['preferences']['theme']);
        $this->assertEquals(420, $response['preferences']['minutes_objective']);
    }
}
