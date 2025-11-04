<?php

namespace Features\Compliance\GdprDownload\Providers;

use Illuminate\Support\ServiceProvider;

class GdprDownloadServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');
    }
}
