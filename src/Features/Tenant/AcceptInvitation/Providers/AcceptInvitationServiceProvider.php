<?php

namespace Features\Tenant\AcceptInvitation\Providers;

use Illuminate\Support\ServiceProvider;

class AcceptInvitationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');
    }
}
