<?php

use Features\Audit\Infrastructure\Middleware\AuditMiddleware;
use Features\Subscription\Infrastructure\Middleware\CheckResourceLimit;
use Features\Subscription\Infrastructure\Middleware\PlanBasedRateLimitMiddleware;
use Features\Subscription\Infrastructure\Middleware\RequireActiveSubscription;
use Features\Subscription\Infrastructure\Middleware\RequirePlanFeature;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Infrastructure\Localization\Middleware\SetLocale;
use Infrastructure\Security\Middleware\CheckSuspiciousActivity;
use Infrastructure\Security\Middleware\IpWhitelist;
use Infrastructure\Security\Middleware\SecurityHeaders;
use Infrastructure\Tenant\Middleware\RequireOrganizationPermission;
use Infrastructure\Tenant\Middleware\RequireOrganizationRole;
use Infrastructure\Tenant\Middleware\RequireTenant;
use Infrastructure\Tenant\Middleware\SetTenantContext;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            SetLocale::class,
        ], append: [
            SecurityHeaders::class,
            AuditMiddleware::class,
        ]);

        $middleware->alias([
            'tenant.context' => SetTenantContext::class,
            'tenant.required' => RequireTenant::class,
            'tenant.role' => RequireOrganizationRole::class,
            'tenant.permission' => RequireOrganizationPermission::class,
            'subscription.required' => RequireActiveSubscription::class,
            'limit' => CheckResourceLimit::class,
            'plan.feature' => RequirePlanFeature::class,
            'rate.limit' => PlanBasedRateLimitMiddleware::class,
            'security.check' => CheckSuspiciousActivity::class,
            'ip.whitelist' => IpWhitelist::class,
            'audit' => AuditMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // This is an API: always answer API requests with JSON, even when the
        // client forgot the "Accept: application/json" header.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson()
        );

        // Messages Laravel writes itself ("Unauthenticated.", "Too Many
        // Attempts.", "This action is unauthorized.") follow the request's
        // language like ours do.
        $exceptions->respond(function (Response $response) {
            if ($response instanceof JsonResponse && is_string($response->getData(true)['message'] ?? null)) {
                $data = $response->getData(true);
                $data['message'] = __($data['message']);
                $response->setData($data);
            }

            return $response;
        });

        // Do not leak internal model class names ("No query results for model
        // [App\Models\...]") to API clients.
        $exceptions->render(function (NotFoundHttpException $exception, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            $message = $exception->getPrevious() instanceof ModelNotFoundException
                ? __('Resource not found.')
                : __($exception->getMessage() ?: 'Not found.');

            return response()->json(['status' => 'error', 'message' => $message], 404);
        });
    })->create();
