<?php

namespace Features\Acl\AssignToUser\Application;

use App\Models\Role;
use App\Models\User;

class AssignToUserHandler
{
    public function __invoke(AssignToUser $command): User
    {
        $user = User::query()->findOrFail($command->userId);
        $role = Role::query()->findOrFail($command->roleId);

        if (! $user->roles()->where('role_id', $role->id)->exists()) {
            $user->roles()->attach($role->id);
        }

        return $user->load('roles')->fresh();
    }
}
