<?php

namespace Tests\Unit\Controllers;

use Tests\TestCase;
use DataController;

/**
 * Unit Tests - DataController
 * Tests admin data access endpoint
 */
class DataControllerTest extends TestCase
{
    private DataController $controller;
    private string $dataFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->controller = new DataController();
        $this->dataFile = __DIR__ . '/../../../data.json';
    }

    protected function tearDown(): void
    {
        // Clean up test data file
        if (file_exists($this->dataFile)) {
            unlink($this->dataFile);
        }

        parent::tearDown();
    }

    // ========================================================================
    // SUCCESSFUL DATA RETRIEVAL
    // ========================================================================

    public function test_returns_data_when_file_exists(): void
    {
        // Create test data file
        $testData = [
            'testuser' => [
                'hours' => ['13/01/2026' => ['08:30', '12:00', '13:00', '18:30']],
                'total_effective' => '10:00',
                'total_paid' => '10:14',
                'last_save' => '2026-01-15 10:00:00'
            ]
        ];

        file_put_contents($this->dataFile, json_encode($testData, JSON_PRETTY_PRINT));

        ob_start();
        $this->controller->indexAction();
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertNotNull($response);
        // JsonResponse::success() wraps data, so check for nested structure
        $this->assertArrayHasKey('testuser', $response);
        $this->assertEquals($testData['testuser']['total_effective'], $response['testuser']['total_effective']);
    }

    public function test_returns_multiple_users_data(): void
    {
        // Create test data with multiple users
        $testData = [
            'user1' => [
                'hours' => [],
                'total_effective' => '08:00',
                'total_paid' => '08:07'
            ],
            'user2' => [
                'hours' => [],
                'total_effective' => '09:00',
                'total_paid' => '09:07'
            ]
        ];

        file_put_contents($this->dataFile, json_encode($testData));

        ob_start();
        $this->controller->indexAction();
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertArrayHasKey('user1', $response);
        $this->assertArrayHasKey('user2', $response);
    }

    public function test_returns_empty_object_when_file_is_empty_json(): void
    {
        // Create empty JSON file
        file_put_contents($this->dataFile, '{}');

        ob_start();
        $this->controller->indexAction();
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertIsArray($response);
        // Empty JSON {} should be returned as empty array
        $this->assertEmpty($response);
    }

    // ========================================================================
    // ERROR HANDLING
    // ========================================================================

    public function test_returns_404_when_file_not_exists(): void
    {
        // Ensure file doesn't exist
        if (file_exists($this->dataFile)) {
            unlink($this->dataFile);
        }

        ob_start();
        $this->controller->indexAction();
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertArrayHasKey('error', $response);
        $this->assertStringContainsString('not found', $response['error']);
    }

    public function test_handles_malformed_json(): void
    {
        // Create file with invalid JSON
        file_put_contents($this->dataFile, '{invalid json content}');

        ob_start();
        $this->controller->indexAction();
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertArrayHasKey('error', $response);
        $this->assertStringContainsString('parse', strtolower($response['error']));
    }

    public function test_handles_empty_file(): void
    {
        // Create empty file (not even empty JSON)
        file_put_contents($this->dataFile, '');

        ob_start();
        $this->controller->indexAction();
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertArrayHasKey('error', $response);
    }

    // ========================================================================
    // DATA INTEGRITY
    // ========================================================================

    public function test_preserves_user_preferences(): void
    {
        $testData = [
            'testuser' => [
                'hours' => [],
                'total_effective' => '00:00',
                'total_paid' => '00:00',
                'preferences' => [
                    'theme' => 'ocean',
                    'minutes_objective' => 480
                ]
            ]
        ];

        file_put_contents($this->dataFile, json_encode($testData));

        ob_start();
        $this->controller->indexAction();
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertEquals('ocean', $response['testuser']['preferences']['theme']);
        $this->assertEquals(480, $response['testuser']['preferences']['minutes_objective']);
    }

    public function test_preserves_session_tokens(): void
    {
        $testToken = 'dGVzdHVzZXI6ZW5jcnlwdGVkX3Bhc3M6MTcwNTMxNjQwMDpzaWduYXR1cmU=';

        $testData = [
            'testuser' => [
                'hours' => [],
                'total_effective' => '00:00',
                'total_paid' => '00:00',
                'session_token' => $testToken
            ]
        ];

        file_put_contents($this->dataFile, json_encode($testData));

        ob_start();
        $this->controller->indexAction();
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertEquals($testToken, $response['testuser']['session_token']);
    }

    public function test_preserves_hours_data_structure(): void
    {
        $weeksData = [
            '2026-w-03' => [
                'days' => [
                    '13-01-2026' => [
                        'hours' => ['08:30', '12:00', '13:00', '18:30'],
                        'breaks' => ['morning' => '00:00', 'noon' => '01:00', 'afternoon' => '00:00'],
                        'effective_to_paid' => [],
                        'effective' => '09:00',
                        'paid' => '09:14'
                    ],
                    '14-01-2026' => [
                        'hours' => ['08:45', '12:15', '13:15', '18:00'],
                        'breaks' => ['morning' => '00:00', 'noon' => '01:00', 'afternoon' => '00:00'],
                        'effective_to_paid' => [],
                        'effective' => '09:00',
                        'paid' => '09:14'
                    ]
                ],
                'total_effective' => '18:00',
                'total_paid' => '18:14'
            ]
        ];

        $testData = [
            'testuser' => [
                'preferences' => [],
                'token' => null,
                'weeks' => $weeksData
            ]
        ];

        file_put_contents($this->dataFile, json_encode($testData));

        ob_start();
        $this->controller->indexAction();
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertEquals($weeksData, $response['testuser']['weeks']);
        $this->assertCount(4, $response['testuser']['weeks']['2026-w-03']['days']['13-01-2026']['hours']);
    }

    // ========================================================================
    // SPECIAL CASES
    // ========================================================================

    public function test_handles_unicode_in_data(): void
    {
        $testData = [
            'tëstüser_日本語' => [
                'preferences' => [],
                'token' => null,
                'weeks' => []
            ]
        ];

        file_put_contents($this->dataFile, json_encode($testData, JSON_UNESCAPED_UNICODE));

        ob_start();
        $this->controller->indexAction();
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertArrayHasKey('tëstüser_日本語', $response);
    }

    public function test_handles_large_hours_array(): void
    {
        // Create data with many days across multiple weeks
        $weeks = [];
        for ($i = 1; $i <= 31; $i++) {
            $date = sprintf('%02d-01-2026', $i);
            $weekKey = '2026-w-' . str_pad((int)ceil($i / 7), 2, '0', STR_PAD_LEFT);

            if (!isset($weeks[$weekKey])) {
                $weeks[$weekKey] = [
                    'days' => [],
                    'total_effective' => '00:00',
                    'total_paid' => '00:00'
                ];
            }

            $weeks[$weekKey]['days'][$date] = [
                'hours' => ['08:30', '12:00', '13:00', '18:30'],
                'breaks' => ['morning' => '00:00', 'noon' => '01:00', 'afternoon' => '00:00'],
                'effective_to_paid' => [],
                'effective' => '09:00',
                'paid' => '09:14'
            ];
        }

        $testData = [
            'testuser' => [
                'preferences' => [],
                'token' => null,
                'weeks' => $weeks
            ]
        ];

        file_put_contents($this->dataFile, json_encode($testData));

        ob_start();
        $this->controller->indexAction();
        $output = ob_get_clean();

        $response = json_decode($output, true);

        // Count total days across all weeks
        $totalDays = 0;
        foreach ($response['testuser']['weeks'] as $week) {
            $totalDays += count($week['days']);
        }
        $this->assertEquals(31, $totalDays);
    }
}
