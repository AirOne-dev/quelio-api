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
}
