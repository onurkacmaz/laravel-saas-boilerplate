<?php

namespace Features\Tenant\GetOrganization\Providers;

use Illuminate\Support\ServiceProvider;

class GetOrganizationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');
    }
}
