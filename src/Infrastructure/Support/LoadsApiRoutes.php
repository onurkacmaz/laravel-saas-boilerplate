<?php

namespace Infrastructure\Support;

use Illuminate\Contracts\Foundation\CachesRoutes;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Loads a feature's route file inside the "api" middleware group so every
 * feature endpoint gets the same baseline middleware (bindings, security
 * headers, auditing...), while staying compatible with "route:cache".
 *
 * @mixin ServiceProvider
 */
trait LoadsApiRoutes
{
    protected function loadApiRoutesFrom(string $path): void
    {
        if ($this->app instanceof CachesRoutes && $this->app->routesAreCached()) {
            return;
        }

        Route::middleware('api')->group($path);

        $this->app->booted(function () {
            $this->app['router']->getRoutes()->refreshNameLookups();
            $this->app['router']->getRoutes()->refreshActionLookups();
        });
    }
}
