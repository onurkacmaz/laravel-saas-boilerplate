<?php

namespace Features\Tenant\UpdateUserRoleOnOrganization\Application;

use App\Models\Organization;
use App\Models\User;
use Features\Tenant\Shared\Support\OrganizationRoles;
use Features\Tenant\UpdateUserRoleOnOrganization\Events\UserRoleUpdated;
use InvalidArgumentException;

class UpdateUserRoleOnOrganizationHandler
{
    /**
     * @throws InvalidArgumentException
     */
    public function __invoke(UpdateUserRoleOnOrganization $command): User
    {
        $organization = Organization::query()->findOrFail($command->organizationId);

        /** @var User $user */
        $user = $organization->users()->findOrFail($command->userId);

        if ($organization->isOwner($user)) {
            throw new InvalidArgumentException('The role of the organization owner cannot be changed.', 422);
        }

        if ($command->actor && ! OrganizationRoles::canManage($organization, $command->actor, $user)) {
            throw new InvalidArgumentException('You do not have permission to change the role of this member.', 403);
        }

        $role = OrganizationRoles::assignable($organization, $command->roleId);

        $organization->updateUserRole(user: $user, roleId: $role->id);

        event(new UserRoleUpdated($organization, $user, $role->id));

        return $user->fresh();
    }
}
