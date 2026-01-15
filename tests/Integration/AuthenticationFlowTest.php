<?php

namespace Tests\Integration;

use Tests\TestCase;
use Auth;
use Storage;

/**
 * Integration test for complete authentication flow
 */
class AuthenticationFlowTest extends TestCase
{
    private Auth $auth;
    private Storage $storage;
    private string $testDataFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testDataFile = sys_get_temp_dir() . '/test_auth_flow_' . uniqid() . '.json';
        $this->auth = new Auth($this->getConfig());
        $this->storage = new Storage($this->getConfig(), $this->testDataFile);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testDataFile)) {
            unlink($this->testDataFile);
        }
        parent::tearDown();
    }

    public function test_complete_login_flow(): void
    {
        $username = 'testuser';
        $password = 'testpass';

        // Step 1: Generate token
        $token = $this->auth->generateToken($username, $password);
        $this->assertNotEmpty($token);

        // Step 2: Save user data with token
        $userData = [
            'users' => [
                $username => [
                    'hours' => ['13-01-2026' => ['08:30', '18:30']],
                    'total_effective' => '10:00',
                    'total_paid' => '10:14',
                    'session_token' => $token,
                    'preferences' => ['theme' => 'ocean']
                ]
            ]
        ];
        $this->storage->saveData($userData);

        // Step 3: Load data and validate token
        $data = $this->storage->loadData();
        $isValid = $this->auth->validateToken($token, $data);
        $this->assertTrue($isValid);

        // Step 4: Extract credentials from token
        $extractedUsername = $this->auth->extractUsernameFromToken($token);
        $extractedPassword = $this->auth->extractPasswordFromToken($token);

        $this->assertEquals($username, $extractedUsername);
        $this->assertEquals($password, $extractedPassword);
    }

    public function test_token_invalidation_flow(): void
    {
        $username = 'testuser';
        $token = $this->auth->generateToken($username, 'oldpass');

        // Save initial data
        $this->storage->saveData([
            'users' => [
                $username => [
                    'session_token' => $token
                ]
            ]
        ]);

        // Invalidate token
        $this->storage->invalidateToken($username);

        // Verify token is gone
        $data = $this->storage->loadData();
        $this->assertArrayNotHasKey('session_token', $data['users'][$username]);

        // New token should be needed
        $newToken = $this->auth->generateToken($username, 'newpass');
        $this->assertNotEquals($token, $newToken);
    }

    public function test_preference_update_flow(): void
    {
        $username = 'testuser';

        // Save initial preferences
        $this->storage->saveUserPreferences($username, [
            'theme' => 'midnight',
            'minutes_objective' => 2100
        ]);

        // Load and verify
        $prefs = $this->storage->getUserPreferences($username);
        $this->assertEquals('midnight', $prefs['theme']);
        $this->assertEquals(2100, $prefs['minutes_objective']);

        // Update preferences
        $this->storage->saveUserPreferences($username, [
            'theme' => 'ocean',
            'minutes_objective' => 2280
        ]);

        // Verify update
        $updatedPrefs = $this->storage->getUserPreferences($username);
        $this->assertEquals('ocean', $updatedPrefs['theme']);
        $this->assertEquals(2280, $updatedPrefs['minutes_objective']);
    }
}
