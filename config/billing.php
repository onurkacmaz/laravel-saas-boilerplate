<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Payment provider
    |--------------------------------------------------------------------------
    |
    | The provider paid plans are billed with: "stripe" (Laravel Cashier) or
    | "paddle" (Laravel Cashier Paddle). Free plans never reach a provider.
    | Pick one before the first paid subscription: subscriptions are not
    | moved from one provider to the other.
    |
    */

    'provider' => env('BILLING_PROVIDER', 'stripe'),

];
