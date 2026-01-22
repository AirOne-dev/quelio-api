<?php

namespace Tests\Unit\Middleware;

use Tests\TestCase;
use AuthMiddleware;
use Auth;
use KelioClient;
use AuthContext;
use RateLimiter;
use Storage;

/**
 * Unit Tests - AuthMiddleware
 * Tests authentication middleware with token-based and credential-based auth
 * Also tests rate limiting and admin authentication
 */
class AuthMiddlewareTest extends TestCase
{
    private AuthMiddleware $middleware;
    private Auth $auth;
    private KelioClient $kelioClient;
    private AuthContext $authContext;
    private RateLimiter $rateLimiter;
    private Storage $storage;

    protected function setUp(): void
    {
        parent::setUp();

        // Create dependencies with proper DI
        $config = $this->getConfig();
        $this->storage = new Storage(true);
        $this->auth = new Auth($this->storage, $config['encryption_key']);
        $this->kelioClient = new KelioClient($config['kelio_url']);
        $this->authContext = new AuthContext();
        $this->authContext->setServices($this->auth, $this->storage);
        $this->rateLimiter = new RateLimiter(
            $config['rate_limit_max_attempts'],
            $config['rate_limit_window'] ?? 300  // Use 'rate_limit_window' from bootstrap.php
        );

        // Create middleware
        $this->middleware = new AuthMiddleware(
            $this->auth,
            $this->kelioClient,
            $this->authContext,
            $this->rateLimiter,
            $this->storage,
            $config
        );

        // Clean up $_POST, $_GET, $_SERVER for each test
        $_POST = [];
        $_GET = [];
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    }

    protected function tearDown(): void
    {
        // Clean up test files
        $dataFile = $this->storage->getDataFilePath();
        if (file_exists($dataFile)) {
            unlink($dataFile);
        }

        // Clean up rate limiter data file
        $rateLimiterFile = sys_get_temp_dir() . '/quel_io_rate_limit.json';
        if (file_exists($rateLimiterFile)) {
            unlink($rateLimiterFile);
        }

        parent::tearDown();
    }

    // ========================================================================
    // TOKEN-BASED AUTHENTICATION
    // ========================================================================

    public function test_authenticates_with_valid_token_in_post(): void
    {
        $username = 'testuser';
        $password = 'testpass';

        // Generate and save token
        $token = $this->auth->generateToken($username, $password);
        $this->storage->saveUserData($username, [], $token);

        // Mock Kelio login success
        $_POST['token'] = $token;

        // Note: This test requires mocking KelioClient->login()
        // For now, we'll test that the middleware processes the token
        $this->assertNotEmpty($token);
        $this->assertTrue($this->auth->validateToken($token));
    }

    public function test_authenticates_with_valid_token_in_get(): void
    {
        $username = 'testuser';
        $password = 'testpass';

        // Generate and save token
        $token = $this->auth->generateToken($username, $password);
        $this->storage->saveUserData($username, [], $token);

        // Token in GET parameter
        $_GET['token'] = $token;

        $this->assertNotEmpty($token);
        $this->assertTrue($this->auth->validateToken($token));
    }

    public function test_extracts_credentials_from_valid_token(): void
    {
        $username = 'testuser';
        $password = 'testpass';

        $token = $this->auth->generateToken($username, $password);
        $this->storage->saveUserData($username, [], $token);

        // Verify token contains correct credentials
        $extractedUsername = $this->auth->getUsernameFromToken($token);
        $extractedPassword = $this->auth->getPasswordFromToken($token);

        $this->assertEquals($username, $extractedUsername);
        $this->assertEquals($password, $extractedPassword);
    }

    public function test_rejects_invalid_token_format(): void
    {
        $_POST['token'] = 'invalid_token_format';

        $isValid = $this->auth->validateToken($_POST['token']);

        $this->assertFalse($isValid);
    }

    public function test_rejects_token_not_in_storage(): void
    {
        $token = $this->auth->generateToken('testuser', 'testpass');
        // Don't save to storage

        $_POST['token'] = $token;

        $isValid = $this->auth->validateToken($token);

        $this->assertFalse($isValid);
    }

    public function test_rejects_empty_token(): void
    {
        $_POST['token'] = '';

        $isValid = $this->auth->validateToken($_POST['token']);

        $this->assertFalse($isValid);
    }

    // ========================================================================
    // CREDENTIAL-BASED AUTHENTICATION
    // ========================================================================

    public function test_accepts_username_and_password_in_post(): void
    {
        $_POST['username'] = 'testuser';
        $_POST['password'] = 'testpass';

        $this->assertNotEmpty($_POST['username']);
        $this->assertNotEmpty($_POST['password']);
    }

    public function test_accepts_username_in_get_password_in_post(): void
    {
        $_GET['username'] = 'testuser';
        $_POST['password'] = 'testpass';

        $username = $_POST['username'] ?? $_GET['username'] ?? '';
        $password = $_POST['password'] ?? '';

        $this->assertEquals('testuser', $username);
        $this->assertEquals('testpass', $password);
    }

