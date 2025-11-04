<?php

namespace Features\Acl\DeletePermission\Application;

use App\Models\Permission;

class DeletePermissionHandler
{
    public function __invoke(DeletePermission $command): void
    {
        Permission::query()->where('id', $command->permissionId)->delete();
    }
}
