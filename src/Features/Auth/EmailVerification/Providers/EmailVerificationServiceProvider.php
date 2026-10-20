<?php

namespace Features\Auth\EmailVerification\Providers;

use Illuminate\Support\ServiceProvider;
use Infrastructure\Support\LoadsApiRoutes;

class EmailVerificationServiceProvider extends ServiceProvider
{
    use LoadsApiRoutes;

    public function boot(): void
    {
        $this->loadApiRoutesFrom(__DIR__.'/../Routes/api.php');
    }
}
