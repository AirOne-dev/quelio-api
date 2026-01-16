<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use Storage;

/**
 * Unit Tests - Storage Service
 * Tests JSON file storage with locking, user data, and preferences
 */
class StorageTest extends TestCase
{
    private Storage $storage;
    private string $testDataFile;

    protected function setUp(): void
    {
        parent::setUp();

        // Create storage with debug mode (pretty print)
        $this->storage = new Storage(true);
        $this->testDataFile = $this->storage->getDataFilePath();
    }

    protected function tearDown(): void
    {
        // Clean up test file
        if (file_exists($this->testDataFile)) {
            unlink($this->testDataFile);
        }
        parent::tearDown();
    }

    // ========================================================================
    // DATA FILE MANAGEMENT
    // ========================================================================

    public function test_creates_data_file_on_first_save(): void
    {
        $username = 'testuser';

        $result = $this->storage->saveUserData($username, [], '00:00', '00:00');

        $this->assertTrue($result);
        $this->assertFileExists($this->testDataFile);
    }

    public function test_returns_empty_array_when_file_not_exists(): void
    {
        $data = $this->storage->loadAllData();

        $this->assertIsArray($data);
        $this->assertEmpty($data);
    }

    public function test_gets_data_file_path(): void
    {
        $path = $this->storage->getDataFilePath();

        $this->assertNotEmpty($path);
        $this->assertIsString($path);
    }

    // ========================================================================
    // USER DATA OPERATIONS
    // ========================================================================

    public function test_saves_and_loads_user_data(): void
    {
        $username = 'testuser';
        $hours = ['13/01/2026' => ['08:30', '12:00', '13:00', '18:30']];
        $totalEffective = '10:00';
        $totalPaid = '10:14';

        $this->storage->saveUserData($username, $hours, $totalEffective, $totalPaid);

        $userData = $this->storage->getUserData($username);

        $this->assertNotNull($userData);
        $this->assertEquals($hours, $userData['hours']);
        $this->assertEquals($totalEffective, $userData['total_effective']);
        $this->assertEquals($totalPaid, $userData['total_paid']);
        $this->assertArrayHasKey('last_save', $userData);
    }

    public function test_returns_null_for_nonexistent_user(): void
    {
        $userData = $this->storage->getUserData('nonexistent');

        $this->assertNull($userData);
    }

    public function test_saves_user_data_with_token(): void
    {
        $username = 'testuser';
        $token = 'test_token_123';

        $this->storage->saveUserData($username, [], '00:00', '00:00', $token);

        $userData = $this->storage->getUserData($username);

        $this->assertEquals($token, $userData['session_token']);
    }

    public function test_preserves_existing_preferences_when_saving_data(): void
    {
        $username = 'testuser';
        $preferences = ['color1' => 'ff0000', 'color2' => '00ff00'];

        // Save preferences first
        $this->storage->saveUserPreferences($username, $preferences);

        // Save user data (should preserve preferences)
        $this->storage->saveUserData($username, [], '00:00', '00:00');

        $userData = $this->storage->getUserData($username);

        $this->assertEquals($preferences, $userData['preferences']);
    }

    public function test_preserves_existing_token_when_saving_data_without_token(): void
    {
        $username = 'testuser';
        $token = 'existing_token';

        // Save with token
        $this->storage->saveUserData($username, [], '00:00', '00:00', $token);

        // Save again without token
        $this->storage->saveUserData($username, [], '01:00', '01:00', null);

        $userData = $this->storage->getUserData($username);

        // Token should still be there
        $this->assertEquals($token, $userData['session_token']);
    }

    // ========================================================================
    // USER PREFERENCES
    // ========================================================================

    public function test_saves_and_loads_user_preferences(): void
    {
        $username = 'testuser';
        $preferences = [
            'color1' => 'ff0000',
            'color2' => '00ff00'
        ];

        $result = $this->storage->saveUserPreferences($username, $preferences);

        $this->assertTrue($result);

        $loaded = $this->storage->getUserPreferences($username);

        $this->assertEquals($preferences, $loaded);
    }

    public function test_merges_preferences_on_save(): void
    {
        $username = 'testuser';

        // Save first set of preferences
        $this->storage->saveUserPreferences($username, ['color1' => 'ff0000']);

        // Save second set (should merge)
        $this->storage->saveUserPreferences($username, ['color2' => '00ff00']);

        $preferences = $this->storage->getUserPreferences($username);

        $this->assertEquals('ff0000', $preferences['color1']);
        $this->assertEquals('00ff00', $preferences['color2']);
    }

    public function test_overwrites_existing_preference_key(): void
    {
        $username = 'testuser';

        $this->storage->saveUserPreferences($username, ['color1' => 'ff0000']);
        $this->storage->saveUserPreferences($username, ['color1' => '0000ff']);

        $preferences = $this->storage->getUserPreferences($username);

        $this->assertEquals('0000ff', $preferences['color1']);
    }

