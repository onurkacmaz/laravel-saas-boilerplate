<?php

namespace Features\Tenant\CreateOrganization\Providers;

use Features\Auth\Register\Events\UserRegisterSucceeded;
use Features\Tenant\CreateOrganization\Application\CreateOrganization;
use Features\Tenant\CreateOrganization\Application\CreateOrganizationHandler;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Infrastructure\Support\LoadsApiRoutes;

class CreateOrganizationServiceProvider extends ServiceProvider
{
    use LoadsApiRoutes;

    public function boot(): void
    {
        $this->loadApiRoutesFrom(__DIR__.'/../Routes/api.php');

        Event::listen(
            events: UserRegisterSucceeded::class,
            listener: fn (UserRegisterSucceeded $event) => app(CreateOrganizationHandler::class)(
                command: new CreateOrganization(
                    userId: $event->user->id,
                    name: Str::limit(sprintf("%s's Organization", $event->user->name), 255, '')
                )
            )
        );
    }
}
