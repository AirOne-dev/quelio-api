<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use Auth;
use Storage;

/**
 * Unit Tests - Auth Service
 * Tests token generation, validation, and password encryption
 */
class AuthTest extends TestCase
{
    private Auth $auth;
    private Storage $storage;
    private string $testDataFile;

    protected function setUp(): void
    {
        parent::setUp();

        // Create temp file for storage
        $this->testDataFile = sys_get_temp_dir() . '/test_auth_' . uniqid() . '.json';

        // Create storage with debug mode
        $this->storage = new Storage(true);

        // Create auth service
        $config = $this->getConfig();
        $this->auth = new Auth($this->storage, $config['encryption_key']);
    }

    protected function tearDown(): void
    {
        // Clean up test file
        if (file_exists($this->testDataFile)) {
            unlink($this->testDataFile);
        }
        parent::tearDown();
    }

    // ========================================================================
    // TOKEN GENERATION
    // ========================================================================

    public function test_generates_valid_token(): void
    {
        $token = $this->auth->generateToken('testuser', 'testpass');

        $this->assertNotEmpty($token);
        $this->assertIsString($token);

        // Token should have 4 parts: username:password:timestamp:signature
        $parts = explode(':', $token);
        $this->assertCount(4, $parts);
    }

    public function test_token_contains_encoded_username(): void
    {
        $username = 'testuser';
        $token = $this->auth->generateToken($username, 'testpass');

        $parts = explode(':', $token);
        $encodedUsername = $parts[0];

        $this->assertEquals($username, base64_decode($encodedUsername));
    }

    public function test_token_contains_encrypted_password(): void
    {
        $password = 'testpass';
        $token = $this->auth->generateToken('testuser', $password);

        $parts = explode(':', $token);
        $encryptedPassword = $parts[1];

        // Should be base64 encoded
        $this->assertNotEmpty($encryptedPassword);
        $decoded = base64_decode($encryptedPassword, true);
        $this->assertNotFalse($decoded);

        // Should not be plain password
        $this->assertNotEquals($password, $decoded);
    }

    public function test_token_contains_timestamp(): void
    {
        $beforeTime = time();
        $token = $this->auth->generateToken('testuser', 'testpass');
        $afterTime = time();

        $parts = explode(':', $token);
        $timestamp = (int) $parts[2];

        $this->assertGreaterThanOrEqual($beforeTime, $timestamp);
        $this->assertLessThanOrEqual($afterTime, $timestamp);
    }

