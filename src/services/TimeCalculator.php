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

            $totalMinutes += $dailyMinutes;
        }

        $h = floor($totalMinutes / 60);
        $m = $totalMinutes % 60;
        return sprintf("%02d:%02d", $h, $m);
    }
}
