<?php

namespace Features\Tenant\AddUserToOrganization\Application;

use App\Models\Organization;
use App\Models\User;
use Features\Tenant\AddUserToOrganization\Events\UserAddedToOrganization;
use Features\Tenant\Shared\Support\OrganizationRoles;
use InvalidArgumentException;

class AddUserToOrganizationHandler
{
    /**
     * @throws InvalidArgumentException
     */
    public function __invoke(AddUserToOrganization $command): User
    {
        $organization = Organization::query()->findOrFail($command->organizationId);
        $user = User::query()->findOrFail($command->userId);

        if ($organization->hasUser($user)) {
            throw new InvalidArgumentException('User is already a member of this organization.', 422);
        }

        $role = OrganizationRoles::assignable($organization, $command->roleId);

        $organization->addUser(user: $user, roleId: $role->id);

        event(new UserAddedToOrganization($organization, $user, $role->id));

        return $user;
    }
}
