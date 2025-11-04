<?php

namespace Features\Compliance\GdprExport\Providers;

use Illuminate\Support\ServiceProvider;

class GdprExportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');
    }
}
