<?php

namespace Features\Tenant\GetOrganizations\Providers;

use Illuminate\Support\ServiceProvider;

class GetOrganizationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');
    }
}
