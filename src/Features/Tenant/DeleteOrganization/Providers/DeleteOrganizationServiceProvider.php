<?php

namespace Features\Tenant\DeleteOrganization\Providers;

use Illuminate\Support\ServiceProvider;

class DeleteOrganizationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');
    }
}
