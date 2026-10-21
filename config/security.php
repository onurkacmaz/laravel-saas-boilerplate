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

    /*
    |--------------------------------------------------------------------------
    | Private Network Targets
    |--------------------------------------------------------------------------
    |
    | Requests to user-supplied URLs (webhooks, and your own features through
    | UrlGuard and the SafeUrl rule) are refused when the URL points to a
    | private, loopback, link-local or otherwise reserved address (SSRF
    | protection). Enable this only for local development (e.g. to reach a
    | tunnel on localhost). WEBHOOKS_ALLOW_PRIVATE_URLS is its former name.
    |
    */

    'allow_private_urls' => (bool) env('SECURITY_ALLOW_PRIVATE_URLS', env('WEBHOOKS_ALLOW_PRIVATE_URLS', false)),

];
