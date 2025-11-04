<?php

namespace Features\Tenant\RemoveUserFromOrganization\Providers;

use Illuminate\Support\ServiceProvider;

class RemoveUserFromOrganizationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');
    }
}
