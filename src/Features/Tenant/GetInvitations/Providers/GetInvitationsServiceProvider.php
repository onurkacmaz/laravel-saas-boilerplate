<?php

namespace Features\Tenant\GetInvitations\Providers;

use Illuminate\Support\ServiceProvider;
use Infrastructure\Support\LoadsApiRoutes;

class GetInvitationsServiceProvider extends ServiceProvider
{
    use LoadsApiRoutes;

    public function boot(): void
    {
        $this->loadApiRoutesFrom(__DIR__.'/../Routes/api.php');
    }
}
