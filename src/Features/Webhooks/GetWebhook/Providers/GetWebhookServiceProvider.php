<?php

namespace Features\Webhooks\GetWebhook\Providers;

use Illuminate\Support\ServiceProvider;
use Infrastructure\Support\LoadsApiRoutes;

class GetWebhookServiceProvider extends ServiceProvider
{
    use LoadsApiRoutes;

    public function boot(): void
    {
        $this->loadApiRoutesFrom(__DIR__.'/../Routes/api.php');
    }
}
