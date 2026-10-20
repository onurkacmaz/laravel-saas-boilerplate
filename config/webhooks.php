<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Private Network Targets
    |--------------------------------------------------------------------------
    |
    | Outgoing webhooks are refused when their URL points to a private,
    | loopback, link-local or otherwise reserved address (SSRF protection).
    | Enable this only for local development (e.g. to reach a tunnel on
    | localhost).
    |
    */

    'allow_private_urls' => (bool) env('WEBHOOKS_ALLOW_PRIVATE_URLS', false),

    /*
    |--------------------------------------------------------------------------
    | Delivery
    |--------------------------------------------------------------------------
    */

    'user_agent' => env('WEBHOOKS_USER_AGENT', 'SaaS-Webhooks/1.0'),

    // Maximum number of bytes of the endpoint response stored per delivery.
    'max_response_body' => (int) env('WEBHOOKS_MAX_RESPONSE_BODY', 65535),

    // Seconds to wait between retries of a failed delivery (one entry per retry).
    'backoff' => [60, 300, 900, 3600],

    /*
    |--------------------------------------------------------------------------
    | Events
    |--------------------------------------------------------------------------
    |
    | Events emitted by the application. Endpoints may subscribe to any of
    | them, or to "*" for every event.
    |
    */

    'events' => [
        'organization.updated',
        'organization.deleted',
        'member.added',
        'member.removed',
        'member.role_updated',
        'invitation.sent',
        'invitation.accepted',
        'invitation.rejected',
        'invitation.canceled',
        'subscription.created',
        'subscription.updated',
        'subscription.canceled',
        'payment.succeeded',
        'payment.failed',
    ],

];
