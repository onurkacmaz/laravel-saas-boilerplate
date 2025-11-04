<?php

namespace Features\Acl\DetachPermission\Application;

use App\Models\Role;

class DetachPermissionHandler
{
    public function __invoke(DetachPermission $command): void
    {
        $role = Role::query()->findOrFail($command->roleId);
        $permission = $role->permissions()->findOrFail($command->permissionId);

        $role->permissions()->detach($permission->id);
    }
}
