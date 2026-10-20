<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Infrastructure\Tenant\Middleware\RequireTenant;

class AppServiceProvider extends ServiceProvider
{
    private const string UUID_PATTERN = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}';

    /**
     * Register any application services.
     */
    public function register(): void
    {
        // The application ships its own Stripe webhook controller on the same URI
        // as Cashier's default one; only ours must be registered.
        if (class_exists(\Laravel\Cashier\Cashier::class)) {
            \Laravel\Cashier\Cashier::ignoreRoutes();
        }

        // Route parameters are UUIDs (or random tokens). Constraining them turns
        // malformed identifiers into a clean 404 instead of a database error
        // (e.g. PostgreSQL rejects non-UUID values for uuid columns). Feature
        // providers may load routes while registering, so this must happen here.
        RouteFacade::patterns([
            'organization' => self::UUID_PATTERN,
            'user' => self::UUID_PATTERN,
            'role' => self::UUID_PATTERN,
            'permission' => self::UUID_PATTERN,
            'invitation' => self::UUID_PATTERN,
            'webhook' => self::UUID_PATTERN,
            'plan' => self::UUID_PATTERN,
            'auditLog' => self::UUID_PATTERN,
            'token' => '[A-Za-z0-9]{1,128}',
        ]);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
        $this->configureAuthUrls();

        if (class_exists(\Laravel\Cashier\Cashier::class)) {
            \Laravel\Cashier\Cashier::useCustomerModel(\App\Models\Organization::class);
            \Laravel\Cashier\Cashier::calculateTaxes();
            if (class_exists(\Features\Subscription\Infrastructure\Eloquent\Models\Subscription::class)) {
                \Laravel\Cashier\Cashier::useSubscriptionModel(\Features\Subscription\Infrastructure\Eloquent\Models\Subscription::class);
            }
        }

        if (class_exists(\Dedoc\Scramble\Scramble::class)) {
            \Dedoc\Scramble\Scramble::configure()
                // Document the versioned API only. (Calling $route->middleware() here
                // would *add* middleware to every route instead of filtering them.)
                ->routes(fn (Route $route) => Str::startsWith($route->uri(), 'api/'))
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

    /**
     * Brute-force protection for unauthenticated authentication endpoints.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('auth', function (Request $request) {
            $email = Str::lower((string) $request->input('email'));

            return [
                Limit::perMinute(10)->by('auth:ip:'.$request->ip()),
                Limit::perMinute(5)->by('auth:email:'.$email.'|'.$request->ip()),
            ];
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by('2fa:'.sha1((string) $request->input('jwt')).'|'.$request->ip());
        });

        RateLimiter::for('verification', function (Request $request) {
            return Limit::perMinute(6)->by('verification:'.($request->user()?->id ?: $request->ip()));
        });
    }

    /**
     * Point password reset / e-mail verification links to the frontend when one
     * is configured (this is an API-first application).
     */
    private function configureAuthUrls(): void
    {
        ResetPassword::createUrlUsing(function ($user, string $token) {
            $base = rtrim((string) (config('app.frontend_url') ?: config('app.url')), '/');

            return $base.'/reset-password?'.http_build_query([
                'token' => $token,
                'email' => $user->getEmailForPasswordReset(),
            ]);
        });

        VerifyEmail::createUrlUsing(function ($user) {
            $signedUrl = URL::temporarySignedRoute(
                'api.v1.auth.verification.verify',
                now()->addMinutes((int) config('auth.verification.expire', 60)),
                [
                    'id' => $user->getKey(),
                    'hash' => sha1($user->getEmailForVerification()),
                ]
            );

            $frontendUrl = config('app.frontend_url');

            if (! $frontendUrl) {
                return $signedUrl;
            }

            return rtrim($frontendUrl, '/').'/verify-email?'.http_build_query(['verify_url' => $signedUrl]);
        });
    }
}
