<?php

namespace Features\Tenant\SendInvitation\Providers;

use Features\Tenant\SendInvitation\Events\InvitationSent;
use Features\Tenant\SendInvitation\Listeners\SendInvitationEmail;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Infrastructure\Support\LoadsApiRoutes;

class SendInvitationServiceProvider extends ServiceProvider
{
    use LoadsApiRoutes;

    public function boot(): void
    {
        $this->loadApiRoutesFrom(__DIR__.'/../Routes/api.php');

        Event::listen(InvitationSent::class, SendInvitationEmail::class);
    }
}
