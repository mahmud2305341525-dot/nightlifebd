<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Demo Mode
    |--------------------------------------------------------------------------
    | Dedicated configuration setting to enable demo routes, presentation hubs,
    | and scenario simulation. Defaults strictly to FALSE.
    | Never automatically enabled by local environment or APP_DEBUG alone.
    */
    'demo_enabled' => (bool) env('NIGHTLIFE_DEMO_ENABLED', false),
    'use_queue' => (bool) env('NIGHTLIFE_USE_QUEUE', false),
    'ai_enabled' => (bool) env('NIGHTLIFE_AI_ENABLED', true),
    'ai_circuit_failures' => (int) env('NIGHTLIFE_AI_CIRCUIT_FAILURES', 3),
    'ai_circuit_minutes' => (int) env('NIGHTLIFE_AI_CIRCUIT_MINUTES', 10),
    'duplicate_vector_store' => env('NIGHTLIFE_DUPLICATE_VECTOR_STORE', 'sql'),
    'require_real_mail' => (bool) env('NIGHTLIFE_REQUIRE_REAL_MAIL', false),
    'require_sms' => (bool) env('NIGHTLIFE_REQUIRE_SMS', false),
    'payments_driver' => env('NIGHTLIFE_PAYMENTS_DRIVER', 'mock'),
    'routing_driver' => env('NIGHTLIFE_ROUTING_DRIVER', 'ors'),

    /*
    |--------------------------------------------------------------------------
    | Queue Mode
    |--------------------------------------------------------------------------
    | false = AI analysis runs synchronously after the HTTP response
    |         (no queue worker needed — best for demos and local dev)
    | true  = Jobs go to the database queue; run `php artisan queue:work`
    */
    'use_queue' => (bool) env('NIGHTLIFE_USE_QUEUE', false),

    /*
    |--------------------------------------------------------------------------
    | Duplicate Detection Thresholds
    |--------------------------------------------------------------------------
    | Cosine similarity above which two reports are treated as the same incident.
    | Gemini embeddings are higher quality so their threshold is tighter.
    */
    'duplicate_threshold' => ['gemini' => 0.85, 'local' => 0.60],

    /*
    |--------------------------------------------------------------------------
    | Route Detour Limit
    |--------------------------------------------------------------------------
    | Routes more than this fraction longer than the fastest are never
    | recommended as the "safest route". 1.30 = 30% longer.
    */
    'max_detour' => env('NIGHTLIFE_MAX_DETOUR', 1.30),

    /*
    |--------------------------------------------------------------------------
    | Active Safety Tool Limits
    |--------------------------------------------------------------------------
    | Maximum active running safety timers and concurrent live trips permitted
    | per user to prevent notification spam and telemetry desynchronization.
    */
    'max_active_timers' => (int) env('NIGHTLIFE_MAX_ACTIVE_TIMERS', 1),
    'max_active_trips' => (int) env('NIGHTLIFE_MAX_ACTIVE_TRIPS', 1),

    /*
    |--------------------------------------------------------------------------
    | Night Planner — Price Tier Costs (BDT per person per stop)
    |--------------------------------------------------------------------------
    | 1 = budget venues, 2 = mid-range, 3 = premium.
    | Used by NightPlannerService to estimate trip budgets.
    */
    'tier_cost' => [1 => 300, 2 => 700, 3 => 1500],

    /*
    |--------------------------------------------------------------------------
    | Average Traffic Speed for ETA Calculation
    |--------------------------------------------------------------------------
    | Used by SafetyController::nearest() to estimate travel time to
    | the nearest police station / hospital / pharmacy in Dhaka traffic.
    */
    'avg_speed_kmh' => env('NIGHTLIFE_AVG_SPEED_KMH', 20),

    /*
    |--------------------------------------------------------------------------
    | Safety Score Bucket Adjustments
    |--------------------------------------------------------------------------
    | Added to the base score per time bucket (documented here for reference —
    | the live values live in SafetyScoreService::BUCKET_ADJUST).
    | day => +10, evening => 0, night => -5, late => -10
    */

    /*
    |--------------------------------------------------------------------------
    | Dependency Availability Summary (for demo / README reference)
    |--------------------------------------------------------------------------
    | GEMINI_API_KEY   → LLM analysis, embeddings, planner, venue coach
    |                    Without it: LocalAnalyzer keyword fallback
    | ORS_API_KEY      → OpenRouteService alternatives
    |                    Without it: public OSRM demo server
    | TELEGRAM_BOT_TOKEN → Telegram SOS alerts
    |                    Without it: email-only (log driver)
    | APP_URL (tunnel) → Live trip links work for contacts
    |                    Without it: trip links only work on same LAN
    */
];
