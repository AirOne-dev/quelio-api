<?php

/**
 * Authentication Context
 * Stores authentication data for the current request
 * Avoids using $GLOBALS and provides a clean interface
 */
class AuthContext
{
    private ?string $username = null;
    private ?string $password = null;
    private ?string $token = null;
    private ?string $jsessionid = null;
    private ?string $authenticatedWith = null;
    private bool $isAuthenticated = false;
    private ?Auth $auth = null;
    private ?Storage $storage = null;

    /**
     * Set authentication data from token
     * Token auth also validates credentials against Kelio, so we have all the data
     */
    public function setTokenAuth(string $token, string $username, string $password, string $jsessionid): void
    {
        $this->token = $token;
        $this->username = $username;
        $this->password = $password;
        $this->jsessionid = $jsessionid;
        $this->authenticatedWith = 'token';
        $this->isAuthenticated = true;
    }

    /**
     * Set authentication data from credentials (after Kelio validation)
     */
    public function setCredentialsAuth(string $username, string $password, string $jsessionid): void
    {
        $this->username = $username;
        $this->password = $password;
        $this->jsessionid = $jsessionid;
        $this->authenticatedWith = 'credentials';
        $this->isAuthenticated = true;
    }

    /**
     * Check if user is authenticated
     */
    public function isAuthenticated(): bool
    {
        return $this->isAuthenticated;
    }

    /**
     * Get authenticated username
     */
    public function getUsername(): ?string
    {
        return $this->username;
    }

    /**
     * Get password (only available for credential auth)
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    /**
     * Get token (only available for token auth)
     */
    public function getToken(): ?string
    {
        return $this->token;
    }

    /**
     * Get Kelio session ID (only available for credential auth)
     */
    public function getJSessionId(): ?string
    {
        return $this->jsessionid;
    }

    /**
     * Get authentication method: 'token' or 'credentials'
     */
    public function getAuthenticatedWith(): ?string
    {
        return $this->authenticatedWith;
    }

    /**
     * Check if authenticated with token
     */
    public function isTokenAuth(): bool
    {
        return $this->authenticatedWith === 'token';
    }

    /**
     * Check if authenticated with credentials
     */
    public function isCredentialsAuth(): bool
    {
        return $this->authenticatedWith === 'credentials';
    }

    /**
     * Set Auth and Storage services for token management
     */
    public function setServices(Auth $auth, Storage $storage): void
    {
        $this->auth = $auth;
        $this->storage = $storage;
    }

    /**
     * Get or generate session token
     * Returns existing token or generates a new one if authenticated with credentials
     *
     * @return string|null Token or null if not authenticated
     */
    public function getOrGenerateToken(): ?string
    {
        if (!$this->isAuthenticated) {
            return null;
        }

        // If token already exists (token auth), return it
        if ($this->token !== null) {
            return $this->token;
        }

        // Otherwise, get from storage or generate new one (credentials auth)
        if ($this->auth === null || $this->storage === null) {
            throw new \Exception('Auth and Storage services must be set via setServices()');
        }

        $userData = $this->storage->getUserData($this->username);
        $existingToken = $userData['token'] ?? null;

        // If token exists in storage, return it
        if ($existingToken !== null) {
            $this->token = $existingToken;
            return $existingToken;
        }

        // Generate new token
        $newToken = $this->auth->generateToken($this->username, $this->password);
        $this->token = $newToken;

        // Save the new token if user data exists
        if (isset($userData['weeks'])) {
            $this->storage->saveUserData(
                $this->username,
                $userData['weeks'],
                $newToken
            );
        }

        return $newToken;
    }
}