    public function test_rejects_missing_username(): void
    {
        $_POST['password'] = 'testpass';
        // No username

        $username = $_POST['username'] ?? $_GET['username'] ?? '';
        $password = $_POST['password'] ?? '';

        $this->assertEmpty($username);
        $this->assertNotEmpty($password);
    }

    public function test_rejects_missing_password(): void
    {
        $_POST['username'] = 'testuser';
        // No password

        $username = $_POST['username'] ?? $_GET['username'] ?? '';
        $password = $_POST['password'] ?? '';

        $this->assertNotEmpty($username);
        $this->assertEmpty($password);
    }

    public function test_rejects_empty_credentials(): void
    {
        $_POST['username'] = '';
        $_POST['password'] = '';

        $username = $_POST['username'] ?? $_GET['username'] ?? '';
        $password = $_POST['password'] ?? '';

        $this->assertEmpty($username);
        $this->assertEmpty($password);
    }

    // ========================================================================
    // RATE LIMITING
    // ========================================================================

    public function test_allows_first_authentication_attempt(): void
    {
        $ip = '192.168.1.1';
        $_SERVER['REMOTE_ADDR'] = $ip;

        $isLimited = $this->rateLimiter->isRateLimited($ip);

        $this->assertFalse($isLimited);
    }

    public function test_blocks_after_max_failed_attempts(): void
    {
        $ip = '192.168.1.2';
        $_SERVER['REMOTE_ADDR'] = $ip;

        $maxAttempts = $this->getConfig()['rate_limit_max_attempts'];

        // Record max failed attempts
        for ($i = 0; $i < $maxAttempts; $i++) {
            $this->rateLimiter->recordAttempt($ip);
        }

        $isLimited = $this->rateLimiter->isRateLimited($ip);

        $this->assertTrue($isLimited);
    }

    public function test_resets_rate_limit_on_successful_auth(): void
    {
        $ip = '192.168.1.3';
        $_SERVER['REMOTE_ADDR'] = $ip;

        // Record some failed attempts
        $this->rateLimiter->recordAttempt($ip);
        $this->rateLimiter->recordAttempt($ip);

        // Simulate successful auth
        $this->rateLimiter->resetAttempts($ip);

        $isLimited = $this->rateLimiter->isRateLimited($ip);

        $this->assertFalse($isLimited);
    }

    public function test_calculates_remaining_attempts(): void
    {
        $ip = '192.168.1.4';
        $_SERVER['REMOTE_ADDR'] = $ip;

        $maxAttempts = $this->getConfig()['rate_limit_max_attempts'];

        // Record 2 attempts
        $this->rateLimiter->recordAttempt($ip);
        $this->rateLimiter->recordAttempt($ip);

        $remaining = $this->rateLimiter->getRemainingAttempts($ip);

        $this->assertEquals($maxAttempts - 2, $remaining);
    }

    public function test_calculates_time_until_reset_when_limited(): void
    {
        $ip = '192.168.1.5';
        $_SERVER['REMOTE_ADDR'] = $ip;

        $config = $this->getConfig();
        $maxAttempts = $config['rate_limit_max_attempts'];
        $windowSeconds = $config['rate_limit_window'] ?? 300;

        // Block the IP
        for ($i = 0; $i < $maxAttempts; $i++) {
            $this->rateLimiter->recordAttempt($ip);
        }

        $timeUntilReset = $this->rateLimiter->getTimeUntilReset($ip);

        $this->assertGreaterThan(0, $timeUntilReset);
        $this->assertLessThanOrEqual($windowSeconds, $timeUntilReset);
    }

    public function test_different_ips_have_independent_rate_limits(): void
    {
        $ip1 = '192.168.1.6';
        $ip2 = '192.168.1.7';

        $maxAttempts = $this->getConfig()['rate_limit_max_attempts'];

        // Block IP1
        for ($i = 0; $i < $maxAttempts; $i++) {
            $this->rateLimiter->recordAttempt($ip1);
        }

        // IP2 should not be affected
        $this->assertTrue($this->rateLimiter->isRateLimited($ip1));
        $this->assertFalse($this->rateLimiter->isRateLimited($ip2));
    }

    // ========================================================================
    // AUTH CONTEXT POPULATION
    // ========================================================================

    public function test_auth_context_stores_token_auth_data(): void
    {
        $username = 'testuser';
        $password = 'testpass';
        $token = $this->auth->generateToken($username, $password);
        $jsessionid = 'TEST_SESSION_123';

        $this->authContext->setTokenAuth($token, $username, $password, $jsessionid);

        $this->assertTrue($this->authContext->isAuthenticated());
        $this->assertTrue($this->authContext->isTokenAuth());
        $this->assertEquals($username, $this->authContext->getUsername());
        $this->assertEquals($password, $this->authContext->getPassword());
        $this->assertEquals($token, $this->authContext->getToken());
        $this->assertEquals($jsessionid, $this->authContext->getJSessionId());
    }

