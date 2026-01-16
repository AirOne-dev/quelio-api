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

    // ========================================================================
    // LOGIN ACTION - FETCH FRESH DATA
    // ========================================================================

    public function test_login_action_calls_fetch_fresh_data(): void
    {
        $username = 'testuser';
        $password = 'testpass';
        $jsessionid = 'TEST_SESSION_123';

        // Setup authenticated context (simulates middleware already authenticated)
        $this->authContext->setCredentialsAuth($username, $password, $jsessionid);

        // Mock KelioClient to return test data
        $mockClient = $this->createMock(KelioClient::class);
        $mockClient->expects($this->once())
            ->method('fetchAllHours')
            ->with($jsessionid)
            ->willReturn([
                ['12/01/2026' => ['08:30', '12:00']],
                ['12/01/2026' => ['13:00', '17:30']],
                []
            ]);

        // Create controller with mocked client
        $controller = new BaseController(
            $this->storage,
            $this->auth,
            $mockClient,
            $this->timeCalculator,
            $this->authContext,
            $this->getConfig()
        );

        ob_start();
        $controller->loginAction();
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertNotNull($response);
        $this->assertIsArray($response);
        $this->assertArrayHasKey('hours', $response);
        $this->assertArrayHasKey('total_effective', $response);
        $this->assertArrayHasKey('total_paid', $response);
    }

    public function test_fetch_fresh_data_handles_missing_jsessionid(): void
    {
        $username = 'testuser';
        $password = 'testpass';

        // Create a mock AuthContext that returns null for jsessionid
        $mockAuthContext = $this->createMock(AuthContext::class);
        $mockAuthContext->method('getUsername')->willReturn($username);
        $mockAuthContext->method('getPassword')->willReturn($password);
        $mockAuthContext->method('getJSessionId')->willReturn(null);
        $mockAuthContext->method('getOrGenerateToken')->willReturn('test_token');

        $controller = new BaseController(
            $this->storage,
            $this->auth,
            $this->kelioClient,
            $this->timeCalculator,
            $mockAuthContext,
            $this->getConfig()
        );

        ob_start();
        $controller->loginAction();
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertArrayHasKey('error', $response);
        $this->assertStringContainsString('Failed to fetch data from Kelio', $response['error']);
        $this->assertTrue($response['token_invalidated'] ?? false);
    }

    public function test_fetch_fresh_data_handles_kelio_client_exception(): void
    {
        $username = 'testuser';
        $password = 'testpass';
        $jsessionid = 'TEST_SESSION_123';

        $this->authContext->setCredentialsAuth($username, $password, $jsessionid);

        // Mock KelioClient to throw exception
        $mockClient = $this->createMock(KelioClient::class);
        $mockClient->expects($this->once())
            ->method('fetchAllHours')
            ->with($jsessionid)
            ->willThrowException(new \Exception('Kelio API error'));

        $controller = new BaseController(
            $this->storage,
            $this->auth,
            $mockClient,
            $this->timeCalculator,
            $this->authContext,
            $this->getConfig()
        );

        ob_start();
        $controller->loginAction();
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertArrayHasKey('error', $response);
        $this->assertStringContainsString('Failed to fetch data from Kelio', $response['error']);
        $this->assertTrue($response['token_invalidated'] ?? false);
    }

    public function test_fetch_fresh_data_saves_user_data_on_success(): void
    {
        $username = 'testuser';
        $password = 'testpass';
        $jsessionid = 'TEST_SESSION_123';

        $this->authContext->setCredentialsAuth($username, $password, $jsessionid);

        // Mock KelioClient
        $mockClient = $this->createMock(KelioClient::class);
        $mockClient->expects($this->once())
            ->method('fetchAllHours')
            ->willReturn([
                ['12/01/2026' => ['08:30', '12:00']],
                ['12/01/2026' => ['13:00', '17:30']],
                []
            ]);

        $controller = new BaseController(
            $this->storage,
            $this->auth,
            $mockClient,
            $this->timeCalculator,
            $this->authContext,
            $this->getConfig()
        );

        ob_start();
        $controller->loginAction();
        ob_end_clean();

        // Verify data was saved
        $userData = $this->storage->getUserData($username);
        $this->assertNotNull($userData);
        $this->assertArrayHasKey('hours', $userData);
        $this->assertArrayHasKey('total_effective', $userData);
        $this->assertArrayHasKey('total_paid', $userData);
    }

    public function test_fetch_fresh_data_invalidates_token_on_error(): void
    {
        $username = 'testuser';
        $password = 'testpass';
        $jsessionid = 'TEST_SESSION_123';

        $this->authContext->setCredentialsAuth($username, $password, $jsessionid);

        // Mock KelioClient to throw exception
        $mockClient = $this->createMock(KelioClient::class);
        $mockClient->expects($this->once())
            ->method('fetchAllHours')
            ->willThrowException(new \Exception('Kelio API error'));

        $controller = new BaseController(
            $this->storage,
            $this->auth,
            $mockClient,
            $this->timeCalculator,
            $this->authContext,
            $this->getConfig()
        );

        ob_start();
        $controller->loginAction();
        $output = ob_get_clean();

        // Verify error response
        $response = json_decode($output, true);
        $this->assertArrayHasKey('error', $response);
        $this->assertStringContainsString('Failed to fetch data from Kelio', $response['error']);
        $this->assertTrue($response['token_invalidated'] ?? false);
    }
}
