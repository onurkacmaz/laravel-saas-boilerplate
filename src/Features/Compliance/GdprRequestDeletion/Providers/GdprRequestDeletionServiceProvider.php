<?php

namespace Features\Compliance\GdprRequestDeletion\Providers;

use Illuminate\Support\ServiceProvider;

class GdprRequestDeletionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');
    }
}
