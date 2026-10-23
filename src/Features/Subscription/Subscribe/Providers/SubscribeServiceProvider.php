<?php

namespace Features\Subscription\Subscribe\Providers;

use Features\Subscription\Shared\Events\SubscriptionCreated;
use Features\Subscription\Shared\Listeners\SendSubscriptionCreatedNotification;
use Features\Subscription\Subscribe\Application\AssignFreePlan;
use Features\Subscription\Subscribe\Commands\AssignFreePlanCommand;
use Features\Tenant\CreateOrganization\Events\OrganizationCreated;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Infrastructure\Support\LoadsApiRoutes;

class SubscribeServiceProvider extends ServiceProvider
{
    use LoadsApiRoutes;

    public function boot(): void
    {
        $this->loadApiRoutesFrom(__DIR__.'/../Routes/api.php');

        Event::listen(SubscriptionCreated::class, SendSubscriptionCreatedNotification::class);
        Event::listen(OrganizationCreated::class, static function (OrganizationCreated $event): void {
            app(AssignFreePlan::class)($event->organization);
        });

        if ($this->app->runningInConsole()) {
            $this->commands([
                AssignFreePlanCommand::class,
            ]);
        }
    }
}
