<?php

namespace Features\Subscription\Infrastructure\Middleware;

use Closure;
use Features\Subscription\Shared\Support\ResourceLimits;
use Features\Subscription\Shared\Support\UsageTracker;
use Illuminate\Http\Request;
use Infrastructure\Tenant\TenantContext;
use Symfony\Component\HttpFoundation\Response;

class CheckResourceLimit
{
    /**
     * Handle an incoming request.
     *
     * Check if the organization has reached the limit for a specific resource.
     *
     * Usage: Route::middleware('limit:users')
     */
    public function handle(Request $request, Closure $next, string $resourceType): Response
    {
        $organization = TenantContext::getOrganization();

        if (! $organization) {
            return response()->json([
                'message' => __('Organization context is required.'),
            ], 403);
        }

        $tracker = new UsageTracker($organization);
        $limits = new ResourceLimits($organization);

        $current = $tracker->getCurrentCount($resourceType);
        $limit = $limits->getLimit($resourceType);

        if ($limits->hasReached($resourceType, $current)) {
            return response()->json([
                'message' => __('You have reached the limit for :resource.', ['resource' => $resourceType]),
                'resource' => $resourceType,
                'current' => $current,
                'limit' => $limit,
                'upgrade_required' => true,
            ], 429); // 429 Too Many Requests
        }

        return $next($request);
    }
}
