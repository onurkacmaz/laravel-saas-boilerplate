<?php

namespace Features\Tenant\Shared\Support;

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Features\Acl\Shared\Support\SystemRoles;
use InvalidArgumentException;

final class OrganizationRoles
{
    /**
     * Resolve a role that may be granted to members of the organization.
     *
     * Only system roles or roles owned by this very organization qualify, and
     * the owner role can never be handed out: ownership is a property of the
     * organization (owner_id), not something an admin can grant.
     *
     * @throws InvalidArgumentException
     */
    public static function assignable(Organization $organization, string $roleId): Role
    {
        /** @var Role|null $role */
        $role = Role::query()->withoutOrganization()->find($roleId);

        if (! $role || ($role->organization_id !== null && $role->organization_id !== $organization->id)) {
            throw new InvalidArgumentException('The selected role does not belong to this organization.', 422);
        }

        if (SystemRoles::isOwnerRole($role)) {
            throw new InvalidArgumentException('The owner role cannot be assigned. Transfer the ownership instead.', 422);
        }

        return $role;
    }

    /**
     * Whether $actor may manage (change the role of, or remove) $target.
     * Owners can manage everyone but themselves; admins can only manage
     * members that are not admins or owners.
     */
    public static function canManage(Organization $organization, User $actor, User $target): bool
    {
        if ($organization->isOwner($target)) {
            return false;
        }

        if ($organization->isOwner($actor)) {
            return true;
        }

        return ! $organization->isAdmin($target);
    }
}
