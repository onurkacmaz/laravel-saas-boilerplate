<?php

namespace Infrastructure\Tenant\Middleware;

use App\Models\Organization;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Infrastructure\Tenant\TenantContext;
use Symfony\Component\HttpFoundation\Response;

class SetTenantContext
{
    /**
     * Name of the request attribute holding the resolved organization id. It
     * outlives the tenant context so that outer middleware (e.g. auditing) can
     * still read it after the context has been cleared.
     */
    public const string REQUEST_ATTRIBUTE = 'tenant_organization_id';

    /**
     * Handle an incoming request.
     *
     * Identifies the tenant (organization) from the X-Organization-Id header
     * and sets it in the TenantContext for the duration of the request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Never trust state left over from a previous request (long-running workers).
        TenantContext::clear();

        try {
            $user = $this->resolveUser($request);

            if ($user) {
                TenantContext::setUser($user);
            }

            $organizationId = $request->header('X-Organization-Id');

            if (is_string($organizationId) && Str::isUuid($organizationId)) {
                $organization = Organization::query()->find($organizationId);

                if ($organization) {
                    // Verify the user belongs to this organization or is the owner
                    if ($user && ($organization->isOwner($user) || $organization->hasUser($user))) {
                        TenantContext::setOrganization($organization);
                    } elseif (! $user) {
                        // Allow setting organization without a user for public, tenant-aware routes.
                        TenantContext::setOrganization($organization);
                    }
                }
            }

            if (TenantContext::hasOrganization()) {
                $request->attributes->set(self::REQUEST_ATTRIBUTE, TenantContext::getOrganizationId());
            }

            return $next($request);
        } finally {
            // Clear context after request, even when an exception bubbles up.
            TenantContext::clear();
        }
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
