<?php

namespace Features\Tenant\RejectInvitation\Providers;

use Illuminate\Support\ServiceProvider;

class RejectInvitationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');
    }
}
