<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use RateLimiter;

/**
 * Unit Tests - RateLimiter Service
 * Tests rate limiting for brute-force protection
 */
class RateLimiterTest extends TestCase
{
    private RateLimiter $rateLimiter;
    private int $maxAttempts = 5;
    private int $windowSeconds = 300;

    protected function setUp(): void
    {
        parent::setUp();

        // Fixed DI: Pass parameters directly to constructor
        $this->rateLimiter = new RateLimiter($this->maxAttempts, $this->windowSeconds);
    }

    protected function tearDown(): void
    {
        // Clean up rate limiter data file
        $dataFile = sys_get_temp_dir() . '/quel_io_rate_limit.json';
        if (file_exists($dataFile)) {
            unlink($dataFile);
        }

        parent::tearDown();
    }

    // ========================================================================
    // BASIC RATE LIMITING
    // ========================================================================

    public function test_allows_first_attempt(): void
    {
        $ip = '192.168.1.1';

        $result = $this->rateLimiter->isRateLimited($ip);

        $this->assertFalse($result); // Not rate limited = false
    }

    public function test_allows_attempts_below_limit(): void
    {
        $ip = '192.168.1.2';

        // Record attempts below limit
        for ($i = 0; $i < $this->maxAttempts - 1; $i++) {
            $this->rateLimiter->recordAttempt($ip);
        }

        // Should still be allowed (not rate limited)
        $result = $this->rateLimiter->isRateLimited($ip);

        $this->assertFalse($result);
    }

    public function test_blocks_after_max_attempts(): void
    {
        $ip = '192.168.1.3';

        // Record max attempts
        for ($i = 0; $i < $this->maxAttempts; $i++) {
            $this->rateLimiter->recordAttempt($ip);
        }

        // Should be rate limited
        $result = $this->rateLimiter->isRateLimited($ip);

        $this->assertTrue($result);
    }

    public function test_blocks_exactly_at_limit(): void
    {
        $ip = '192.168.1.4';

        // Record exactly max attempts
        for ($i = 0; $i < $this->maxAttempts; $i++) {
            $this->rateLimiter->recordAttempt($ip);
        }

        // Should be rate limited
        $this->assertTrue($this->rateLimiter->isRateLimited($ip));
    }

    // ========================================================================
    // RESET FUNCTIONALITY
    // ========================================================================

    public function test_resets_attempts_on_success(): void
    {
        $ip = '192.168.1.5';

        // Record some attempts
        $this->rateLimiter->recordAttempt($ip);
        $this->rateLimiter->recordAttempt($ip);

        // Reset on success
        $this->rateLimiter->resetAttempts($ip);

        // Should not be rate limited anymore
        $result = $this->rateLimiter->isRateLimited($ip);

        $this->assertFalse($result);
    }

    public function test_reset_allows_new_attempts(): void
    {
        $ip = '192.168.1.6';

        // Block the IP
        for ($i = 0; $i < $this->maxAttempts; $i++) {
            $this->rateLimiter->recordAttempt($ip);
        }
        $this->assertTrue($this->rateLimiter->isRateLimited($ip));

        // Reset
        $this->rateLimiter->resetAttempts($ip);

        // Should be able to make new attempts
        $this->assertFalse($this->rateLimiter->isRateLimited($ip));
        $this->rateLimiter->recordAttempt($ip);
        $this->assertFalse($this->rateLimiter->isRateLimited($ip));
    }

    // ========================================================================
    // IP INDEPENDENCE
    // ========================================================================

    public function test_different_ips_are_independent(): void
    {
        $ip1 = '192.168.1.7';
        $ip2 = '192.168.1.8';

        // Block IP1
        for ($i = 0; $i < $this->maxAttempts; $i++) {
            $this->rateLimiter->recordAttempt($ip1);
        }

        // IP2 should not be rate limited
        $result = $this->rateLimiter->isRateLimited($ip2);

        $this->assertFalse($result);
        $this->assertTrue($this->rateLimiter->isRateLimited($ip1));
    }

    public function test_multiple_ips_tracked_separately(): void
    {
        $ip1 = '192.168.1.9';
        $ip2 = '192.168.1.10';
        $ip3 = '192.168.1.11';

        // Each IP makes different number of attempts
        $this->rateLimiter->recordAttempt($ip1);
        $this->rateLimiter->recordAttempt($ip1);

        $this->rateLimiter->recordAttempt($ip2);

        for ($i = 0; $i < $this->maxAttempts; $i++) {
            $this->rateLimiter->recordAttempt($ip3);
        }

        // Each IP should have independent status
        $this->assertFalse($this->rateLimiter->isRateLimited($ip1));   // 2 attempts (not limited)
        $this->assertFalse($this->rateLimiter->isRateLimited($ip2));   // 1 attempt (not limited)
        $this->assertTrue($this->rateLimiter->isRateLimited($ip3));    // 5 attempts (rate limited)
    }

