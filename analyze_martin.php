<?php
// Analyze Martin's data manually
$data = [
    "12-01-2026" => ["08:30", "10:43", "10:47", "12:02", "13:01", "17:50"],
    "13-01-2026" => ["08:30", "10:35", "10:46", "12:16", "13:19", "15:41", "15:50", "17:39"],
    "14-01-2026" => ["08:30", "10:40", "10:47", "12:02", "13:02", "17:08"],
    "15-01-2026" => ["08:31", "12:10", "13:13"]
];

function timeToMin($time) {
    list($h, $m) = explode(':', $time);
    return $h * 60 + $m;
}

$totalEffective = 0;

foreach ($data as $date => $hours) {
    echo "$date:\n";
    $dayEffective = 0;
    for ($i = 0; $i < count($hours) - 1; $i += 2) {
        $start = $hours[$i];
        $end = $hours[$i+1] ?? 'now';
        $minutes = timeToMin($end) - timeToMin($start);
        $dayEffective += $minutes;
        echo "  $start - $end: {$minutes}min\n";
    }

    // Show breaks
    for ($i = 1; $i < count($hours) - 1; $i += 2) {
        $breakStart = $hours[$i];
        $breakEnd = $hours[$i+1];
        $breakMin = timeToMin($breakEnd) - timeToMin($breakStart);
        echo "  Break: $breakStart - $breakEnd: {$breakMin}min";

        // Check if in noon window (12:00-14:00)
        $bStart = timeToMin($breakStart);
        $bEnd = timeToMin($breakEnd);
        if ($bStart < 14*60 && $bEnd > 12*60) {
            $overlapStart = max($bStart, 12*60);
            $overlapEnd = min($bEnd, 14*60);
            $overlap = $overlapEnd - $overlapStart;
            echo " (overlaps noon: {$overlap}min)";
        }
        echo "\n";
    }

    $h = floor($dayEffective / 60);
    $m = $dayEffective % 60;
    echo "  Day total: {$h}h{$m}min\n\n";
    $totalEffective += $dayEffective;
}

$h = floor($totalEffective / 60);
$m = $totalEffective % 60;
echo "Total effective: {$h}h{$m}min\n";
