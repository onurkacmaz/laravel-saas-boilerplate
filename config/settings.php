<?php

return [
    'defaults' => [
        'timezone' => env('ORGANIZATION_SETTINGS_DEFAULT_TIMEZONE', 'UTC'),
        'locale' => env('ORGANIZATION_SETTINGS_DEFAULT_LOCALE', 'en'),
        'date_format' => env('ORGANIZATION_SETTINGS_DEFAULT_DATE_FORMAT', 'Y-m-d'),
        'time_format' => env('ORGANIZATION_SETTINGS_DEFAULT_TIME_FORMAT', 'H:i:s'),
        'currency' => env('ORGANIZATION_SETTINGS_DEFAULT_CURRENCY', 'USD'),
        'notification_preferences' => null,
        'feature_flags' => null,
    ],

    // Feature flags an organization can toggle in its settings. Applications
    // add their own flags here (or merge them in from a service provider).
    'feature_flags' => [
        'advanced_analytics',
        'api_access',
        'custom_branding',
    ],

    'invitations' => [
        // Number of days an invitation stays valid.
        'expire_days' => (int) env('INVITATION_EXPIRE_DAYS', 7),
    ],
];
