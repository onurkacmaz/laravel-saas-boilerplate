<?php

namespace Features\Acl\AttachPermission\Application;

use App\Models\Permission;
use App\Models\Role;

class AttachPermissionHandler
{
    public function __invoke(AttachPermission $command): Role
    {
        $role = Role::query()->findOrFail($command->roleId);
        $permission = Permission::query()->findOrFail($command->permissionId);

        if (! $role->permissions()->where('permission_id', $permission->id)->exists()) {
            $role->permissions()->attach($permission->id);
        }

        return $role->load('permissions')->fresh();
    }
}
