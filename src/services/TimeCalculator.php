<?php

class TimeCalculator
{
    public function __construct(private array $config)
    {
    }

    /**
     * Merge multiple arrays of hours by day
     * @param array ...$arrays
     * @return array
     */
    public function mergeHoursByDay(array ...$arrays): array
    {
        $merged = [];

        foreach ($arrays as $timeSet) {
            if (!$timeSet) {
                continue;
            }

            foreach ($timeSet as $date => $hours) {
                // convert date format (23/12/2024 -> 23-12-2024)
                $cleanDate = str_replace('/', '-', $date);

                if (!isset($merged[$cleanDate])) {
                    $merged[$cleanDate] = [];
                }

                // clean hours (remove spaces and \u00a0 character)
                $cleanHours = array_map(function ($hour) {
                    return trim($hour, " \t\n\r\0\x0B\xC2\xA0");
                }, $hours);

                $merged[$cleanDate] = array_merge($merged[$cleanDate], $cleanHours);
                sort($merged[$cleanDate]);
            }
        }

        return $merged;
    }

    /**
     * Calculate total working hours
     * @param array $arrayData (returned by mergeHoursByDay)
     * @param int $pause (in minutes)
     * @return string
     */
    public function calculateTotalWorkingHours(array $arrayData, int $pause = 0): string
    {
        date_default_timezone_set('Europe/Paris');

        $totalMinutes = 0;
        $currentDate = date('d-m-Y');
        $currentTime = date('H:i');

        $startLimit = $this->config['start_limit_minutes'];
        $endLimit = $this->config['end_limit_minutes'];
        $noonBreakStart = $this->config['noon_break_start'];
        $noonBreakEnd = $this->config['noon_break_end'];
        $noonMinimumBreak = $this->config['noon_minimum_break'];

        foreach ($arrayData as $date => $hours) {
            $dailyMinutes = 0;
            $nbHours = count($hours);
            $morningPauseAdded = false;
            $afternoonPauseAdded = false;

            // Special case for the current day with an odd number of hours
            if ($date === $currentDate && $nbHours % 2 !== 0) {
                $start = explode(':', $hours[$nbHours - 1]);
                $end = explode(':', $currentTime);

                $startMinutes = max(min(intval($start[0]) * 60 + intval($start[1]), $endLimit), $startLimit);
                $endMinutes = max(min(intval($end[0]) * 60 + intval($end[1]), $endLimit), $startLimit);

                $plageMinutes = $endMinutes - $startMinutes;
                $dailyMinutes += $plageMinutes;

                // If we are after the morning break threshold, we add the morning break
                if ($endMinutes >= $this->config['morning_break_threshold'] && !$morningPauseAdded) {
                    $dailyMinutes += $pause;
                    $morningPauseAdded = true;
                }
                // If we are after the afternoon break threshold, we add the afternoon break
                if ($endMinutes >= $this->config['afternoon_break_threshold'] && !$afternoonPauseAdded) {
                    $dailyMinutes += $pause;
                    $afternoonPauseAdded = true;
                }
            }

            // Normal case
            for ($i = 0; $i < $nbHours - 1; $i += 2) {
                $start = explode(':', $hours[$i]);
                $end = explode(':', $hours[$i + 1]);

                $startMinutes = max(min(intval($start[0]) * 60 + intval($start[1]), $endLimit), $startLimit);
                $endMinutes = max(min(intval($end[0]) * 60 + intval($end[1]), $endLimit), $startLimit);

                $plageMinutes = $endMinutes - $startMinutes;
                $dailyMinutes += $plageMinutes;

                // Check for the morning break
                if ($endMinutes >= $this->config['morning_break_threshold'] && !$morningPauseAdded) {
                    $dailyMinutes += $pause;
                    $morningPauseAdded = true;
                }

                // Check for the afternoon break
                if ($endMinutes >= $this->config['afternoon_break_threshold'] && !$afternoonPauseAdded) {
                    $dailyMinutes += $pause;
                    $afternoonPauseAdded = true;
                }
            }

            // Apply noon minimum break rule (only when calculating paid hours)
            // The rule: if lunch break < 1h, remove the "gained" minutes from paid hours
            // But we can only remove up to the total bonus already added
            if ($pause > 0) {
                $noonBreakDuration = $this->calculateNoonBreak($hours, $noonBreakStart, $noonBreakEnd);
                if ($noonBreakDuration !== null && $noonBreakDuration < $noonMinimumBreak) {
                    // User took less than minimum break, so they "gained" time
                    // Calculate gained minutes
                    $gainedMinutes = $noonMinimumBreak - $noonBreakDuration;

                    // Calculate total bonus added for this day
                    $totalBonus = 0;
                    if ($morningPauseAdded) $totalBonus += $pause;
                    if ($afternoonPauseAdded) $totalBonus += $pause;

                    // Only deduct up to the bonus amount (can't make paid < effective)
                    $deduction = min($gainedMinutes, $totalBonus);
                    $dailyMinutes -= $deduction;
                }
                // If break >= minimum, no adjustment needed
            }

            $totalMinutes += $dailyMinutes;
        }

        $h = floor($totalMinutes / 60);
        $m = $totalMinutes % 60;
        return sprintf("%02d:%02d", $h, $m);
    }

