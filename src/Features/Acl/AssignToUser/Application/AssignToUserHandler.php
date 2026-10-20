<?php

namespace Features\Acl\AssignToUser\Application;

use App\Models\Role;
use App\Models\User;
use Features\Acl\Shared\Support\AclRules;
use Features\Acl\Shared\Support\SystemRoles;

class AssignToUserHandler
{
    public function __invoke(AssignToUser $command): User
    {
        // Only members of the current organization can be targeted, and only
        // with roles visible to it.
        $user = AclRules::memberOfCurrentOrganization($command->userId);
        $role = Role::query()->findOrFail($command->roleId);

        abort_if(SystemRoles::isOwnerRole($role), 422, 'The owner role cannot be assigned.');

        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user->fresh('roles');
    }
}
