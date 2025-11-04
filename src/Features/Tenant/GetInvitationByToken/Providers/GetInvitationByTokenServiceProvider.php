<?php

namespace Features\Tenant\GetInvitationByToken\Providers;

use Illuminate\Support\ServiceProvider;

class GetInvitationByTokenServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');
    }
}
