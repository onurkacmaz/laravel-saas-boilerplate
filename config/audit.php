<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Request Auditing
    |--------------------------------------------------------------------------
    |
    | When enabled, every successful state-changing API request (POST, PUT,
    | PATCH, DELETE) made by an authenticated user is recorded in the audit log.
    |
    */

    'enabled' => (bool) env('AUDIT_ENABLED', true),

];
