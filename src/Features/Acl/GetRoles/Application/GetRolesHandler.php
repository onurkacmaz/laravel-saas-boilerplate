<?php

namespace Features\Acl\GetRoles\Application;

use App\Models\Role;
use Illuminate\Database\Eloquent\Collection;

class GetRolesHandler
{
    public function __invoke(GetRoles $command): Collection
    {
        return Role::with('permissions')->get();
    }
}
