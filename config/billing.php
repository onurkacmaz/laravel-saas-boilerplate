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

    /*
    |--------------------------------------------------------------------------
    | Payment grace period
    |--------------------------------------------------------------------------
    |
    | Days a paid subscription keeps its plan after a renewal payment fails
    | (it is "past due"), while the provider retries the payment and the
    | customer updates their card. Limits, features and premium routes stay
    | as they were; once the days are over the organization falls back to
    | the free limits until the payment goes through. 0 drops the plan as
    | soon as a payment fails.
    |
    */

    'past_due_grace_days' => (int) env('BILLING_PAST_DUE_GRACE_DAYS', 7),

];
