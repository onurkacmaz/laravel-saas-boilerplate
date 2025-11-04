<?php

namespace Features\Acl\GetRole\Application;

use App\Models\Role;

class GetRoleHandler
{
    public function __invoke(GetRole $command): Role
    {
        return Role::query()->with('permissions')->findOrFail($command->roleId);
    }
}
