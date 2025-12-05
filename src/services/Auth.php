<?php

class Auth
{
    public function __construct(
        private Storage $storage,
        private string $encryptionKey
    ) {
    }

    /**
     * Generate a session token for a user
     * Token format: base64(username):base64(encrypted_password):timestamp:signature
     *
     * @param string $username
     * @param string $password
     * @return string
     */
    public function generateToken(string $username, string $password): string
    {
        $timestamp = time();
        $encodedUsername = base64_encode($username);
        $encryptedPassword = base64_encode($this->encryptPassword($password));
        $signature = hash('sha256', $username . ':' . $password . ':' . $timestamp);

        return $encodedUsername . ':' . $encryptedPassword . ':' . $timestamp . ':' . $signature;
    }

    /**
     * Extract username from token
     * @param string $token
     * @return string|null Username or null if invalid
     */
    public function getUsernameFromToken(string $token): ?string
    {
        if (empty($token)) {
            return null;
        }

        $parts = explode(':', $token);
        if (count($parts) !== 4) {
            return null;
        }

        try {
            $username = base64_decode($parts[0], true);
            if ($username === false) {
                return null;
            }
            return $username;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Extract password from token
     * @param string $token
     * @return string|null Password or null if invalid
     */
    public function getPasswordFromToken(string $token): ?string
    {
        if (empty($token)) {
            return null;
        }

        $parts = explode(':', $token);
        if (count($parts) !== 4) {
            return null;
        }

        try {
            $encryptedPassword = base64_decode($parts[1], true);
            if ($encryptedPassword === false) {
                return null;
            }
            return $this->decryptPassword($encryptedPassword);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Encrypt password for token storage
     */
    private function encryptPassword(string $password): string
    {
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($password, 'AES-256-CBC', $this->encryptionKey, 0, $iv);
        return base64_encode($iv) . '::' . $encrypted;
    }

    /**
     * Decrypt password from token
     */
    private function decryptPassword(string $encrypted): ?string
    {
        $parts = explode('::', $encrypted);
        if (count($parts) !== 2) {
            return null;
        }

        $iv = base64_decode($parts[0]);
        $data = $parts[1];

        $decrypted = openssl_decrypt($data, 'AES-256-CBC', $this->encryptionKey, 0, $iv);
        return $decrypted !== false ? $decrypted : null;
    }

    /**
     * Validate a session token
     * @param string $token
     * @return bool
     */
    public function validateToken(string $token): bool
    {
        if (empty($token)) {
            return false;
        }

        try {
            $username = $this->getUsernameFromToken($token);
            if ($username === null) {
                return false;
            }

            $userData = $this->storage->getUserData($username);

            if ($userData === null || !isset($userData['session_token'])) {
                return false;
            }

            $savedToken = $userData['session_token'];

            // Check if token matches
            if ($savedToken !== $token) {
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            error_log("Token validation error: " . $e->getMessage());
            return false;
        }
    }

}

