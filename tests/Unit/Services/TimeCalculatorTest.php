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
}