    public function test_returns_empty_array_for_user_without_preferences(): void
    {
        $preferences = $this->storage->getUserPreferences('nonexistent');

        $this->assertIsArray($preferences);
        $this->assertEmpty($preferences);
    }

    public function test_initializes_user_data_when_saving_preferences_for_new_user(): void
    {
        $username = 'newuser';

        $this->storage->saveUserPreferences($username, ['color1' => 'ff0000']);

        $userData = $this->storage->getUserData($username);

        $this->assertNotNull($userData);
        $this->assertArrayHasKey('hours', $userData);
        $this->assertArrayHasKey('total_effective', $userData);
        $this->assertArrayHasKey('total_paid', $userData);
    }

    // ========================================================================
    // TOKEN MANAGEMENT
    // ========================================================================

    public function test_invalidates_user_token(): void
    {
        $username = 'testuser';
        $token = 'test_token';

        // Save with token
        $this->storage->saveUserData($username, [], '00:00', '00:00', $token);

        // Verify token exists
        $userData = $this->storage->getUserData($username);
        $this->assertEquals($token, $userData['session_token']);

        // Invalidate token
        $result = $this->storage->invalidateToken($username);

        $this->assertTrue($result);

        // Verify token is removed
        $userData = $this->storage->getUserData($username);
        $this->assertArrayNotHasKey('session_token', $userData);
    }

    public function test_invalidate_returns_true_for_nonexistent_user(): void
    {
        $result = $this->storage->invalidateToken('nonexistent');

        $this->assertTrue($result);
    }

    // ========================================================================
    // JSON FORMATTING
    // ========================================================================

    public function test_pretty_prints_json_in_debug_mode(): void
    {
        $storage = new Storage(true); // Debug mode
        $username = 'testuser';

        $storage->saveUserData($username, [], '00:00', '00:00');

        $filePath = $storage->getDataFilePath();
        $content = file_get_contents($filePath);

        // Pretty printed JSON should contain newlines and indentation
        $this->assertStringContainsString("\n", $content);
        $this->assertStringContainsString('    ', $content); // Indentation

        // Clean up
        unlink($filePath);
    }

    public function test_minifies_json_in_production_mode(): void
    {
        $storage = new Storage(false); // Production mode
        $username = 'testuser';

        $storage->saveUserData($username, [], '00:00', '00:00');

        $filePath = $storage->getDataFilePath();
        $content = file_get_contents($filePath);

        // Minified JSON should be compact (no pretty spacing)
        $decoded = json_decode($content, true);
        $this->assertNotNull($decoded);

        // Should not have excessive whitespace
        $compactVersion = json_encode($decoded);
        $this->assertLessThanOrEqual(strlen($content) + 50, strlen($compactVersion) + 50);

        // Clean up
        unlink($filePath);
    }

    // ========================================================================
    // MULTIPLE USERS
    // ========================================================================

    public function test_handles_multiple_users(): void
    {
        $user1 = 'user1';
        $user2 = 'user2';

        $this->storage->saveUserData($user1, ['date1' => ['08:30']], '08:30', '08:30');
        $this->storage->saveUserData($user2, ['date2' => ['09:00']], '09:00', '09:00');

        $allData = $this->storage->loadAllData();

        $this->assertArrayHasKey($user1, $allData);
        $this->assertArrayHasKey($user2, $allData);
        $this->assertCount(2, $allData);
    }

    public function test_updates_existing_user_data_without_affecting_others(): void
    {
        $user1 = 'user1';
        $user2 = 'user2';

        $this->storage->saveUserData($user1, ['date1' => ['08:30']], '08:30', '08:30');
        $this->storage->saveUserData($user2, ['date2' => ['09:00']], '09:00', '09:00');

        // Update user1
        $this->storage->saveUserData($user1, ['date1' => ['10:00']], '10:00', '10:00');

        $user1Data = $this->storage->getUserData($user1);
        $user2Data = $this->storage->getUserData($user2);

        $this->assertEquals(['date1' => ['10:00']], $user1Data['hours']);
        $this->assertEquals(['date2' => ['09:00']], $user2Data['hours']); // Unchanged
    }

    // ========================================================================
    // EDGE CASES & ERROR HANDLING
    // ========================================================================

    public function test_handles_empty_hours_array(): void
    {
        $username = 'testuser';

        $result = $this->storage->saveUserData($username, [], '00:00', '00:00');

        $this->assertTrue($result);

        $userData = $this->storage->getUserData($username);
        $this->assertEquals([], $userData['hours']);
    }

    public function test_handles_unicode_in_data(): void
    {
        $username = 'tëstüser_日本語';

        $result = $this->storage->saveUserData($username, [], '00:00', '00:00');

        $this->assertTrue($result);

        $userData = $this->storage->getUserData($username);
        $this->assertNotNull($userData);
    }

    public function test_load_all_data_returns_array(): void
    {
        $data = $this->storage->loadAllData();

        $this->assertIsArray($data);
    }
}
