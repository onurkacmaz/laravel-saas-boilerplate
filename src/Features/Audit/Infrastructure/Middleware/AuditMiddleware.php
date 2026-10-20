<?php

namespace Features\Audit\Infrastructure\Middleware;

use Closure;
use Features\Audit\Infrastructure\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

readonly class AuditMiddleware
{
    public function __construct(
        private AuditLogger $auditLogger
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only audit successful state-changing requests. Auditing must never
        // turn a successful request into an error.
        if ($this->shouldAudit($request, $response)) {
            rescue(fn () => $this->auditLogger->action(
                action: $this->getActionName($request),
                metadata: [
                    'method' => $request->method(),
                    // The path only: query strings may carry signatures or tokens.
                    'path' => '/'.ltrim($request->path(), '/'),
                    'route' => $request->route()?->getName(),
                    'status_code' => $response->getStatusCode(),
                ]
            ));
        }

        return $response;
    }

    /**
     * Determine if the request should be audited.
     */
    private function shouldAudit(Request $request, Response $response): bool
    {
        // Only audit authenticated requests
        if (! $request->user()) {
            return false;
        }

        // Only audit state-changing methods
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            return false;
        }

        // Only audit successful responses
        if ($response->getStatusCode() >= 400) {
            return false;
        }

        return (bool) config('audit.enabled', true);
    }

    /**
     * Get a descriptive action name from the request.
     */
    private function getActionName(Request $request): string
    {
        // Route names ("api.v1.organizations.update") describe the action far
        // better than the URL, whose last segment is often an identifier.
        $routeName = $request->route()?->getName();

        if ($routeName) {
            return preg_replace('/^api\.v\d+\./', '', $routeName);
        }

        $method = strtolower($request->method());
        $segments = array_values(array_filter(
            explode('/', $request->path()),
            fn (string $segment) => $segment !== '' && ! Str::isUuid($segment)
        ));

        return $method.'.'.($segments[array_key_last($segments)] ?? 'unknown');
    }
}
