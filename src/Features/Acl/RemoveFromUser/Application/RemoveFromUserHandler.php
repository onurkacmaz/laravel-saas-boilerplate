<?php

namespace Features\Acl\RemoveFromUser\Application;

use Features\Acl\Shared\Support\AclRules;

class RemoveFromUserHandler
{
    public function __invoke(RemoveFromUser $command): void
    {
        $user = AclRules::memberOfCurrentOrganization($command->userId);

        // The tenant scope applies to the related roles as well.
        $role = $user->roles()->findOrFail($command->roleId);

        $user->roles()->detach($role->id);
    }
}
