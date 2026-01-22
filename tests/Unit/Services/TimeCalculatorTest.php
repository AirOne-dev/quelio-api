<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use TimeCalculator;

class TimeCalculatorTest extends TestCase
{
    private TimeCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new TimeCalculator($this->getConfig());
    }

    public function test_merges_hours_by_day(): void
    {
        $result = $this->calculator->mergeHoursByDay(
            ['13/01/2026' => ['08:30', '12:00']],
            ['13/01/2026' => ['13:00', '18:30']]
        );

        $this->assertArrayHasKey('13-01-2026', $result);
        $this->assertEquals(['08:30', '12:00', '13:00', '18:30'], $result['13-01-2026']);
    }

    public function test_calculates_effective_hours_single_day(): void
    {
        $data = ['15-01-2026' => ['08:30', '12:00', '13:00', '18:30']];
        $result = $this->calculator->calculateTotalWorkingHours($data, 0);

        $this->assertEquals('09:00', $result);
    }

    public function test_calculates_paid_hours_with_bonus(): void
    {
        $data = ['15-01-2026' => ['08:30', '12:00', '13:00', '18:30']];
        $result = $this->calculator->calculateTotalWorkingHours($data, 7);

        // 9h00 effective + 14min bonus (7+7) = 9h14
        $this->assertEquals('09:14', $result);
    }

    public function test_applies_noon_minimum_break_rule(): void
    {
        // 47min lunch break (< 1h minimum)
        $data = ['15-01-2026' => ['08:30', '12:00', '12:47', '18:30']];
        $result = $this->calculator->calculateTotalWorkingHours($data, 7);

        // 9h13 effective + 14min bonus - 13min gained = 9h14
        $this->assertEquals('09:14', $result);
    }

    public function test_no_penalty_for_long_lunch(): void
    {
        // 1h30 lunch break (> 1h minimum)
        $data = ['15-01-2026' => ['08:30', '12:00', '13:30', '18:30']];
        $result = $this->calculator->calculateTotalWorkingHours($data, 7);

        // 8h30 effective + 14min bonus = 8h44
        $this->assertEquals('08:44', $result);
    }

    public function test_multiple_days_calculation(): void
    {
        $data = [
            '13-01-2026' => ['08:30', '12:00', '13:00', '18:30'],
            '14-01-2026' => ['08:30', '12:00', '13:00', '17:30'],
        ];

        $result = $this->calculator->calculateTotalWorkingHours($data, 7);

        // Day 1: 9h + 14min = 9h14
        // Day 2: 8h + 14min = 8h14
        // Total: 17h28
        $this->assertEquals('17:28', $result);
    }

    public function test_handles_no_lunch_break(): void
    {
        $data = ['15-01-2026' => ['08:30', '18:30']];
        $result = $this->calculator->calculateTotalWorkingHours($data, 7);

        // 10h effective + 14min bonus, no lunch deduction = 10h14
        $this->assertEquals('10:14', $result);
    }

    public function test_limits_deduction_to_bonus_amount(): void
    {
        // Multiple breaks with only 30min lunch
        $data = ['15-01-2026' => ['08:30', '10:00', '10:15', '12:00', '12:30', '18:30']];
        $result = $this->calculator->calculateTotalWorkingHours($data, 7);

        // Gained: 30min, but only 14min bonus → deduct min(30, 14) = 14min
        // Result should be >= effective hours
        $effective = $this->calculator->calculateTotalWorkingHours($data, 0);
        $this->assertGreaterThanOrEqual($effective, $result);
    }

    // ========================================================================
    // WEEKLY DATA CALCULATION
    // ========================================================================

    public function test_calculates_weekly_data_single_day(): void
    {
        $mergedHours = [
            '13-01-2026' => ['08:30', '12:00', '13:00', '18:30']
        ];

        $weeks = $this->calculator->calculateWeeklyData($mergedHours);

        $this->assertArrayHasKey('2026-w-03', $weeks);
        $this->assertArrayHasKey('days', $weeks['2026-w-03']);
        $this->assertArrayHasKey('13-01-2026', $weeks['2026-w-03']['days']);
        $this->assertArrayHasKey('hours', $weeks['2026-w-03']['days']['13-01-2026']);
        $this->assertArrayHasKey('breaks', $weeks['2026-w-03']['days']['13-01-2026']);
        $this->assertArrayHasKey('effective', $weeks['2026-w-03']['days']['13-01-2026']);
        $this->assertArrayHasKey('paid', $weeks['2026-w-03']['days']['13-01-2026']);
        $this->assertArrayHasKey('effective_to_paid', $weeks['2026-w-03']['days']['13-01-2026']);
    }

    public function test_calculates_weekly_data_multiple_days_same_week(): void
    {
        $mergedHours = [
            '13-01-2026' => ['08:30', '12:00', '13:00', '18:30'], // Lundi
            '14-01-2026' => ['08:30', '12:00', '13:00', '17:30'], // Mardi
            '15-01-2026' => ['08:30', '12:00', '13:00', '18:00']  // Mercredi
        ];

        $weeks = $this->calculator->calculateWeeklyData($mergedHours);

        $this->assertCount(1, $weeks, 'Should have exactly 1 week');
        $this->assertArrayHasKey('2026-w-03', $weeks);
        $this->assertCount(3, $weeks['2026-w-03']['days'], 'Week should have 3 days');

        // Verify weekly totals
        $this->assertNotEmpty($weeks['2026-w-03']['total_effective']);
        $this->assertNotEmpty($weeks['2026-w-03']['total_paid']);
    }

    public function test_calculates_weekly_data_multiple_weeks(): void
    {
        $mergedHours = [
            // Week 3 (13-19 Jan)
            '13-01-2026' => ['08:30', '12:00', '13:00', '18:30'],
            '14-01-2026' => ['08:30', '12:00', '13:00', '17:30'],

            // Week 4 (20-26 Jan)
            '20-01-2026' => ['08:30', '12:00', '13:00', '18:00'],
            '21-01-2026' => ['09:00', '12:30', '13:30', '18:00'],

            // Week 5 (27 Jan - 2 Feb)
            '27-01-2026' => ['08:00', '12:00', '13:00', '17:00']
        ];

        $weeks = $this->calculator->calculateWeeklyData($mergedHours);

        // Verify we have 3 different weeks
        $this->assertCount(3, $weeks, 'Should have exactly 3 weeks');
        $this->assertArrayHasKey('2026-w-03', $weeks, 'Should have week 3');
        $this->assertArrayHasKey('2026-w-04', $weeks, 'Should have week 4');
        $this->assertArrayHasKey('2026-w-05', $weeks, 'Should have week 5');

        // Verify each week has correct number of days
        $this->assertCount(2, $weeks['2026-w-03']['days'], 'Week 3 should have 2 days');
        $this->assertCount(2, $weeks['2026-w-04']['days'], 'Week 4 should have 2 days');
        $this->assertCount(1, $weeks['2026-w-05']['days'], 'Week 5 should have 1 day');

        // Verify all weeks have totals
        foreach ($weeks as $weekKey => $weekData) {
            $this->assertArrayHasKey('total_effective', $weekData, "$weekKey should have total_effective");
            $this->assertArrayHasKey('total_paid', $weekData, "$weekKey should have total_paid");
            $this->assertMatchesRegularExpression('/^\d{2}:\d{2}$/', $weekData['total_effective'], "$weekKey total_effective should be HH:MM format");
            $this->assertMatchesRegularExpression('/^\d{2}:\d{2}$/', $weekData['total_paid'], "$weekKey total_paid should be HH:MM format");
        }
    }

    public function test_weekly_data_includes_break_details(): void
    {
        $mergedHours = [
            '13-01-2026' => ['08:30', '12:00', '13:00', '18:30']
        ];

        $weeks = $this->calculator->calculateWeeklyData($mergedHours);
        $day = $weeks['2026-w-03']['days']['13-01-2026'];

        // Verify break structure
        $this->assertArrayHasKey('breaks', $day);
        $this->assertArrayHasKey('morning', $day['breaks']);
        $this->assertArrayHasKey('noon', $day['breaks']);
        $this->assertArrayHasKey('afternoon', $day['breaks']);

        // Verify break format (HH:MM)
        $this->assertMatchesRegularExpression('/^\d{2}:\d{2}$/', $day['breaks']['morning']);
        $this->assertMatchesRegularExpression('/^\d{2}:\d{2}$/', $day['breaks']['noon']);
        $this->assertMatchesRegularExpression('/^\d{2}:\d{2}$/', $day['breaks']['afternoon']);
    }

    public function test_weekly_data_includes_effective_to_paid_transformations(): void
    {
        $mergedHours = [
            '13-01-2026' => ['08:30', '12:00', '13:00', '18:30']
        ];

        $weeks = $this->calculator->calculateWeeklyData($mergedHours);
        $day = $weeks['2026-w-03']['days']['13-01-2026'];

        // Verify effective_to_paid is an array
        $this->assertIsArray($day['effective_to_paid']);

        // Should have transformations (morning and afternoon breaks)
        $this->assertNotEmpty($day['effective_to_paid']);

        // Each transformation should be a string describing the change
        foreach ($day['effective_to_paid'] as $transformation) {
            $this->assertIsString($transformation);
            $this->assertNotEmpty($transformation);
        }
    }

    // ========================================================================
    // EFFECTIVE TO PAID TRANSFORMATIONS - ALL TYPES
    // ========================================================================

    public function test_effective_to_paid_includes_morning_break_bonus(): void
    {
        // Work past 11h00 to trigger morning break
        $mergedHours = [
            '13-01-2026' => ['08:30', '12:00']
        ];

        $weeks = $this->calculator->calculateWeeklyData($mergedHours);
        $transformations = $weeks['2026-w-03']['days']['13-01-2026']['effective_to_paid'];

        // Should contain morning break bonus
        $this->assertNotEmpty($transformations);
        $morningBreakFound = false;
        foreach ($transformations as $t) {
            if (str_contains($t, 'morning break')) {
                $morningBreakFound = true;
                $this->assertStringContainsString('+ 00:07', $t);
            }
        }
        $this->assertTrue($morningBreakFound, 'Morning break bonus should be present');
    }

    public function test_effective_to_paid_includes_afternoon_break_bonus(): void
    {
        // Work past 16h00 to trigger afternoon break
        $mergedHours = [
            '13-01-2026' => ['08:30', '17:00']
        ];

        $weeks = $this->calculator->calculateWeeklyData($mergedHours);
        $transformations = $weeks['2026-w-03']['days']['13-01-2026']['effective_to_paid'];

        // Should contain afternoon break bonus
        $this->assertNotEmpty($transformations);
        $afternoonBreakFound = false;
        foreach ($transformations as $t) {
            if (str_contains($t, 'afternoon break')) {
                $afternoonBreakFound = true;
                $this->assertStringContainsString('+ 00:07', $t);
            }
        }
        $this->assertTrue($afternoonBreakFound, 'Afternoon break bonus should be present');
    }

    public function test_effective_to_paid_includes_both_break_bonuses(): void
    {
        // Work past both 11h00 and 16h00 to trigger both bonuses
        $mergedHours = [
            '13-01-2026' => ['08:30', '12:00', '13:00', '18:30']
        ];

        $weeks = $this->calculator->calculateWeeklyData($mergedHours);
        $transformations = $weeks['2026-w-03']['days']['13-01-2026']['effective_to_paid'];

        // Should have 2 transformations (morning + afternoon)
        $this->assertCount(2, $transformations);

        // Verify both are present
        $transformationsText = implode(' ', $transformations);
        $this->assertStringContainsString('morning break', $transformationsText);
        $this->assertStringContainsString('afternoon break', $transformationsText);
        $this->assertStringContainsString('+ 00:07', $transformationsText);
    }

    public function test_effective_to_paid_includes_noon_break_deduction(): void
    {
        // Work with short lunch break (< 1h minimum)
        // Use example from the schema: 21-01-2026 with 54min lunch break
        // 08:31-12:14, 13:08-17:36 = 54min lunch break (< 1h)
        $mergedHours = [
            '21-01-2026' => ['08:31', '10:41', '10:47', '12:14', '13:08', '17:36']
        ];

        $weeks = $this->calculator->calculateWeeklyData($mergedHours);
        $transformations = $weeks['2026-w-04']['days']['21-01-2026']['effective_to_paid'];

        // Should have 3 transformations: morning, afternoon, noon deduction
        $this->assertGreaterThanOrEqual(3, count($transformations),
            "Should have at least 3 transformations");

        // Find the noon break deduction (be careful: "noon break" is also in "afternoon break")
        $noonDeductionFound = false;
        foreach ($transformations as $t) {
            if (str_contains($t, '(noon break)')) {  // More specific: the actual noon break duration
                $noonDeductionFound = true;
                $this->assertStringContainsString('-', $t, "Deduction should start with -. Got: $t"); // Should be a deduction
                $this->assertStringContainsString('01:00 (minimum)', $t); // 1h minimum
                $this->assertStringContainsString('00:54 (noon break)', $t); // Actual break (54 min)
            }
        }
        $this->assertTrue($noonDeductionFound,
            "Noon break deduction should be present. All transformations: " . json_encode($transformations));
    }

    public function test_effective_to_paid_no_deduction_for_long_lunch(): void
    {
        // Work with long lunch break (>= 1h minimum)
        // 08:30-12:00, 13:30-18:30 = 1h30 lunch break (>= 1h)
        $mergedHours = [
            '13-01-2026' => ['08:30', '12:00', '13:30', '18:30']
        ];

        $weeks = $this->calculator->calculateWeeklyData($mergedHours);
        $transformations = $weeks['2026-w-03']['days']['13-01-2026']['effective_to_paid'];

        // Should have only 2 transformations: morning, afternoon (no deduction)
        $this->assertCount(2, $transformations);

        // Verify no noon break deduction
        foreach ($transformations as $t) {
            $this->assertStringNotContainsString('noon break =>', $t);
        }
    }

    public function test_effective_to_paid_empty_for_short_work_day(): void
    {
        // Work before 11h00 (no morning break threshold)
        $mergedHours = [
            '13-01-2026' => ['08:30', '10:30']
        ];

        $weeks = $this->calculator->calculateWeeklyData($mergedHours);
        $transformations = $weeks['2026-w-03']['days']['13-01-2026']['effective_to_paid'];

        // Should be empty (no bonuses earned, no deductions)
        $this->assertEmpty($transformations);
    }

    public function test_effective_to_paid_deduction_limited_to_bonus(): void
    {
        // Work with very short lunch break to test deduction limit
        // 08:30-12:00, 12:30-18:30 = 30min lunch break
        // Gained: 60min - 30min = 30min
        // Bonuses: 7min + 7min = 14min
        // Deduction should be limited to 14min (not 30min)
        $mergedHours = [
            '13-01-2026' => ['08:30', '12:00', '12:30', '18:30']
        ];

        $weeks = $this->calculator->calculateWeeklyData($mergedHours);
        $day = $weeks['2026-w-03']['days']['13-01-2026'];
        $transformations = $day['effective_to_paid'];

        // Should have 3 transformations
        $this->assertCount(3, $transformations);

        // Find the deduction and verify it's 14min (not 30min)
        $deductionFound = false;
        foreach ($transformations as $t) {
            if (str_contains($t, '(noon break)') && str_contains($t, '-')) {
                $deductionFound = true;
                // Should deduct 14min (total bonus), not 30min (gained time)
                $this->assertStringContainsString('- 00:14', $t);
            }
        }
        $this->assertTrue($deductionFound, 'Noon break deduction should be present and limited');

        // Verify paid >= effective (never negative adjustment)
        $effectiveMinutes = $this->timeToMinutes($day['effective']);
        $paidMinutes = $this->timeToMinutes($day['paid']);
        $this->assertGreaterThanOrEqual($effectiveMinutes, $paidMinutes);
    }

    /**
     * Helper: Convert HH:MM to minutes
     */
    private function timeToMinutes(string $time): int
    {
        [$h, $m] = explode(':', $time);
        return (int)$h * 60 + (int)$m;
    }
}
