<?php

namespace Infrastructure\Tenant\Middleware;

use Closure;
use Illuminate\Http\Request;
use Infrastructure\Tenant\TenantContext;
use Symfony\Component\HttpFoundation\Response;

class RequireTenant
{
    /**
     * Handle an incoming request.
     *
     * Ensures that a valid tenant (organization) context is set.
     * Returns 403 if no organization is set or the user doesn't belong to it.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! TenantContext::hasOrganization()) {
            return response()->json([
                'message' => 'Organization context is required. Please provide X-Organization-Id header.',
            ], 403);
        }

        $user = $request->user();

        if ($user && ! TenantContext::userBelongsToOrganization()) {
            return response()->json([
                'message' => 'You do not have access to this organization.',
            ], 403);
        }

        return $next($request);
    }
}
