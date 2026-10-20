<?php

namespace Features\Subscription\StripeWebhook\Providers;

use Features\Subscription\Shared\Events\PaymentFailed;
use Features\Subscription\Shared\Events\PaymentSucceeded;
use Features\Subscription\Shared\Events\SubscriptionUpgraded;
use Features\Subscription\Shared\Listeners\SendPaymentFailedNotification;
use Features\Subscription\Shared\Listeners\SendPaymentSucceededNotification;
use Features\Subscription\Shared\Listeners\SendSubscriptionUpgradedNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Infrastructure\Support\LoadsApiRoutes;

class StripeWebhookServiceProvider extends ServiceProvider
{
    use LoadsApiRoutes;

    public function boot(): void
    {
        $this->loadApiRoutesFrom(__DIR__.'/../Routes/api.php');

        Event::listen(PaymentSucceeded::class, SendPaymentSucceededNotification::class);
        Event::listen(PaymentFailed::class, SendPaymentFailedNotification::class);
        Event::listen(SubscriptionUpgraded::class, SendSubscriptionUpgradedNotification::class);
    }
}
