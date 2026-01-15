<?php

/**
 * Tests for TimeCalculator service
 */

require_once __DIR__ . '/../src/services/TimeCalculator.php';

class TimeCalculatorTest
{
    private TimeCalculator $calculator;
    private array $config;
    private int $passed = 0;
    private int $failed = 0;
    private array $failures = [];

    public function __construct()
    {
        $this->config = [
            'start_limit_minutes' => 8 * 60 + 30, // 8h30
            'end_limit_minutes' => 18 * 60 + 30, // 18h30
            'morning_break_threshold' => 11 * 60, // 11h00
            'afternoon_break_threshold' => 16 * 60, // 16h00
            'noon_minimum_break' => 60, // 1 heure
            'noon_break_start' => 12 * 60, // 12h00
            'noon_break_end' => 14 * 60, // 14h00
        ];

        $this->calculator = new TimeCalculator($this->config);
    }

    public function run(): int
    {
        echo "\n" . str_repeat("=", 80) . "\n";
        echo "TimeCalculator Test Suite\n";
        echo str_repeat("=", 80) . "\n\n";

        $testData = require __DIR__ . '/test_data.php';

        foreach ($testData as $testName => $test) {
            $this->runTest($testName, $test);
        }

        $this->printSummary();

        return $this->failed > 0 ? 1 : 0;
    }

    private function runTest(string $testName, array $test): void
    {
        echo "Test: {$test['description']}\n";
        echo str_repeat("-", 80) . "\n";

        // Display input data
        echo "Input hours:\n";
        foreach ($test['hours'] as $date => $hours) {
            echo "  $date: " . json_encode($hours) . "\n";
        }
        echo "\n";

        // Calculate effective hours (pause = 0)
        $effective = $this->calculator->calculateTotalWorkingHours($test['hours'], 0);

        // Calculate paid hours (pause = 7 minutes per break period)
        $paid = $this->calculator->calculateTotalWorkingHours($test['hours'], 7);

        echo "Results:\n";
        echo "  Effective hours: $effective";
        if ($test['expected_effective']) {
            echo " (expected: {$test['expected_effective']})";
        }
        echo "\n";

        echo "  Paid hours:      $paid";
        if ($test['expected_paid']) {
            echo " (expected: {$test['expected_paid']})";
        }
        echo "\n\n";

        // Validate results
        $effectivePass = !$test['expected_effective'] || $effective === $test['expected_effective'];
        $paidPass = !$test['expected_paid'] || $paid === $test['expected_paid'];

        // Check that paid >= effective (critical validation)
        $paidMinutes = $this->timeToMinutes($paid);
        $effectiveMinutes = $this->timeToMinutes($effective);
        $logicalPass = $paidMinutes >= $effectiveMinutes;

        if (!$logicalPass) {
            echo "  ⚠️  CRITICAL: Paid hours ($paid) < Effective hours ($effective)\n";
            echo "      Difference: -" . $this->minutesToTime($effectiveMinutes - $paidMinutes) . "\n";
        }

        $allPass = $effectivePass && $paidPass && $logicalPass;

        if ($allPass) {
            echo "✓ PASS\n";
            $this->passed++;
        } else {
            echo "✗ FAIL\n";
            $this->failed++;
            $this->failures[] = [
                'test' => $testName,
                'description' => $test['description'],
                'effective' => [
                    'actual' => $effective,
                    'expected' => $test['expected_effective'],
                    'pass' => $effectivePass
                ],
                'paid' => [
                    'actual' => $paid,
                    'expected' => $test['expected_paid'],
                    'pass' => $paidPass
                ],
                'logical' => $logicalPass
            ];
        }

        echo "\n";
    }

    private function printSummary(): void
    {
        echo str_repeat("=", 80) . "\n";
        echo "Test Summary\n";
        echo str_repeat("=", 80) . "\n";
        echo "Passed: {$this->passed}\n";
        echo "Failed: {$this->failed}\n";
        echo "Total:  " . ($this->passed + $this->failed) . "\n";

        if (!empty($this->failures)) {
            echo "\nFailure Details:\n";
            echo str_repeat("-", 80) . "\n";
            foreach ($this->failures as $failure) {
                echo "❌ {$failure['description']}\n";

                if (!$failure['effective']['pass']) {
                    echo "   Effective: {$failure['effective']['actual']} (expected: {$failure['effective']['expected']})\n";
                }

                if (!$failure['paid']['pass']) {
                    echo "   Paid: {$failure['paid']['actual']} (expected: {$failure['paid']['expected']})\n";
                }

                if (!$failure['logical']) {
                    echo "   ⚠️  Paid < Effective (logic error)\n";
                }

                echo "\n";
            }
        }

        echo str_repeat("=", 80) . "\n";
    }

    private function timeToMinutes(string $time): int
    {
        $parts = explode(':', $time);
        return intval($parts[0]) * 60 + intval($parts[1]);
    }

    private function minutesToTime(int $minutes): string
    {
        $h = floor($minutes / 60);
        $m = $minutes % 60;
        return sprintf("%02d:%02d", $h, $m);
    }
}

// Run tests
$test = new TimeCalculatorTest();
exit($test->run());
