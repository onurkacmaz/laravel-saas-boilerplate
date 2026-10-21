<?php

namespace Infrastructure\Tenant\Middleware;

use Closure;
use Illuminate\Http\Request;
use Infrastructure\Tenant\TenantContext;
use Symfony\Component\HttpFoundation\Response;

class RequireOrganizationRole
{
    /**
     * Ensure the authenticated user holds one of the given roles (by slug) in
     * the current organization. The organization owner always passes.
     *
     * Usage: ->middleware('tenant.role:admin') or ->middleware('tenant.role:owner')
     * The "admin" role implicitly includes the owner.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $organization = TenantContext::getOrganization();
        $user = $request->user();

        if (! $organization || ! $user) {
            return response()->json([
                'message' => __('Organization context is required. Please provide X-Organization-Id header.'),
            ], 403);
        }

        if ($organization->isOwner($user)) {
            return $next($request);
        }

        $roles = $roles === [] ? ['admin'] : $roles;
        $currentRole = $organization->roleOf($user)?->slug;

        // "owner" as a role slug (as used by system roles) is equivalent to ownership.
        $allowed = in_array($currentRole, $roles, true)
            || (in_array('admin', $roles, true) && $currentRole === 'owner');

        if (! $allowed) {
            return response()->json([
                'message' => __('You do not have permission to perform this action.'),
            ], 403);
        }

        return $next($request);
    }
}