    /**
     * Calculate noon break duration in minutes
     * Returns null if no break was taken during noon window
     * @param array $hours Array of times for the day
     * @param int $noonStart Start of noon window (in minutes)
     * @param int $noonEnd End of noon window (in minutes)
     * @return int|null
     */
    private function calculateNoonBreak(array $hours, int $noonStart, int $noonEnd): ?int
    {
        $nbHours = count($hours);

        // Need at least 2 pairs of times to have a break
        if ($nbHours < 3) {
            return null;
        }

        // Look for breaks that overlap with noon window (12h-14h)
        for ($i = 1; $i < $nbHours - 1; $i += 2) {
            $breakStart = explode(':', $hours[$i]);
            $breakEnd = explode(':', $hours[$i + 1]);

            $breakStartMinutes = intval($breakStart[0]) * 60 + intval($breakStart[1]);
            $breakEndMinutes = intval($breakEnd[0]) * 60 + intval($breakEnd[1]);

            // Check if this break overlaps with noon window
            if ($breakStartMinutes < $noonEnd && $breakEndMinutes > $noonStart) {
                // Calculate the overlap
                $overlapStart = max($breakStartMinutes, $noonStart);
                $overlapEnd = min($breakEndMinutes, $noonEnd);
                return $overlapEnd - $overlapStart;
            }
        }

        return null;
    }

    /**
     * Get week key from date (format: YYYY-w-WW)
     * @param string $date Format: dd-mm-yyyy
     * @return string Format: YYYY-w-WW
     */
    private function getWeekKey(string $date): string
    {
        $parts = explode('-', $date);
        $timestamp = mktime(0, 0, 0, (int)$parts[1], (int)$parts[0], (int)$parts[2]);
        $weekNumber = date('W', $timestamp);
        $year = date('o', $timestamp); // ISO-8601 year
        return sprintf('%s-w-%02d', $year, $weekNumber);
    }

    /**
     * Format minutes to HH:MM
     * @param int $minutes
     * @return string
     */
    private function formatMinutes(int $minutes): string
    {
        $h = floor($minutes / 60);
        $m = $minutes % 60;
        return sprintf("%02d:%02d", $h, $m);
    }

