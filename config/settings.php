<?php

return [
    'defaults' => [
        'timezone' => env('ORGANIZATION_SETTINGS_DEFAULT_TIMEZONE', 'Europe/Istanbul'),
        'locale' => env('ORGANIZATION_SETTINGS_DEFAULT_LOCALE', 'tr'),
        'date_format' => env('ORGANIZATION_SETTINGS_DEFAULT_DATE_FORMAT', 'Y-m-d'),
        'time_format' => env('ORGANIZATION_SETTINGS_DEFAULT_TIME_FORMAT', 'H:i:s'),
        'currency' => env('ORGANIZATION_SETTINGS_DEFAULT_CURRENCY', 'TRY'),
        'notification_preferences' => null,
        'feature_flags' => null,
    ],
];
