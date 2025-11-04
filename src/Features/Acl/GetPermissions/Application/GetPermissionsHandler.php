<?php

namespace Features\Acl\GetPermissions\Application;

use App\Models\Permission;
use Illuminate\Database\Eloquent\Collection;

class GetPermissionsHandler
{
    public function __invoke(GetPermissions $command): Collection
    {
        return Permission::with('roles')->get();
    }
}
