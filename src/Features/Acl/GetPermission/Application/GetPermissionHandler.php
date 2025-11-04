<?php

namespace Features\Acl\GetPermission\Application;

use App\Models\Permission;

class GetPermissionHandler
{
    public function __invoke(GetPermission $command): Permission
    {
        return Permission::query()->findOrFail($command->permissionId);
    }
}
