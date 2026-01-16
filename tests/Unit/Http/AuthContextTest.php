<?php

namespace Tests\Unit\Http;

use Tests\TestCase;
use AuthContext;
use Auth;
use Storage;

/**
 * Unit Tests - AuthContext
 * Tests authentication context state management
 */
class AuthContextTest extends TestCase
{
    private AuthContext $authContext;
    private Storage $storage;
    private Auth $auth;

    protected function setUp(): void
    {
        parent::setUp();

        $config = $this->getConfig();
        $this->storage = new Storage(true);
        $this->auth = new Auth($this->storage, $config['encryption_key']);
        $this->authContext = new AuthContext();
        $this->authContext->setServices($this->auth, $this->storage);
    }

    protected function tearDown(): void
    {
        $dataFile = $this->storage->getDataFilePath();
        if (file_exists($dataFile)) {
            unlink($dataFile);
        }

        parent::tearDown();
    }

    // ========================================================================
    // GET AUTHENTICATED WITH
    // ========================================================================

    public function test_get_authenticated_with_returns_token_for_token_auth(): void
    {
        $username = 'testuser';
        $password = 'testpass';
        $token = $this->auth->generateToken($username, $password);

        $this->authContext->setTokenAuth($token, $username, $password, 'SESSION123');

        $result = $this->authContext->getAuthenticatedWith();

        $this->assertEquals('token', $result);
    }

    public function test_get_authenticated_with_returns_credentials_for_credentials_auth(): void
    {
        $this->authContext->setCredentialsAuth('testuser', 'testpass', 'SESSION123');

        $result = $this->authContext->getAuthenticatedWith();

        $this->assertEquals('credentials', $result);
    }

    public function test_get_authenticated_with_returns_null_when_not_authenticated(): void
    {
        $result = $this->authContext->getAuthenticatedWith();

        $this->assertNull($result);
    }

    // ========================================================================
    // STATE MANAGEMENT
    // ========================================================================

    public function test_is_authenticated_returns_false_initially(): void
    {
        $this->assertFalse($this->authContext->isAuthenticated());
    }

    public function test_set_token_auth_sets_authenticated_state(): void
    {
        $token = $this->auth->generateToken('user', 'pass');
        $this->authContext->setTokenAuth($token, 'user', 'pass', 'SESSION123');

        $this->assertTrue($this->authContext->isAuthenticated());
        $this->assertTrue($this->authContext->isTokenAuth());
        $this->assertFalse($this->authContext->isCredentialsAuth());
    }

    public function test_set_credentials_auth_sets_authenticated_state(): void
    {
        $this->authContext->setCredentialsAuth('user', 'pass', 'SESSION123');

        $this->assertTrue($this->authContext->isAuthenticated());
        $this->assertFalse($this->authContext->isTokenAuth());
        $this->assertTrue($this->authContext->isCredentialsAuth());
    }

    public function test_get_username_returns_correct_value(): void
    {
        $this->authContext->setCredentialsAuth('testuser', 'testpass', 'SESSION123');

        $this->assertEquals('testuser', $this->authContext->getUsername());
    }

    public function test_get_password_returns_correct_value(): void
    {
        $this->authContext->setCredentialsAuth('testuser', 'testpass', 'SESSION123');

        $this->assertEquals('testpass', $this->authContext->getPassword());
    }

    public function test_get_token_returns_null_for_credentials_auth(): void
    {
        $this->authContext->setCredentialsAuth('testuser', 'testpass', 'SESSION123');

        $this->assertNull($this->authContext->getToken());
    }

    public function test_get_jsessionid_returns_correct_value(): void
    {
        $this->authContext->setCredentialsAuth('testuser', 'testpass', 'SESSION123');

        $this->assertEquals('SESSION123', $this->authContext->getJSessionId());
    }

    // ========================================================================
    // GET OR GENERATE TOKEN
    // ========================================================================

    public function test_get_or_generate_token_returns_existing_token_for_token_auth(): void
    {
        $username = 'testuser';
        $password = 'testpass';
        $token = $this->auth->generateToken($username, $password);

        $this->authContext->setTokenAuth($token, $username, $password, 'SESSION123');

        $result = $this->authContext->getOrGenerateToken();

        $this->assertNotEmpty($result);
        $this->assertEquals($token, $result);
    }

    public function test_get_or_generate_token_generates_new_token_for_credentials_auth(): void
    {
        $username = 'testuser';
        $password = 'testpass';

        $this->authContext->setCredentialsAuth($username, $password, 'SESSION123');

        $token = $this->authContext->getOrGenerateToken();

        $this->assertNotEmpty($token);
        // Verify it's a valid token format
        $parts = explode(':', $token);
        $this->assertCount(4, $parts);
    }

    public function test_get_or_generate_token_returns_null_when_not_authenticated(): void
    {
        $result = $this->authContext->getOrGenerateToken();

        $this->assertNull($result);
    }

    public function test_set_services_allows_late_binding(): void
    {
        $newContext = new AuthContext();

        $this->assertFalse($newContext->isAuthenticated());

        $newContext->setServices($this->auth, $this->storage);
        $newContext->setCredentialsAuth('user', 'pass', 'SESSION');

        $token = $newContext->getOrGenerateToken();

        $this->assertNotEmpty($token);
    }
}
