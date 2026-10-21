<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | Addresses of the proxies in front of the API: a load balancer, a CDN, or
    | a frontend server that forwards API calls. Requests from them may set
    | X-Forwarded-For (and -Host, -Port, -Proto), so the client's own address
    | is what rate limits, the IP allow-list, security.check and audit logs
    | see. A comma-separated list of IPs or CIDR ranges, or "*" to trust the
    | address that connects (only when nothing else can reach the API).
    | Empty trusts nobody: X-Forwarded-* headers are ignored.
    |
    */

    'proxies' => env('TRUSTED_PROXIES') ?: null,

];
