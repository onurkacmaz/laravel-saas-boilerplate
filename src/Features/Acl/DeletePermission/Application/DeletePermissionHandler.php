<?php

namespace Features\Acl\DeletePermission\Application;

use App\Models\Permission;
use Features\Acl\Shared\Support\AclRules;

class DeletePermissionHandler
{
    public function __invoke(DeletePermission $command): void
    {
        $permission = Permission::query()->findOrFail($command->permissionId);

        AclRules::ensureMutable($permission);

        $permission->roles()->detach();
        $permission->delete();
    }
}