    public function test_token_contains_signature(): void
    {
        $token = $this->auth->generateToken('testuser', 'testpass');

        $parts = explode(':', $token);
        $signature = $parts[3];

        $this->assertNotEmpty($signature);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $signature); // SHA-256
    }

    public function test_different_credentials_produce_different_tokens(): void
    {
        $token1 = $this->auth->generateToken('user1', 'pass1');
        $token2 = $this->auth->generateToken('user2', 'pass2');

        $this->assertNotEquals($token1, $token2);
    }

    // ========================================================================
    // USERNAME EXTRACTION
    // ========================================================================

    public function test_extracts_username_from_valid_token(): void
    {
        $username = 'testuser';
        $token = $this->auth->generateToken($username, 'testpass');

        $extracted = $this->auth->getUsernameFromToken($token);

        $this->assertEquals($username, $extracted);
    }

    public function test_returns_null_for_invalid_token_format(): void
    {
        $result = $this->auth->getUsernameFromToken('invalid_token');

        $this->assertNull($result);
    }

    public function test_returns_null_for_empty_token(): void
    {
        $result = $this->auth->getUsernameFromToken('');

        $this->assertNull($result);
    }

    public function test_returns_null_for_malformed_token(): void
    {
        $result = $this->auth->getUsernameFromToken('part1:part2'); // Missing parts

        $this->assertNull($result);
    }

    // ========================================================================
    // PASSWORD EXTRACTION & DECRYPTION
    // ========================================================================

    public function test_extracts_and_decrypts_password_from_token(): void
    {
        $password = 'testpass';
        $token = $this->auth->generateToken('testuser', $password);

        $extracted = $this->auth->getPasswordFromToken($token);

        $this->assertEquals($password, $extracted);
    }

    public function test_handles_special_characters_in_password(): void
    {
        $password = 'p@ssw0rd!#$%^&*()';
        $token = $this->auth->generateToken('testuser', $password);

        $extracted = $this->auth->getPasswordFromToken($token);

        $this->assertEquals($password, $extracted);
    }

    public function test_password_extraction_returns_null_for_invalid_token(): void
    {
        $result = $this->auth->getPasswordFromToken('invalid_token');

        $this->assertNull($result);
    }

    // ========================================================================
    // TOKEN VALIDATION
    // ========================================================================

    public function test_validates_token_when_stored_in_storage(): void
    {
        $username = 'testuser';
        $token = $this->auth->generateToken($username, 'testpass');

        // Save token to storage
        $this->storage->saveUserData($username, [], '00:00', '00:00', $token);

        $isValid = $this->auth->validateToken($token);

        $this->assertTrue($isValid);
    }

    public function test_rejects_token_not_in_storage(): void
    {
        $token = $this->auth->generateToken('testuser', 'testpass');

        // Don't save to storage
        $isValid = $this->auth->validateToken($token);

        $this->assertFalse($isValid);
    }

    public function test_rejects_empty_token(): void
    {
        $isValid = $this->auth->validateToken('');

        $this->assertFalse($isValid);
    }

    public function test_rejects_mismatched_token(): void
    {
        $username = 'testuser';
        $token1 = $this->auth->generateToken($username, 'testpass');
        $token2 = $this->auth->generateToken($username, 'different');

        // Save token1 to storage
        $this->storage->saveUserData($username, [], '00:00', '00:00', $token1);

        // Try to validate token2
        $isValid = $this->auth->validateToken($token2);

        $this->assertFalse($isValid);
    }

    // ========================================================================
    // ENCRYPTION/DECRYPTION
    // ========================================================================

    public function test_password_encryption_is_different_each_time(): void
    {
        // Generate two tokens with same credentials
        $token1 = $this->auth->generateToken('testuser', 'testpass');
        sleep(1); // Ensure different timestamp
        $token2 = $this->auth->generateToken('testuser', 'testpass');

        // Tokens should be different (different IV and timestamp)
        $this->assertNotEquals($token1, $token2);

        // But both should decrypt to same password
        $pass1 = $this->auth->getPasswordFromToken($token1);
        $pass2 = $this->auth->getPasswordFromToken($token2);
        $this->assertEquals($pass1, $pass2);
    }

    public function test_long_password_encryption(): void
    {
        $longPassword = str_repeat('a', 100);
        $token = $this->auth->generateToken('testuser', $longPassword);

        $extracted = $this->auth->getPasswordFromToken($token);

        $this->assertEquals($longPassword, $extracted);
    }

    public function test_unicode_password_encryption(): void
    {
        $unicodePassword = 'pässwörd_日本語_🔒';
        $token = $this->auth->generateToken('testuser', $unicodePassword);

        $extracted = $this->auth->getPasswordFromToken($token);

        $this->assertEquals($unicodePassword, $extracted);
    }

    // ========================================================================
    // EDGE CASES
    // ========================================================================

    public function test_handles_username_with_colon(): void
    {
        // Colons in username should not break token parsing
        $username = 'user:name';
        $token = $this->auth->generateToken($username, 'testpass');

        $extracted = $this->auth->getUsernameFromToken($token);

        // Should extract the base64 encoded version correctly
        $this->assertNotNull($extracted);
    }

    public function test_token_validation_handles_storage_errors_gracefully(): void
    {
        // Create auth with non-existent user
        $token = $this->auth->generateToken('nonexistent', 'testpass');

        $isValid = $this->auth->validateToken($token);

        // Should return false without throwing exception
        $this->assertFalse($isValid);
    }
}
