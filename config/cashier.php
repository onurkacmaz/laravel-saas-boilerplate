<?php

use App\Models\Organization;

return [
    /*
    |--------------------------------------------------------------------------
    | Billable Model
    |--------------------------------------------------------------------------
    |
    | This option defines which model should be used to represent billable
    | entities when interacting with Stripe. By default, this value is
    | pulled from the CASHIER_MODEL environment variable or falls back
    | to the Organization domain model in this project.
    |
    */

    'model' => env('CASHIER_MODEL', Organization::class),

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    |
    | Here you may configure the default currency to be used when generating
    | invoices or creating subscriptions. You may override the locale that
    | is used when formatting monetary amounts on generated documents.
    |
    */

    'currency' => env('CASHIER_CURRENCY', 'usd'),

    'currency_locale' => env('CASHIER_CURRENCY_LOCALE', 'en_US'),

    /*
    |--------------------------------------------------------------------------
    | Cashier Path
    |--------------------------------------------------------------------------
    |
    | This is the base URI where Cashier's webhook controller will live. You
    | are free to modify this path as needed, but make sure Stripe's webhook
    | configuration is updated accordingly so that events reach your app.
    |
    */

    'path' => env('CASHIER_PATH', 'stripe'),

    /*
    |--------------------------------------------------------------------------
    | Stripe Webhooks
    |--------------------------------------------------------------------------
    |
    | Cashier is capable of processing Stripe webhooks. If you plan to make
    | use of this functionality you should configure the webhook endpoint
    | name, signing secret, and tolerance window in seconds below.
    |
    */

    'webhook' => [
        'path' => env('CASHIER_WEBHOOK', 'stripe/webhook'),

        'secret' => env('STRIPE_WEBHOOK_SECRET'),

        'tolerance' => env('STRIPE_WEBHOOK_TOLERANCE', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Invoice Customization
    |--------------------------------------------------------------------------
    |
    | Cashier allows adding arbitrary data to the generated Stripe invoices.
    | You may place any array values in this section that should be passed
    | to the Stripe API when invoices are created for your subscribers.
    |
    */

    'invoice_data' => [
        'metadata' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Logger Channel
    |--------------------------------------------------------------------------
    |
    | If you would like Cashier to log Stripe API interactions or webhook
    | handling, you may specify the log channel here. Leave this value
    | null to disable logging of Cashier specific events entirely.
    |
    */

    'logger' => env('CASHIER_LOGGER'),
];
