<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Suspicious Activity (security.check middleware)
    |--------------------------------------------------------------------------
    |
    | Requests above the per-IP rate, and requests with suspicious payloads,
    | count as violations; an IP with too many violations within the window
    | is blocked. A route can raise the rate for itself, e.g.
    | ->middleware('security.check:600'); 0 disables the rate check.
    |
    */

    'suspicious_activity' => [
        'max_requests_per_minute' => (int) env('SECURITY_MAX_REQUESTS_PER_MINUTE', 100),
        'max_violations' => (int) env('SECURITY_MAX_VIOLATIONS', 5),
        'violation_window_minutes' => (int) env('SECURITY_VIOLATION_WINDOW_MINUTES', 60),
        'block_minutes' => (int) env('SECURITY_BLOCK_MINUTES', 1440),
    ],

];
