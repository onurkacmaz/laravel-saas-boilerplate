<?php

namespace Features\Tenant\GetOrganizationSettings\Provider;

use Illuminate\Support\ServiceProvider;

class GetOrganizationSettingsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');
    }
}
