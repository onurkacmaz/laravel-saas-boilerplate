<?php

namespace Features\Tenant\CancelInvitation\Providers;

use Illuminate\Support\ServiceProvider;

class CancelInvitationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');
    }
}
