<?php

namespace Features\Tenant\UpdateOrganizationSettings\Provider;

use Illuminate\Support\ServiceProvider;

class UpdateOrganizationSettingsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');
    }
}
