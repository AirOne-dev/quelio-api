<?php

class AuthMiddleware
{
    public function __construct(
        private Auth $auth,
        private KelioClient $kelioClient,
        private AuthContext $authContext,
        private RateLimiter $rateLimiter,
        private Storage $storage,
        private array $config
    ) {
    }

    /**
     * Middleware to require authentication
     * Accepts either:
     * - Valid token (extracts credentials and validates against Kelio)
     * - Username + password (validates against Kelio)
     *
     * In both cases, Kelio connection is made ONCE to get fresh data
     * Authentication data is stored in AuthContext for controllers to use
     *
     * Can be used as: [$authMiddleware, 'handle']
     */
    public function handle(): ?bool
    {
        // Get client IP address
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        // Check rate limiting
        if ($this->rateLimiter->isRateLimited($ip)) {
            $timeUntilReset = $this->rateLimiter->getTimeUntilReset($ip);
            JsonResponse::error(
                "Too many login attempts. Please try again in " . ceil($timeUntilReset / 60) . " minutes.",
                429,
                [
                    'retry_after' => $timeUntilReset,
                    'retry_after_minutes' => ceil($timeUntilReset / 60)
                ]
            );
            return true; // Stop execution
        }

        $username = null;
        $password = null;

        // Try token authentication first
        $token = $_POST['token'] ?? $_GET['token'] ?? '';

        $isTokenAuth = false;
        $providedToken = '';

        if (!empty($token)) {
            // Validate token format and existence
            if ($this->auth->validateToken($token)) {
                // Token is valid, extract username and password
                $username = $this->auth->getUsernameFromToken($token);
                $password = $this->auth->getPasswordFromToken($token);

                if ($username === null || $password === null) {
                    JsonResponse::unauthorized('Invalid token format');
                    return true; // Stop execution
                }

                $isTokenAuth = true;
                $providedToken = $token;
            } else {
                // Token provided but invalid
                JsonResponse::unauthorized('Invalid or expired token');
                return true; // Stop execution
            }
        } else {
            // Try username/password authentication
            $username = $_POST['username'] ?? $_GET['username'] ?? '';
            $password = $_POST['password'] ?? '';

            if (empty($username) || empty($password)) {
                // No valid authentication method provided
                JsonResponse::unauthorized('Authentication required: provide either a valid token or username/password');
                return true; // Stop execution
            }
        }

        // At this point, we have username and password (either from token or direct input)
        // Validate credentials against Kelio (ONLY ONCE HERE)
        try {
            $jsessionid = $this->kelioClient->login($username, $password);

            // Credentials are valid, reset rate limiter for this IP
            $this->rateLimiter->resetAttempts($ip);

            // Store auth data in AuthContext
            if ($isTokenAuth) {
                $this->authContext->setTokenAuth($providedToken, $username, $password, $jsessionid);
            } else {
                $this->authContext->setCredentialsAuth($username, $password, $jsessionid);
            }

            return null; // Continue execution
        } catch (\Exception $e) {
            // Invalid credentials - record failed attempt
            $this->rateLimiter->recordAttempt($ip);

            // Invalidate token if it exists
            if (!empty($username)) {
                $this->storage->invalidateToken($username);
            }

            $remaining = $this->rateLimiter->getRemainingAttempts($ip);
            $errorMessage = 'Invalid username or password: ' . $e->getMessage();

            if ($remaining > 0) {
                $errorMessage .= " ($remaining attempts remaining)";
            }

            JsonResponse::unauthorized($errorMessage, ['token_invalidated' => true]);
            return true; // Stop execution
        }
    }

    public function admin(): ?bool {
        $username = $_POST['username'] ?? $_GET['username'] ?? '';
        $password = $_POST['password'] ?? $_GET['password'] ?? '';

        if ($username === $this->config['admin_username'] && $password === $this->config['admin_password']) {
            return null;
        }

        JsonResponse::unauthorized('Invalid username or password');
        return true; // Stop execution
    }
}
