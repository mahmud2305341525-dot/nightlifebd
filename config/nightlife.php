<?php

return [
    // false = AI analysis runs right after the HTTP response (no queue worker needed, best for demos)
    // true  = jobs go to the database queue; run `php artisan queue:work`
    'use_queue' => env('NIGHTLIFE_USE_QUEUE', false),

    // cosine similarity above which two reports are treated as the same incident
    'duplicate_threshold' => ['gemini' => 0.85, 'local' => 0.60],

    // routes more than this much longer than the fastest are never recommended
    'max_detour' => 1.30,

    // BDT estimate per person for each price tier (used by the Night Planner)
    'tier_cost' => [1 => 300, 2 => 700, 3 => 1500],

    'avg_speed_kmh' => 20, // Dhaka traffic, used for ETA to help points
];