    /**
     * Calculate daily details for a single day
     * @param string $date
     * @param array $hours
     * @return array
     */
    private function calculateDailyDetails(string $date, array $hours): array
    {
        date_default_timezone_set('Europe/Paris');

        $pause = $this->config['pause_time'];
        $startLimit = $this->config['start_limit_minutes'];
        $endLimit = $this->config['end_limit_minutes'];
        $noonBreakStart = $this->config['noon_break_start'];
        $noonBreakEnd = $this->config['noon_break_end'];
        $noonMinimumBreak = $this->config['noon_minimum_break'];

        $currentDate = date('d-m-Y');
        $currentTime = date('H:i');

        $effectiveMinutes = 0;
        $nbHours = count($hours);
        $morningPauseAdded = false;
        $afternoonPauseAdded = false;

        $morningBreakMinutes = 0;
        $noonBreakMinutes = 0;
        $afternoonBreakMinutes = 0;

        // Calculate all breaks in the day
        for ($i = 1; $i < $nbHours - 1; $i += 2) {
            $breakStart = explode(':', $hours[$i]);
            $breakEnd = explode(':', $hours[$i + 1]);
            $breakStartMinutes = intval($breakStart[0]) * 60 + intval($breakStart[1]);
            $breakEndMinutes = intval($breakEnd[0]) * 60 + intval($breakEnd[1]);
            $breakDuration = $breakEndMinutes - $breakStartMinutes;

            // Classify break
            if ($breakStartMinutes < $noonBreakStart) {
                $morningBreakMinutes += $breakDuration;
            } elseif ($breakStartMinutes >= $noonBreakStart && $breakEndMinutes <= $noonBreakEnd) {
                $noonBreakMinutes += $breakDuration;
            } else {
                $afternoonBreakMinutes += $breakDuration;
            }
        }

        // Special case for current day with odd number of hours
        if ($date === $currentDate && $nbHours % 2 !== 0) {
            $start = explode(':', $hours[$nbHours - 1]);
            $end = explode(':', $currentTime);

            $startMinutes = max(min(intval($start[0]) * 60 + intval($start[1]), $endLimit), $startLimit);
            $endMinutes = max(min(intval($end[0]) * 60 + intval($end[1]), $endLimit), $startLimit);

            $effectiveMinutes += $endMinutes - $startMinutes;

            if ($endMinutes >= $this->config['morning_break_threshold'] && !$morningPauseAdded) {
                $morningPauseAdded = true;
            }
            if ($endMinutes >= $this->config['afternoon_break_threshold'] && !$afternoonPauseAdded) {
                $afternoonPauseAdded = true;
            }
        }

        // Calculate effective hours from work periods
        for ($i = 0; $i < $nbHours - 1; $i += 2) {
            $start = explode(':', $hours[$i]);
            $end = explode(':', $hours[$i + 1]);

            $startMinutes = max(min(intval($start[0]) * 60 + intval($start[1]), $endLimit), $startLimit);
            $endMinutes = max(min(intval($end[0]) * 60 + intval($end[1]), $endLimit), $startLimit);

            $effectiveMinutes += $endMinutes - $startMinutes;

            if ($endMinutes >= $this->config['morning_break_threshold'] && !$morningPauseAdded) {
                $morningPauseAdded = true;
            }
            if ($endMinutes >= $this->config['afternoon_break_threshold'] && !$afternoonPauseAdded) {
                $afternoonPauseAdded = true;
            }
        }

        // Calculate paid hours
        $paidMinutes = $effectiveMinutes;
        $effectiveToPaid = [];

        if ($morningPauseAdded) {
            $paidMinutes += $pause;
            $effectiveToPaid[] = sprintf("+ %s => morning break", $this->formatMinutes($pause));
        }
        if ($afternoonPauseAdded) {
            $paidMinutes += $pause;
            $effectiveToPaid[] = sprintf("+ %s => afternoon break", $this->formatMinutes($pause));
        }

        // Apply noon minimum break rule
        $noonBreakDuration = $this->calculateNoonBreak($hours, $noonBreakStart, $noonBreakEnd);
        if ($noonBreakDuration !== null && $noonBreakDuration < $noonMinimumBreak) {
            $gainedMinutes = $noonMinimumBreak - $noonBreakDuration;
            $totalBonus = 0;
            if ($morningPauseAdded) $totalBonus += $pause;
            if ($afternoonPauseAdded) $totalBonus += $pause;

            $deduction = min($gainedMinutes, $totalBonus);
            $paidMinutes -= $deduction;
            $effectiveToPaid[] = sprintf(
                "- %s => noon break => %s (minimum) - %s (noon break)",
                $this->formatMinutes($deduction),
                $this->formatMinutes($noonMinimumBreak),
                $this->formatMinutes($noonBreakDuration)
            );
        }

        return [
            'hours' => $hours,
            'breaks' => [
                'morning' => $this->formatMinutes($morningBreakMinutes),
                'noon' => $this->formatMinutes($noonBreakMinutes),
                'afternoon' => $this->formatMinutes($afternoonBreakMinutes)
            ],
            'effective_to_paid' => $effectiveToPaid,
            'effective' => $this->formatMinutes($effectiveMinutes),
            'paid' => $this->formatMinutes($paidMinutes)
        ];
    }

    /**
     * Calculate weekly data structure from merged hours
     * @param array $mergedHours Result from mergeHoursByDay()
     * @return array Weekly structure with days, breaks, and totals
     */
    public function calculateWeeklyData(array $mergedHours): array
    {
        $weeks = [];

        foreach ($mergedHours as $date => $hours) {
            $weekKey = $this->getWeekKey($date);

            if (!isset($weeks[$weekKey])) {
                $weeks[$weekKey] = [
                    'days' => [],
                    'total_effective' => 0,
                    'total_paid' => 0
                ];
            }

            $dailyDetails = $this->calculateDailyDetails($date, $hours);
            $weeks[$weekKey]['days'][$date] = $dailyDetails;

            // Add to weekly totals (convert HH:MM to minutes)
            $effectiveParts = explode(':', $dailyDetails['effective']);
            $paidParts = explode(':', $dailyDetails['paid']);

            $weeks[$weekKey]['total_effective'] += intval($effectiveParts[0]) * 60 + intval($effectiveParts[1]);
            $weeks[$weekKey]['total_paid'] += intval($paidParts[0]) * 60 + intval($paidParts[1]);
        }

        // Convert totals back to HH:MM format
        foreach ($weeks as $weekKey => $weekData) {
            $weeks[$weekKey]['total_effective'] = $this->formatMinutes($weekData['total_effective']);
            $weeks[$weekKey]['total_paid'] = $this->formatMinutes($weekData['total_paid']);
        }

        return $weeks;
    }
}
