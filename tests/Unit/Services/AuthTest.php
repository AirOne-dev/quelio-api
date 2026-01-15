<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use Auth;

class AuthTest extends TestCase
{
    private Auth $auth;

    protected function setUp(): void
    {
        parent::setUp();
        $this->auth = new Auth($this->getConfig());
    }

    public function test_generates_valid_token(): void
    {
        $token = $this->auth->generateToken('testuser', 'testpass');

        $this->assertNotEmpty($token);
        $this->assertIsString($token);
        $this->assertStringContainsString(':', $token);
    }

    public function test_validates_correct_token(): void
    {
        $token = $this->auth->generateToken('testuser', 'testpass');

        $result = $this->auth->validateToken($token, [
            'users' => [
                'testuser' => [
                    'session_token' => $token
                ]
            ]
        ]);

        $this->assertTrue($result);
    }

    public function test_rejects_invalid_token(): void
    {
        $result = $this->auth->validateToken('invalid_token', [
            'users' => [
                'testuser' => [
                    'session_token' => 'different_token'
                ]
            ]
        ]);

        $this->assertFalse($result);
    }

    public function test_extracts_username_from_token(): void
    {
        $token = $this->auth->generateToken('testuser', 'testpass');
        $username = $this->auth->extractUsernameFromToken($token);

        $this->assertEquals('testuser', $username);
    }

    public function test_extracts_password_from_token(): void
    {
        $password = 'test_password_123';
        $token = $this->auth->generateToken('testuser', $password);
        $extractedPassword = $this->auth->extractPasswordFromToken($token);

        $this->assertEquals($password, $extractedPassword);
    }

    public function test_token_contains_timestamp(): void
    {
        $token = $this->auth->generateToken('testuser', 'testpass');
        $parts = explode(':', $token);

        $this->assertCount(4, $parts);
        $timestamp = $parts[2];
        $this->assertIsNumeric($timestamp);
        $this->assertGreaterThan(time() - 10, $timestamp);
    }

    public function test_token_contains_signature(): void
    {
        $token = $this->auth->generateToken('testuser', 'testpass');
        $parts = explode(':', $token);

        $this->assertCount(4, $parts);
        $signature = $parts[3];
        $this->assertNotEmpty($signature);
        $this->assertEquals(64, strlen($signature)); // SHA256 = 64 hex chars
    }
}
