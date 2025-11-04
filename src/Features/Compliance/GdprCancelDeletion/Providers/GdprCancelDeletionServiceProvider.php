<?php

namespace Features\Compliance\GdprCancelDeletion\Providers;

use Illuminate\Support\ServiceProvider;

class GdprCancelDeletionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');
    }
}
