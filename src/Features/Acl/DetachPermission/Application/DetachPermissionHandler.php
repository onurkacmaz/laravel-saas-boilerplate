<?php

namespace Features\Acl\DetachPermission\Application;

use App\Models\Role;
use Features\Acl\Shared\Support\AclRules;

class DetachPermissionHandler
{
    public function __invoke(DetachPermission $command): void
    {
        $role = Role::query()->findOrFail($command->roleId);

        AclRules::ensureMutable($role);

        $permission = $role->permissions()->findOrFail($command->permissionId);

        $role->permissions()->detach($permission->id);
    }
}
