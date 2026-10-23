<?php

namespace Features\Subscription;

use Features\Subscription\Cancel\Providers\CancelServiceProvider;
use Features\Subscription\GetLimits\Providers\GetLimitsServiceProvider;
use Features\Subscription\GetPlan\Providers\GetPlanServiceProvider;
use Features\Subscription\GetPlans\Providers\GetPlansServiceProvider;
use Features\Subscription\GetSubscription\Providers\GetSubscriptionServiceProvider;
use Features\Subscription\GetUsage\Providers\GetUsageServiceProvider;
use Features\Subscription\OpenBillingPortal\Providers\OpenBillingPortalServiceProvider;
use Features\Subscription\Shared\Commands\SendTrialEndingNotifications;
use Features\Subscription\Shared\Listeners\DispatchOrganizationSubscriptionChanged;
use Features\Subscription\StripeWebhook\Providers\StripeWebhookServiceProvider;
use Features\Subscription\Subscribe\Providers\SubscribeServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class SubscriptionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->loadMigrationsFrom([
            __DIR__.'/../../Features/Subscription/Infrastructure/Eloquent/Migrations',
        ]);

        $this->app->register(GetPlanServiceProvider::class);
        $this->app->register(GetPlansServiceProvider::class);
        $this->app->register(GetUsageServiceProvider::class);
        $this->app->register(GetLimitsServiceProvider::class);
        $this->app->register(GetSubscriptionServiceProvider::class);
        $this->app->register(SubscribeServiceProvider::class);
        $this->app->register(CancelServiceProvider::class);
        $this->app->register(OpenBillingPortalServiceProvider::class);
        $this->app->register(StripeWebhookServiceProvider::class);
    }

    public function boot(): void
    {
        Event::subscribe(DispatchOrganizationSubscriptionChanged::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                SendTrialEndingNotifications::class,
            ]);
        }
    }
}