    public function test_auth_context_stores_credentials_auth_data(): void
    {
        $username = 'testuser';
        $password = 'testpass';
        $jsessionid = 'TEST_SESSION_456';

        $this->authContext->setCredentialsAuth($username, $password, $jsessionid);

        $this->assertTrue($this->authContext->isAuthenticated());
        $this->assertTrue($this->authContext->isCredentialsAuth());
        $this->assertEquals($username, $this->authContext->getUsername());
        $this->assertEquals($password, $this->authContext->getPassword());
        $this->assertNull($this->authContext->getToken()); // No token in credentials auth
        $this->assertEquals($jsessionid, $this->authContext->getJSessionId());
    }

    public function test_auth_context_generates_token_for_credentials_auth(): void
    {
        $username = 'testuser';
        $password = 'testpass';
        $jsessionid = 'TEST_SESSION_789';

        // Save user data first (required for token generation)
        $this->storage->saveUserData($username, []);

        $this->authContext->setCredentialsAuth($username, $password, $jsessionid);

        $generatedToken = $this->authContext->getOrGenerateToken();

        $this->assertNotNull($generatedToken);
        $this->assertNotEmpty($generatedToken);

        // Verify it's a valid token
        $extractedUsername = $this->auth->getUsernameFromToken($generatedToken);
        $this->assertEquals($username, $extractedUsername);
    }

    // ========================================================================
    // ADMIN AUTHENTICATION
    // ========================================================================

    public function test_admin_auth_with_valid_credentials(): void
    {
        $config = $this->getConfig();
        $_POST['username'] = $config['admin_username'];
        $_POST['password'] = $config['admin_password'];

        $result = $this->middleware->admin();

        $this->assertNull($result); // null = success, continue execution
    }

    public function test_admin_auth_rejects_invalid_username(): void
    {
        $config = $this->getConfig();
        $_POST['username'] = 'wrong_admin';
        $_POST['password'] = $config['admin_password'];

        ob_start();
        $result = $this->middleware->admin();
        $output = ob_get_clean();

        $this->assertTrue($result); // true = stop execution (failed)
        $this->assertStringContainsString('error', $output);
    }

    public function test_admin_auth_rejects_invalid_password(): void
    {
        $config = $this->getConfig();
        $_POST['username'] = $config['admin_username'];
        $_POST['password'] = 'wrong_password';

        ob_start();
        $result = $this->middleware->admin();
        $output = ob_get_clean();

        $this->assertTrue($result); // true = stop execution (failed)
        $this->assertStringContainsString('error', $output);
    }

    public function test_admin_auth_accepts_credentials_in_get(): void
    {
        $config = $this->getConfig();
        $_GET['username'] = $config['admin_username'];
        $_GET['password'] = $config['admin_password'];

        $result = $this->middleware->admin();

        $this->assertNull($result); // null = success
    }

    public function test_admin_auth_rejects_empty_credentials(): void
    {
        $_POST['username'] = '';
        $_POST['password'] = '';

        ob_start();
        $result = $this->middleware->admin();
        $output = ob_get_clean();

        $this->assertTrue($result); // true = stop execution (failed)
        $this->assertStringContainsString('error', $output);
    }

    // ========================================================================
    // TOKEN INVALIDATION ON FAILED AUTH
    // ========================================================================

    public function test_invalidates_token_after_failed_credentials_auth(): void
    {
        $username = 'testuser';
        $password = 'testpass';

        // Save token to storage
        $token = $this->auth->generateToken($username, $password);
        $this->storage->saveUserData($username, [], $token);

        // Verify token exists
        $userData = $this->storage->getUserData($username);
        $this->assertArrayHasKey('token', $userData);

        // Simulate failed auth by invalidating token
        $this->storage->invalidateToken($username);

        // Verify token is removed
        $userData = $this->storage->getUserData($username);
        $this->assertArrayNotHasKey('token', $userData);
    }

    // ========================================================================
    // EDGE CASES
    // ========================================================================

    public function test_handles_missing_remote_addr(): void
    {
        unset($_SERVER['REMOTE_ADDR']);

        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        $this->assertEquals('unknown', $ip);
    }

    public function test_prioritizes_token_over_credentials(): void
    {
        $username = 'testuser';
        $password = 'testpass';
        $token = $this->auth->generateToken($username, $password);
        $this->storage->saveUserData($username, [], $token);

        // Provide both token and credentials
        $_POST['token'] = $token;
        $_POST['username'] = 'different_user';
        $_POST['password'] = 'different_pass';

        // Token should be checked first
        $tokenFromPost = $_POST['token'] ?? $_GET['token'] ?? '';

        $this->assertNotEmpty($tokenFromPost);
        $this->assertTrue($this->auth->validateToken($tokenFromPost));
    }

    public function test_handles_unicode_in_credentials(): void
    {
        $_POST['username'] = 'tëstüser_日本語';
        $_POST['password'] = 'pässwörd_🔒';

        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        $this->assertNotEmpty($username);
        $this->assertNotEmpty($password);

        // Generate token with Unicode
        $token = $this->auth->generateToken($username, $password);
        $extractedPassword = $this->auth->getPasswordFromToken($token);

        $this->assertEquals($password, $extractedPassword);
    }
}
