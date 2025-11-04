<?php

namespace Features\Tenant\UpdateUserRoleOnOrganization\Providers;

use Illuminate\Support\ServiceProvider;

class UpdateUserRoleOnOrganizationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');
    }
}
