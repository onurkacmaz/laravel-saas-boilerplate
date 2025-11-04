<?php

use Features\Subscription\Infrastructure\Middleware\CheckResourceLimit;
use Features\Subscription\Infrastructure\Middleware\PlanBasedRateLimitMiddleware;
use Features\Subscription\Infrastructure\Middleware\RequireActiveSubscription;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Infrastructure\Security\Middleware\CheckSuspiciousActivity;
use Infrastructure\Security\Middleware\IpWhitelist;
use Infrastructure\Security\Middleware\SecurityHeaders;
use Infrastructure\Tenant\Middleware\RequireTenant;
use Infrastructure\Tenant\Middleware\SetTenantContext;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(append: [
            SecurityHeaders::class,
        ]);

        $middleware->alias([
            'tenant.context' => SetTenantContext::class,
            'tenant.required' => RequireTenant::class,
            'subscription.required' => RequireActiveSubscription::class,
            'limit' => CheckResourceLimit::class,
            'rate.limit' => PlanBasedRateLimitMiddleware::class,
            'security.check' => CheckSuspiciousActivity::class,
            'ip.whitelist' => IpWhitelist::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
