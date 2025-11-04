<?php

namespace Features\Tenant\GetInvitations\Providers;

use Illuminate\Support\ServiceProvider;

class GetInvitationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');
    }
}
