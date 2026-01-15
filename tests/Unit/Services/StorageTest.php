<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use Storage;

class StorageTest extends TestCase
{
    private Storage $storage;
    private string $testDataFile;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a temporary data file for testing
        $this->testDataFile = sys_get_temp_dir() . '/test_data_' . uniqid() . '.json';
        $this->storage = new Storage($this->getConfig(), $this->testDataFile);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testDataFile)) {
            unlink($this->testDataFile);
        }
        parent::tearDown();
    }

    public function test_saves_and_loads_data(): void
    {
        $testData = [
            'users' => [
                'testuser' => [
                    'hours' => ['13-01-2026' => ['08:30', '18:30']],
                    'total_effective' => '10:00',
                    'total_paid' => '10:14',
                ]
            ]
        ];

        $this->storage->saveData($testData);
        $loaded = $this->storage->loadData();

        $this->assertEquals($testData, $loaded);
    }

    public function test_returns_empty_array_if_file_not_exists(): void
    {
        $storage = new Storage($this->getConfig(), '/nonexistent/path/data.json');
        $result = $storage->loadData();

        $this->assertEquals(['users' => []], $result);
    }

    public function test_saves_user_preferences(): void
    {
        $preferences = [
            'theme' => 'ocean',
            'minutes_objective' => 2280
        ];

        $this->storage->saveUserPreferences('testuser', $preferences);
        $loaded = $this->storage->getUserPreferences('testuser');

        $this->assertEquals($preferences, $loaded);
    }

    public function test_returns_default_preferences_for_new_user(): void
    {
        $preferences = $this->storage->getUserPreferences('newuser');

        $this->assertIsArray($preferences);
        $this->assertEmpty($preferences);
    }

    public function test_updates_session_token(): void
    {
        $token = 'test_token_12345';

        $this->storage->saveData([
            'users' => [
                'testuser' => [
                    'hours' => [],
                    'session_token' => 'old_token'
                ]
            ]
        ]);

        $this->storage->updateSessionToken('testuser', $token);
        $data = $this->storage->loadData();

        $this->assertEquals($token, $data['users']['testuser']['session_token']);
    }

    public function test_invalidates_token(): void
    {
        $this->storage->saveData([
            'users' => [
                'testuser' => [
                    'session_token' => 'valid_token'
                ]
            ]
        ]);

        $this->storage->invalidateToken('testuser');
        $data = $this->storage->loadData();

        $this->assertArrayNotHasKey('session_token', $data['users']['testuser']);
    }

    public function test_pretty_prints_json_in_debug_mode(): void
    {
        $config = $this->getConfig();
        $config['debug_mode'] = true;
        $storage = new Storage($config, $this->testDataFile);

        $testData = ['users' => ['test' => ['data' => 'value']]];
        $storage->saveData($testData);

        $fileContents = file_get_contents($this->testDataFile);

        // Pretty printed JSON should have newlines
        $this->assertStringContainsString("\n", $fileContents);
    }

    public function test_minifies_json_in_production_mode(): void
    {
        $config = $this->getConfig();
        $config['debug_mode'] = false;
        $storage = new Storage($config, $this->testDataFile);

        $testData = ['users' => ['test' => ['data' => 'value']]];
        $storage->saveData($testData);

        $fileContents = file_get_contents($this->testDataFile);

        // Minified JSON should not have extra newlines
        $this->assertEquals(1, substr_count($fileContents, "\n"));
    }
}
