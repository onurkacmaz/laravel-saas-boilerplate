<?php

namespace Features\Acl\AttachPermission\Application;

use App\Models\Permission;
use App\Models\Role;
use Features\Acl\Shared\Support\AclRules;

class AttachPermissionHandler
{
    public function __invoke(AttachPermission $command): Role
    {
        $role = Role::query()->findOrFail($command->roleId);

        AclRules::ensureMutable($role);

        // The tenant scope restricts this to the organization's permissions and
        // the system-wide ones.
        $permission = Permission::query()->findOrFail($command->permissionId);

        $role->permissions()->syncWithoutDetaching([$permission->id]);

        return $role->fresh('permissions');
    }
}
