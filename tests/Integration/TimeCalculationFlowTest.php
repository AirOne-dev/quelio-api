<?php

namespace Tests\Integration;

use Tests\TestCase;
use TimeCalculator;
use Storage;

/**
 * Integration test for time calculation with storage
 */
class TimeCalculationFlowTest extends TestCase
{
    private TimeCalculator $calculator;
    private Storage $storage;
    private string $testDataFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testDataFile = sys_get_temp_dir() . '/test_time_flow_' . uniqid() . '.json';
        $this->calculator = new TimeCalculator($this->getConfig());
        $this->storage = new Storage($this->getConfig(), $this->testDataFile);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testDataFile)) {
            unlink($this->testDataFile);
        }
        parent::tearDown();
    }

    public function test_complete_hours_calculation_and_storage(): void
    {
        // Step 1: Merge hours from multiple sources (simulating Kelio API)
        $hours1 = ['13/01/2026' => ['08:30', '12:00']];
        $hours2 = ['13/01/2026' => ['13:00', '18:30']];
        $hours3 = ['14/01/2026' => ['08:30', '17:30']];

        $mergedHours = $this->calculator->mergeHoursByDay($hours1, $hours2, $hours3);

        // Step 2: Calculate totals
        $effective = $this->calculator->calculateTotalWorkingHours($mergedHours, 0);
        $paid = $this->calculator->calculateTotalWorkingHours($mergedHours, 7);

        $this->assertNotEmpty($effective);
        $this->assertNotEmpty($paid);

        // Step 3: Save to storage
        $username = 'testuser';
        $userData = [
            'users' => [
                $username => [
                    'hours' => $mergedHours,
                    'total_effective' => $effective,
                    'total_paid' => $paid,
                    'last_save' => date('d/m/Y H:i:s')
                ]
            ]
        ];

        $this->storage->saveData($userData);

        // Step 4: Load and verify
        $loadedData = $this->storage->loadData();

        $this->assertEquals($effective, $loadedData['users'][$username]['total_effective']);
        $this->assertEquals($paid, $loadedData['users'][$username]['total_paid']);
        $this->assertArrayHasKey('hours', $loadedData['users'][$username]);
    }

    public function test_weekly_accumulation_flow(): void
    {
        $username = 'testuser';

        // Simulate adding hours day by day
        $dailyHours = [
            ['13-01-2026' => ['08:30', '12:00', '13:00', '18:30']],
            ['14-01-2026' => ['08:30', '17:30']],
            ['15-01-2026' => ['08:30', '12:00', '12:45', '18:00']],
        ];

        foreach ($dailyHours as $dayIndex => $hours) {
            // Load existing data
            $data = $this->storage->loadData();
            $existingHours = $data['users'][$username]['hours'] ?? [];

            // Merge new hours
            $mergedHours = $this->calculator->mergeHoursByDay($existingHours, $hours);

            // Calculate new totals
            $effective = $this->calculator->calculateTotalWorkingHours($mergedHours, 0);
            $paid = $this->calculator->calculateTotalWorkingHours($mergedHours, 7);

            // Save updated data
            $data['users'][$username] = [
                'hours' => $mergedHours,
                'total_effective' => $effective,
                'total_paid' => $paid,
            ];

            $this->storage->saveData($data);
        }

        // Verify final state
        $finalData = $this->storage->loadData();
        $userHours = $finalData['users'][$username]['hours'];

        // Should have all 3 days
        $this->assertCount(3, $userHours);
        $this->assertArrayHasKey('13-01-2026', $userHours);
        $this->assertArrayHasKey('14-01-2026', $userHours);
        $this->assertArrayHasKey('15-01-2026', $userHours);

        // Totals should be cumulative
        $this->assertMatchesRegularExpression('/^\d{2}:\d{2}$/', $finalData['users'][$username]['total_effective']);
        $this->assertMatchesRegularExpression('/^\d{2}:\d{2}$/', $finalData['users'][$username]['total_paid']);
    }
}
