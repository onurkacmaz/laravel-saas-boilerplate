<?php

namespace Features\Tenant\UpdateOrganization\Providers;

use Illuminate\Support\ServiceProvider;

class UpdateOrganizationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');
    }
}
