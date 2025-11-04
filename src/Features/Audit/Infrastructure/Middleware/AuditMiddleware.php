<?php

namespace Features\Audit\Infrastructure\Middleware;

use Closure;
use Features\Audit\Infrastructure\Support\AuditLogger;
use Illuminate\Http\Request;
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

        // Only audit successful state-changing requests
        if ($this->shouldAudit($request, $response)) {
            $this->auditLogger->action(
                action: $this->getActionName($request),
                metadata: [
                    'method' => $request->method(),
                    'url' => $request->fullUrl(),
                    'status_code' => $response->getStatusCode(),
                ]
            );
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

        return true;
    }

    /**
     * Get a descriptive action name from the request.
     */
    private function getActionName(Request $request): string
    {
        $method = strtolower($request->method());
        $path = $request->path();

        // Extract the resource name from the path
        $segments = explode('/', $path);
        $resource = $segments[array_key_last($segments)] ?? 'unknown';

        return "$method.$resource";
    }
}