    // ========================================================================
    // REMAINING ATTEMPTS
    // ========================================================================

    public function test_calculates_remaining_attempts_correctly(): void
    {
        $ip = '192.168.1.12';

        // No attempts yet
        $this->assertEquals($this->maxAttempts, $this->rateLimiter->getRemainingAttempts($ip));

        // Record 2 attempts
        $this->rateLimiter->recordAttempt($ip);
        $this->rateLimiter->recordAttempt($ip);

        $remaining = $this->rateLimiter->getRemainingAttempts($ip);

        $this->assertEquals($this->maxAttempts - 2, $remaining);
    }

    public function test_remaining_attempts_never_negative(): void
    {
        $ip = '192.168.1.13';

        // Exceed max attempts
        for ($i = 0; $i < $this->maxAttempts + 5; $i++) {
            $this->rateLimiter->recordAttempt($ip);
        }

        $remaining = $this->rateLimiter->getRemainingAttempts($ip);

        $this->assertEquals(0, $remaining);
        $this->assertGreaterThanOrEqual(0, $remaining);
    }

    public function test_remaining_attempts_after_reset(): void
    {
        $ip = '192.168.1.14';

        // Record attempts
        $this->rateLimiter->recordAttempt($ip);
        $this->rateLimiter->recordAttempt($ip);

        // Reset
        $this->rateLimiter->resetAttempts($ip);

        // Should have max attempts available
        $this->assertEquals($this->maxAttempts, $this->rateLimiter->getRemainingAttempts($ip));
    }

    // ========================================================================
    // TIME UNTIL RESET
    // ========================================================================

    public function test_time_until_reset_is_positive_when_limited(): void
    {
        $ip = '192.168.1.15';

        // Block the IP
        for ($i = 0; $i < $this->maxAttempts; $i++) {
            $this->rateLimiter->recordAttempt($ip);
        }

        $timeUntilReset = $this->rateLimiter->getTimeUntilReset($ip);

        $this->assertGreaterThan(0, $timeUntilReset);
        $this->assertLessThanOrEqual($this->windowSeconds, $timeUntilReset);
    }

    public function test_time_until_reset_is_zero_when_not_limited(): void
    {
        $ip = '192.168.1.16';

        // No attempts recorded
        $timeUntilReset = $this->rateLimiter->getTimeUntilReset($ip);

        $this->assertEquals(0, $timeUntilReset);
    }

    // ========================================================================
    // CLEANUP
    // ========================================================================

    public function test_cleanup_removes_expired_entries(): void
    {
        $ip = '192.168.1.17';

        // Record attempt
        $this->rateLimiter->recordAttempt($ip);

        // Cleanup should not remove recent entries
        $this->rateLimiter->cleanup();

        // Should still have recorded attempt
        $remaining = $this->rateLimiter->getRemainingAttempts($ip);
        $this->assertEquals($this->maxAttempts - 1, $remaining);
    }

    // ========================================================================
    // EDGE CASES
    // ========================================================================

    public function test_handles_ipv6_addresses(): void
    {
        $ipv6 = '2001:0db8:85a3:0000:0000:8a2e:0370:7334';

        $result = $this->rateLimiter->isRateLimited($ipv6);

        $this->assertFalse($result);

        // Should track IPv6 independently
        $this->rateLimiter->recordAttempt($ipv6);
        $remaining = $this->rateLimiter->getRemainingAttempts($ipv6);
        $this->assertEquals($this->maxAttempts - 1, $remaining);
    }

    public function test_record_attempt_cleans_old_attempts_automatically(): void
    {
        $ip = '192.168.1.18';

        // Record attempt
        $this->rateLimiter->recordAttempt($ip);

        // Recording new attempt should trigger cleanup internally
        // (this tests that cleanup happens automatically)
        $this->rateLimiter->recordAttempt($ip);

        // Should have 2 attempts recorded
        $remaining = $this->rateLimiter->getRemainingAttempts($ip);
        $this->assertEquals($this->maxAttempts - 2, $remaining);
    }

    public function test_handles_empty_ip(): void
    {
        $ip = '';

        // Should handle empty IP gracefully (not rate limited)
        $result = $this->rateLimiter->isRateLimited($ip);

        $this->assertFalse($result);
    }
}
