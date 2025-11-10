<?php

namespace Features\Subscription\Subscribe\Providers;

use Features\Subscription\Infrastructure\Eloquent\Models\Plan;
use Features\Subscription\Shared\Events\SubscriptionCreated;
use Features\Subscription\Shared\Listeners\SendSubscriptionCreatedNotification;
use Features\Subscription\Subscribe\Application\Subscribe;
use Features\Subscription\Subscribe\Application\SubscribeHandler;
use Features\Tenant\CreateOrganization\Events\OrganizationCreated;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class SubscribeServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');

        Event::listen(SubscriptionCreated::class, SendSubscriptionCreatedNotification::class);
        Event::listen(OrganizationCreated::class, static function (OrganizationCreated $event): void {
            $planId = Plan::query()
                ->where('is_active', true)
                ->where('price', 0)
                ->value('id');

            if (! $planId) {
                return;
            }

            app(SubscribeHandler::class)(
                command: new Subscribe(
                    planId: $planId,
                    useTrial: true,
                    quantity: 1,
                    organization: $event->organization
                )
            );
        });
    }
}
