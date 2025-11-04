<?php

namespace Features\Tenant\SendInvitation\Providers;

use Features\Tenant\SendInvitation\Events\InvitationSent;
use Features\Tenant\SendInvitation\Listeners\SendInvitationEmail;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class SendInvitationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');

        Event::listen(InvitationSent::class, SendInvitationEmail::class);
    }
}
