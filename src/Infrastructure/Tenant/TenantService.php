<?php

namespace Infrastructure\Tenant;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Collection;

class TenantService
{
    /**
     * Get all organizations for a user.
     */
    public function getUserOrganizations(User $user): Collection
    {
        return Organization::query()
            ->whereHas('users', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->orWhere('owner_id', $user->id)
            ->where('is_active', true)
            ->get();
    }

    /**
     * Get the user's role in an organization.
     */
    public function getUserRoleInOrganization(User $user, Organization $organization): ?string
    {
        if ($organization->isOwner($user)) {
            return 'owner';
        }

        $pivot = $organization->users()
            ->where('user_id', $user->id)
            ->first()?->pivot;

        return $pivot?->role_id;
    }

    /**
     * Check if user can access organization.
     */
    public function canAccessOrganization(User $user, Organization $organization): bool
    {
        return $organization->hasUser($user) || $organization->isOwner($user);
    }

    /**
     * Switch the user's current organization context.
     */
    public function switchOrganization(User $user, string $organizationId): bool
    {
        $organization = Organization::query()->find($organizationId);

        if (! $organization || ! $organization->is_active) {
            return false;
        }

        if (! $this->canAccessOrganization($user, $organization)) {
            return false;
        }

        TenantContext::setOrganization($organization);
        TenantContext::setUser($user);

        return true;
    }

    /**
     * Get the current organization from context.
     */
    public function getCurrentOrganization(): ?Organization
    {
        return TenantContext::getOrganization();
    }

    /**
     * Check if a user has admin access in the current organization.
     */
    public function currentUserIsAdmin(): bool
    {
        return TenantContext::userIsAdmin();
    }

    /**
     * Check if user is an owner of current organization.
     */
    public function currentUserIsOwner(): bool
    {
        return TenantContext::userIsOwner();
    }
}
