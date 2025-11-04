<?php

namespace Features\Tenant\AddUserToOrganization\Providers;

use Illuminate\Support\ServiceProvider;

class AddUserToOrganizationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');
    }
}
