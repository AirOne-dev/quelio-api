<?php

namespace Tests\Unit\Http;

use Tests\TestCase;
use AuthContext;

class AuthContextTest extends TestCase
{
    private array $userData;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userData = [
            '2024-01-15' => ['08:00', '12:00', '13:00', '17:00'],
            '2024-01-16' => ['09:00', '18:00']
        ];
        $this->token = 'test_token_123';
    }

    public function testConstructorSetsProperties(): void
    {
        $context = new AuthContext('testuser', 'testpass', $this->userData, $this->token);

        $this->assertEquals('testuser', $context->getUsername());
        $this->assertEquals('testpass', $context->getPassword());
        $this->assertEquals($this->userData, $context->getUserData());
        $this->assertEquals($this->token, $context->getToken());
    }

    public function testGetUsername(): void
    {
        $context = new AuthContext('john_doe', 'pass', [], '');

        $this->assertEquals('john_doe', $context->getUsername());
    }

    public function testGetPassword(): void
    {
        $context = new AuthContext('user', 'secret123', [], '');

        $this->assertEquals('secret123', $context->getPassword());
    }

    public function testGetUserData(): void
    {
        $context = new AuthContext('user', 'pass', $this->userData, '');

        $this->assertEquals($this->userData, $context->getUserData());
    }

    public function testGetUserDataReturnsEmptyArray(): void
    {
        $context = new AuthContext('user', 'pass', [], '');

        $this->assertIsArray($context->getUserData());
        $this->assertEmpty($context->getUserData());
    }

    public function testGetToken(): void
    {
        $context = new AuthContext('user', 'pass', [], $this->token);

        $this->assertEquals($this->token, $context->getToken());
    }

    public function testGetTokenReturnsEmptyString(): void
    {
        $context = new AuthContext('user', 'pass', [], '');

        $this->assertEquals('', $context->getToken());
    }

    public function testIsFromCache(): void
    {
        $context = new AuthContext('user', 'pass', $this->userData, $this->token);
        $context->setFromCache(true);

        $this->assertTrue($context->isFromCache());
    }

    public function testIsFromCacheDefaultsFalse(): void
    {
        $context = new AuthContext('user', 'pass', [], '');

        $this->assertFalse($context->isFromCache());
    }

    public function testSetFromCache(): void
    {
        $context = new AuthContext('user', 'pass', [], '');

        $this->assertFalse($context->isFromCache());

        $context->setFromCache(true);
        $this->assertTrue($context->isFromCache());

        $context->setFromCache(false);
        $this->assertFalse($context->isFromCache());
    }

    public function testWasAuthenticatedWithCredentials(): void
    {
        $context = new AuthContext('user', 'pass', [], '');
        $context->setAuthenticatedWithCredentials(true);

        $this->assertTrue($context->wasAuthenticatedWithCredentials());
    }

    public function testWasAuthenticatedWithCredentialsDefaultsFalse(): void
    {
        $context = new AuthContext('user', 'pass', [], '');

        $this->assertFalse($context->wasAuthenticatedWithCredentials());
    }

    public function testSetAuthenticatedWithCredentials(): void
    {
        $context = new AuthContext('user', 'pass', [], '');

        $this->assertFalse($context->wasAuthenticatedWithCredentials());

        $context->setAuthenticatedWithCredentials(true);
        $this->assertTrue($context->wasAuthenticatedWithCredentials());

        $context->setAuthenticatedWithCredentials(false);
        $this->assertFalse($context->wasAuthenticatedWithCredentials());
    }

    public function testContextWithCompleteData(): void
    {
        $context = new AuthContext('admin', 'admin_pass', $this->userData, 'admin_token');
        $context->setFromCache(true);
        $context->setAuthenticatedWithCredentials(false);

        $this->assertEquals('admin', $context->getUsername());
        $this->assertEquals('admin_pass', $context->getPassword());
        $this->assertEquals($this->userData, $context->getUserData());
        $this->assertEquals('admin_token', $context->getToken());
        $this->assertTrue($context->isFromCache());
        $this->assertFalse($context->wasAuthenticatedWithCredentials());
    }

    public function testContextWithMinimalData(): void
    {
        $context = new AuthContext('', '', [], '');

        $this->assertEquals('', $context->getUsername());
        $this->assertEquals('', $context->getPassword());
        $this->assertEquals([], $context->getUserData());
        $this->assertEquals('', $context->getToken());
        $this->assertFalse($context->isFromCache());
        $this->assertFalse($context->wasAuthenticatedWithCredentials());
    }
}
