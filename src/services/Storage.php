<?php

class Storage
{
    // Storage keys constants
    private const KEY_WEEKS = 'weeks';
    private const KEY_PREFERENCES = 'preferences';
    private const KEY_TOKEN = 'token';

    private string $dataFile;
    private bool $debugMode;

    public function __construct(bool $debugMode = false)
    {
        $this->debugMode = $debugMode;

        // Try multiple possible locations for the data file
        $possibleDataPaths = [
            './data.json',
            __DIR__ . '/../../data.json',
            sys_get_temp_dir() . '/kelio_data.json',
            '/tmp/kelio_data.json'
        ];

        $foundPath = null;
        foreach ($possibleDataPaths as $path) {
            $dir = dirname($path);
            if (is_writable($dir)) {
                $foundPath = $path;
                break;
            }
        }

        // If no writable directory found, use temp directory as fallback
        $this->dataFile = $foundPath ?? sys_get_temp_dir() . '/kelio_data.json';
    }

    /**
     * Get the data file path
     */
    public function getDataFilePath(): string
    {
        return $this->dataFile;
    }

    /**
     * Load all saved data from JSON file with proper locking
     * @return array
     */
    public function loadAllData(): array
    {
        if (!file_exists($this->dataFile)) {
            return [];
        }

        $fp = fopen($this->dataFile, 'r');
        if ($fp === false) {
            error_log("Failed to open file for reading: $this->dataFile");
            return [];
        }

        // Acquire shared lock for reading
        if (!flock($fp, LOCK_SH)) {
            error_log("Failed to acquire shared lock on: $this->dataFile");
            fclose($fp);
            return [];
        }

        $content = stream_get_contents($fp);

        // Release lock
        flock($fp, LOCK_UN);
        fclose($fp);

        if ($content === false) {
            return [];
        }

        $data = json_decode($content, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Get saved data for a specific user
     * @param string $username
     * @return array|null
     */
    public function getUserData(string $username): ?array
    {
        $allData = $this->loadAllData();
        return $allData[$username] ?? null;
    }

    /**
     * Save user data
     * @param string $username
     * @param array $weeks Weekly data structure
     * @param string|null $token
     * @return bool
     */
    public function saveUserData(string $username, array $weeks, ?string $token = null): bool
    {
        try {
            // Ensure directory exists and is writable
            if (!$this->ensureDirectoryExists()) {
                return false;
            }

            $allData = $this->loadAllData();

            // Preserve existing preferences and token if they exist
            $existingPreferences = $allData[$username][self::KEY_PREFERENCES] ?? [];
            $existingToken = $allData[$username][self::KEY_TOKEN] ?? null;

            $allData[$username] = [
                self::KEY_PREFERENCES => $existingPreferences,
                self::KEY_TOKEN => $token ?? $existingToken,
                self::KEY_WEEKS => $weeks
            ];

            return $this->saveAllData($allData);
        } catch (\Throwable $e) {
            error_log("Save data error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Save user preferences
     * @param string $username
     * @param array $preferences
     * @return bool
     */
    public function saveUserPreferences(string $username, array $preferences): bool
    {
        try {
            if (!$this->ensureDirectoryExists()) {
                return false;
            }

            $allData = $this->loadAllData();

            // Initialize user data if doesn't exist
            if (!isset($allData[$username])) {
                $allData[$username] = [
                    self::KEY_PREFERENCES => [],
                    self::KEY_TOKEN => null,
                    self::KEY_WEEKS => []
                ];
            }

            // Merge new preferences with existing ones
            $existingPreferences = $allData[$username][self::KEY_PREFERENCES] ?? [];
            $allData[$username][self::KEY_PREFERENCES] = array_merge($existingPreferences, $preferences);

            return $this->saveAllData($allData);
        } catch (\Throwable $e) {
            error_log("Save preferences error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get user preferences
     * @param string $username
     * @return array
     */
    public function getUserPreferences(string $username): array
    {
        $userData = $this->getUserData($username);
        return $userData[self::KEY_PREFERENCES] ?? [];
    }

    /**
     * Invalidate (remove) user token
     * @param string $username
     * @return bool
     */
    public function invalidateToken(string $username): bool
    {
        try {
            if (!$this->ensureDirectoryExists()) {
                return false;
            }

            $allData = $this->loadAllData();

            // If user doesn't exist, nothing to invalidate
            if (!isset($allData[$username])) {
                return true;
            }

            // Remove the token
            unset($allData[$username][self::KEY_TOKEN]);

            return $this->saveAllData($allData);
        } catch (\Throwable $e) {
            error_log("Invalidate token error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Ensure the storage directory exists and is writable
     * @return bool
     */
    private function ensureDirectoryExists(): bool
    {
        $dir = dirname($this->dataFile);

        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                error_log("Failed to create directory: $dir");
                return false;
            }
        }

        if (!is_writable($dir)) {
            error_log("Directory not writable: $dir");
            return false;
        }

        return true;
    }

    /**
     * Save all data to file with proper locking
     * @param array $data
     * @return bool
     */
    private function saveAllData(array $data): bool
    {
        $flags = JSON_UNESCAPED_UNICODE;
        if ($this->debugMode) {
            $flags |= JSON_PRETTY_PRINT;
        }

        $jsonData = json_encode($data, $flags);

        if ($jsonData === false) {
            error_log("Failed to encode JSON data");
            return false;
        }

        $result = file_put_contents($this->dataFile, $jsonData, LOCK_EX);
        if ($result === false) {
            error_log("Failed to write file: $this->dataFile");
            return false;
        }

        return true;
    }
}
