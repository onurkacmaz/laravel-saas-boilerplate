<?php

namespace Features\Tenant\CreateOrganization\Providers;

use Features\Auth\Register\Events\UserRegisterSucceeded;
use Features\Tenant\CreateOrganization\Application\CreateOrganization;
use Features\Tenant\CreateOrganization\Application\CreateOrganizationHandler;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class CreateOrganizationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');

        Event::listen(
            events: UserRegisterSucceeded::class,
            listener: fn (UserRegisterSucceeded $event) => app(CreateOrganizationHandler::class)(
                command: new CreateOrganization(
                    userId: $event->user->id,
                    name: sprintf("%s's Organization", $event->user->name)
                )
            )
        );
    }
}
