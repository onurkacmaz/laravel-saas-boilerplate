<?php

namespace Infrastructure\Tenant\Middleware;

use Closure;
use Illuminate\Http\Request;
use Infrastructure\Tenant\TenantContext;
use Symfony\Component\HttpFoundation\Response;

class RequireOrganizationPermission
{
    /**
     * Ensure the authenticated user holds every given permission (by slug) in
     * the current organization. Owners and admins hold all permissions.
     *
     * Usage: ->middleware('tenant.permission:projects.manage')
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $organization = TenantContext::getOrganization();
        $user = $request->user();

        if (! $organization || ! $user) {
            return response()->json([
                'message' => __('Organization context is required. Please provide X-Organization-Id header.'),
            ], 403);
        }

        foreach ($permissions as $permission) {
            if (! $organization->userHasPermission($user, $permission)) {
                return response()->json([
                    'message' => __('You do not have permission to perform this action.'),
                    'permission' => $permission,
                ], 403);
            }
        }

        return $next($request);
    }
}
