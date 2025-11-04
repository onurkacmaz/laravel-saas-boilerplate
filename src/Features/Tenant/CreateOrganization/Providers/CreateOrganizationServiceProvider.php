<?php

namespace Features\Tenant\CreateOrganization\Providers;

use Illuminate\Support\ServiceProvider;

class CreateOrganizationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');
    }
}
