<?php

/**
 * Rate Limiter Service
 * Prevents brute-force attacks by limiting login attempts per IP address
 */
class RateLimiter
{
    private string $dataFile;
    private int $maxAttempts;
    private int $windowSeconds;

    public function __construct(int $maxAttempts, int $windowSeconds)
    {
        $this->maxAttempts = $maxAttempts;
        $this->windowSeconds = $windowSeconds;

        // Store rate limit data in temp directory
        $this->dataFile = sys_get_temp_dir() . '/quel_io_rate_limit.json';
    }

    /**
     * Check if the given IP is rate limited
     * @param string $ip IP address to check
     * @return bool True if rate limited, false otherwise
     */
    public function isRateLimited(string $ip): bool
    {
        $attempts = $this->getAttempts($ip);
        $currentTime = time();

        // Filter attempts within the time window
        $recentAttempts = array_filter($attempts, function ($timestamp) use ($currentTime) {
            return ($currentTime - $timestamp) < $this->windowSeconds;
        });

        return count($recentAttempts) >= $this->maxAttempts;
    }

    /**
     * Record a login attempt for the given IP
     * @param string $ip IP address
     */
    public function recordAttempt(string $ip): void
    {
        $attempts = $this->getAttempts($ip);
        $attempts[] = time();

        // Clean old attempts (outside time window)
        $currentTime = time();
        $attempts = array_filter($attempts, function ($timestamp) use ($currentTime) {
            return ($currentTime - $timestamp) < $this->windowSeconds;
        });

        $this->saveAttempts($ip, array_values($attempts));
    }

    /**
     * Reset attempts for a specific IP (called on successful login)
     * @param string $ip IP address
     */
    public function resetAttempts(string $ip): void
    {
        $this->saveAttempts($ip, []);
    }

    /**
     * Get remaining attempts for an IP
     * @param string $ip IP address
     * @return int Number of remaining attempts
     */
    public function getRemainingAttempts(string $ip): int
    {
        $attempts = $this->getAttempts($ip);
        $currentTime = time();

        $recentAttempts = array_filter($attempts, function ($timestamp) use ($currentTime) {
            return ($currentTime - $timestamp) < $this->windowSeconds;
        });

        $remaining = $this->maxAttempts - count($recentAttempts);
        return max(0, $remaining);
    }

    /**
     * Get time until rate limit expires
     * @param string $ip IP address
     * @return int Seconds until rate limit expires, 0 if not rate limited
     */
    public function getTimeUntilReset(string $ip): int
    {
        $attempts = $this->getAttempts($ip);
        if (empty($attempts)) {
            return 0;
        }

        $currentTime = time();
        $recentAttempts = array_filter($attempts, function ($timestamp) use ($currentTime) {
            return ($currentTime - $timestamp) < $this->windowSeconds;
        });

        if (count($recentAttempts) < $this->maxAttempts) {
            return 0;
        }

        // Find the oldest attempt in the window
        $oldestAttempt = min($recentAttempts);
        $resetTime = $oldestAttempt + $this->windowSeconds;

        return max(0, $resetTime - $currentTime);
    }

    /**
     * Get attempts for a specific IP
     * @param string $ip IP address
     * @return array List of timestamps
     */
    private function getAttempts(string $ip): array
    {
        $allData = $this->loadData();
        return $allData[$ip] ?? [];
    }

    /**
     * Save attempts for a specific IP
     * @param string $ip IP address
     * @param array $attempts List of timestamps
     */
    private function saveAttempts(string $ip, array $attempts): void
    {
        $allData = $this->loadData();

        if (empty($attempts)) {
            unset($allData[$ip]);
        } else {
            $allData[$ip] = $attempts;
        }

        $this->saveData($allData);
    }

    /**
     * Load all rate limit data
     * @return array
     */
    private function loadData(): array
    {
        if (!file_exists($this->dataFile)) {
            return [];
        }

        $content = file_get_contents($this->dataFile);
        if ($content === false) {
            return [];
        }

        $data = json_decode($content, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Save all rate limit data
     * @param array $data
     */
    private function saveData(array $data): void
    {
        try {
            $dir = dirname($this->dataFile);
            if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
                error_log("Failed to create rate limiter directory: $dir");
                return;
            }

            $jsonData = json_encode($data);
            if ($jsonData === false) {
                error_log("Failed to encode rate limiter data");
                return;
            }

            file_put_contents($this->dataFile, $jsonData, LOCK_EX);
        } catch (\Throwable $e) {
            error_log("Rate limiter save error: " . $e->getMessage());
        }
    }

    /**
     * Clean up old entries (can be called periodically)
     */
    public function cleanup(): void
    {
        $allData = $this->loadData();
        $currentTime = time();
        $cleaned = false;

        foreach ($allData as $ip => $attempts) {
            $recentAttempts = array_filter($attempts, function ($timestamp) use ($currentTime) {
                return ($currentTime - $timestamp) < $this->windowSeconds;
            });

            if (empty($recentAttempts)) {
                unset($allData[$ip]);
                $cleaned = true;
            } elseif (count($recentAttempts) < count($attempts)) {
                $allData[$ip] = array_values($recentAttempts);
                $cleaned = true;
            }
        }

        if ($cleaned) {
            $this->saveData($allData);
        }
    }
}
