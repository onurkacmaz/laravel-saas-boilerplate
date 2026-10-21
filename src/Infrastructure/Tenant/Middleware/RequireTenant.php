<?php

namespace Infrastructure\Tenant\Middleware;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Infrastructure\Tenant\TenantContext;
use Symfony\Component\HttpFoundation\Response;

class RequireTenant
{
    /**
     * Handle an incoming request.
     *
     * Ensures that a valid tenant (organization) context is set and, when the
     * route targets a specific organization (an {organization} parameter), that
     * it is the same organization as the current tenant. This prevents a member
     * of organization A from acting on organization B by only swapping the URL.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! TenantContext::hasOrganization()) {
            return response()->json([
                'message' => __('Organization context is required. Please provide X-Organization-Id header.'),
            ], 403);
        }

        $user = $request->user();

        if ($user && ! TenantContext::userBelongsToOrganization() && ! TenantContext::userIsOwner()) {
            return response()->json([
                'message' => __('You do not have access to this organization.'),
            ], 403);
        }

        $routeOrganization = $request->route()?->parameter('organization');

        if ($routeOrganization instanceof Model) {
            $routeOrganization = $routeOrganization->getKey();
        }

        if ($routeOrganization !== null && (string) $routeOrganization !== TenantContext::getOrganizationId()) {
            return response()->json([
                'message' => __('The requested organization does not match the X-Organization-Id header.'),
            ], 403);
        }

        return $next($request);
    }
}
