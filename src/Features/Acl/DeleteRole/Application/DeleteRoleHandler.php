<?php

namespace Features\Acl\DeleteRole\Application;

use App\Models\Role;
use Features\Acl\DeleteRole\Events\RoleDeleted;

class DeleteRoleHandler
{
    public function __invoke(DeleteRole $command): ?bool
    {
        $role = Role::query()->findOrFail($command->roleId);

        $deleted = $role->delete();

        if ($deleted) {
            event(new RoleDeleted($role));
        }

        return $deleted;
    }
}
