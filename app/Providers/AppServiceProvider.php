<?php

namespace App\Providers;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Infrastructure\Tenant\Middleware\RequireTenant;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void {}

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (class_exists(\Laravel\Cashier\Cashier::class)) {
            \Laravel\Cashier\Cashier::useCustomerModel(\App\Models\Organization::class);
            \Laravel\Cashier\Cashier::calculateTaxes();
            if (class_exists(\Features\Subscription\Infrastructure\Eloquent\Models\Subscription::class)) {
                \Laravel\Cashier\Cashier::useSubscriptionModel(\Features\Subscription\Infrastructure\Eloquent\Models\Subscription::class);
            }
        }

        if (class_exists(\Dedoc\Scramble\Scramble::class)) {
            \Dedoc\Scramble\Scramble::configure()
                ->routes(fn (Route $route) => $route->middleware(['api', 'auth:sanctum']))
                ->withDocumentTransformers(function (\Dedoc\Scramble\Support\Generator\OpenApi $openApi) {
                    $openApi->secure(
                        \Dedoc\Scramble\Support\Generator\SecurityScheme::http('bearer')
                    );

                    $apiPath = config('scramble.api_path', 'api');

                    $tenantRequiredOperations = collect(RouteFacade::getRoutes()->getRoutes())
                        ->filter(function (Route $route) {
                            return collect($route->gatherMiddleware())->contains(function (string $middleware) {
                                return $middleware === 'tenant.required'
                                    || str_starts_with($middleware, 'tenant.required:')
                                    || $middleware === RequireTenant::class;
                            });
                        })
                        ->mapWithKeys(function (Route $route) use ($apiPath) {
                            $pathAliases = \Dedoc\Scramble\Reflection\ReflectionRoute::createFromRoute($route)->getSignatureParametersMap();
                            $uriWithoutOptionalParameters = Str::replace('?}', '}', $route->uri());

                            $normalizedPath = Str::replace(
                                collect($pathAliases)->keys()->map(fn ($key) => '{'.$key.'}')->all(),
                                collect($pathAliases)->values()->map(fn ($value) => '{'.$value.'}')->all(),
                                $uriWithoutOptionalParameters,
                            );

                            $normalizedPath = Str::of($normalizedPath)
                                ->replaceStart($apiPath, '')
                                ->trim('/')
                                ->toString();

                            $method = strtolower($route->methods()[0]);

                            return [sprintf('%s %s', $method, $normalizedPath) => true];
                        });

                    foreach ($openApi->paths as $path) {
                        foreach ($path->operations as $operation) {
                            $operationKey = sprintf('%s %s', $operation->method, $path->path);

                            if (! $tenantRequiredOperations->has($operationKey)) {
                                continue;
                            }

                            $hasOrganizationHeader = collect($operation->parameters ?? [])
                                ->contains(function ($parameter) {
                                    return $parameter instanceof \Dedoc\Scramble\Support\Generator\Parameter
                                        && $parameter->name === 'X-Organization-Id'
                                        && $parameter->in === 'header';
                                });

                            if (! $hasOrganizationHeader) {
                                $operation->addParameters([
                                    \Dedoc\Scramble\Support\Generator\Parameter::make('X-Organization-Id', 'header')
                                        ->required(true)
                                        ->setSchema(\Dedoc\Scramble\Support\Generator\Schema::fromType(new \Dedoc\Scramble\Support\Generator\Types\StringType))
                                        ->example('09ddaeae-1654-486d-b557-eab6cdc72ad9')
                                        ->description('Identifier of the organization context for the request.'),
                                ]);
                            }
                        }
                    }
                });
        }

        if (class_exists(\Laravel\Sanctum\Sanctum::class)) {
            \Laravel\Sanctum\Sanctum::usePersonalAccessTokenModel(\App\Models\PersonalAccessToken::class);
        }
    }
}
