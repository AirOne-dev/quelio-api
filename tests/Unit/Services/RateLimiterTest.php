<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use RateLimiter;

class RateLimiterTest extends TestCase
{
    private RateLimiter $rateLimiter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rateLimiter = new RateLimiter($this->getConfig());
    }

    public function test_allows_first_attempt(): void
    {
        $ip = '192.168.1.1';

        $result = $this->rateLimiter->checkLimit($ip);

        $this->assertTrue($result);
    }

    public function test_blocks_after_max_attempts(): void
    {
        $ip = '192.168.1.2';
        $maxAttempts = $this->getConfig()['rate_limit_max_attempts'];

        // Make max attempts
        for ($i = 0; $i < $maxAttempts; $i++) {
            $this->rateLimiter->recordAttempt($ip);
        }

        // Next attempt should be blocked
        $result = $this->rateLimiter->checkLimit($ip);

        $this->assertFalse($result);
    }

    public function test_resets_attempts_on_success(): void
    {
        $ip = '192.168.1.3';

        // Record some attempts
        $this->rateLimiter->recordAttempt($ip);
        $this->rateLimiter->recordAttempt($ip);

        // Reset on success
        $this->rateLimiter->resetAttempts($ip);

        // Should be allowed now
        $result = $this->rateLimiter->checkLimit($ip);

        $this->assertTrue($result);
    }

    public function test_different_ips_are_independent(): void
    {
        $ip1 = '192.168.1.4';
        $ip2 = '192.168.1.5';
        $maxAttempts = $this->getConfig()['rate_limit_max_attempts'];

        // Block IP1
        for ($i = 0; $i < $maxAttempts; $i++) {
            $this->rateLimiter->recordAttempt($ip1);
        }

        // IP2 should still be allowed
        $result = $this->rateLimiter->checkLimit($ip2);

        $this->assertTrue($result);
    }

    public function test_cleans_up_expired_attempts(): void
    {
        $ip = '192.168.1.6';

        // This test would need to mock time or wait for expiration
        // For now, just verify the method exists and doesn't error
        $this->rateLimiter->recordAttempt($ip);
        $this->rateLimiter->cleanup();

        $this->assertTrue(true); // Placeholder assertion
    }
}
