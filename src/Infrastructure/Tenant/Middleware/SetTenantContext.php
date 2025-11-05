<?php

namespace Infrastructure\Tenant\Middleware;

use App\Models\Organization;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Infrastructure\Tenant\TenantContext;
use Symfony\Component\HttpFoundation\Response;

class SetTenantContext
{
    /**
     * Handle an incoming request.
     *
     * Identifies the tenant (organization) from the X-Organization-Id header
     * and sets it in the TenantContext for the duration of the request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $this->resolveUser($request);

        if ($user) {
            TenantContext::setUser($user);
        }

        // Get organization ID from the header
        $organizationId = $request->header('X-Organization-Id');

        if ($organizationId) {
            $organization = Organization::query()->find($organizationId);

            if ($organization) {
                // Verify the user belongs to this organization or is the owner
                if ($user && ($organization->hasUser($user) || $organization->isOwner($user))) {
                    TenantContext::setOrganization($organization);
                    TenantContext::setUser($user);
                } elseif (! $user) {
                    // Allow setting organization without a user for specific cases
                    TenantContext::setOrganization($organization);
                }
            }
        }

        $response = $next($request);

        // Clear context after request
        TenantContext::clear();

        return $response;
    }

    private function resolveUser(Request $request): ?User
    {
        $guards = array_unique(array_merge(
            [null, 'sanctum'],
            [config('auth.defaults.guard')],
            array_keys(config('auth.guards', []))
        ));

        foreach (array_filter($guards, fn (?string $guard) => $guard !== '') as $guard) {
            $user = $guard
                ? Auth::guard($guard)->user()
                : $request->user();

            if ($user instanceof User) {
                return $user;
            }
        }

        return null;
    }
}
