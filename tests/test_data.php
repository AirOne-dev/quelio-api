<?php

/**
 * Test data for TimeCalculator tests
 */

return [
    'martin_real_case' => [
        'description' => 'Real case from Martin - 12-15 Jan 2026',
        'hours' => [
            "12-01-2026" => [
                "08:30",
                "10:43",
                "10:47",
                "12:02",
                "13:01",
                "17:50"
            ],
            "13-01-2026" => [
                "08:30",
                "10:35",
                "10:46",
                "12:16",
                "13:19",
                "15:41",
                "15:50",
                "17:39"
            ],
            "14-01-2026" => [
                "08:30",
                "10:40",
                "10:47",
                "12:02",
                "13:02",
                "17:08"
            ],
            "15-01-2026" => [
                "08:31",
                "12:10",
                "13:13"
            ]
        ],
        'expected_effective' => null, // Will vary based on current time for 15-01
        'expected_paid' => null, // Should be >= effective
    ],

    'single_day_short_lunch' => [
        'description' => 'Single day with 47min lunch break (< 1h)',
        'hours' => [
            '15-01-2026' => ['08:30', '12:00', '12:47', '18:30']
        ],
        'expected_effective' => '09:13', // 3h30 + 5h43 = 9h13
        'expected_paid' => '09:14', // 9h13 + 14min (2x7) - 13min (gained) = 9h14
    ],

    'single_day_exact_lunch' => [
        'description' => 'Single day with exactly 1h lunch',
        'hours' => [
            '15-01-2026' => ['08:30', '12:00', '13:00', '18:30']
        ],
        'expected_effective' => '09:00', // 3h30 + 5h30 = 9h00
        'expected_paid' => '09:14', // 9h00 + 14min - 0 gained = 9h14
    ],

    'single_day_long_lunch' => [
        'description' => 'Single day with 1h30 lunch (> 1h minimum)',
        'hours' => [
            '15-01-2026' => ['08:30', '12:00', '13:30', '18:30']
        ],
        'expected_effective' => '08:30', // 3h30 + 5h00 = 8h30
        'expected_paid' => '08:44', // 8h30 + 14min - 0 gained = 8h44
    ],

    'single_day_no_lunch' => [
        'description' => 'Single day without lunch break',
        'hours' => [
            '15-01-2026' => ['08:30', '18:30']
        ],
        'expected_effective' => '10:00',
        'expected_paid' => '10:14', // 10h00 + 14min, no lunch deduction
    ],

    'single_day_lunch_outside_window' => [
        'description' => 'Lunch break outside 12h-14h window',
        'hours' => [
            '15-01-2026' => ['08:30', '11:00', '11:30', '18:30']
        ],
        'expected_effective' => '09:30',
        'expected_paid' => '09:44', // 9h30 + 14min, no noon break rule
    ],

    'multiple_breaks_one_during_noon' => [
        'description' => 'Multiple breaks, one during noon window',
        'hours' => [
            '15-01-2026' => [
                '08:30', '10:00', // Morning work
                '10:15', '12:00', // Continue morning
                '12:30', '18:30'  // Afternoon (30min lunch)
            ]
        ],
        'expected_effective' => '09:15', // 1h30 + 1h45 + 6h00 = 9h15
        'expected_paid' => '09:15', // 9h15 + 14min - 14min (can only deduct up to bonus) = 9h15
    ],
];
