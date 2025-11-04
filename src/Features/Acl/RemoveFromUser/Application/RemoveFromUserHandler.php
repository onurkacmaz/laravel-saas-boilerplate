<?php

namespace Features\Acl\RemoveFromUser\Application;

use App\Models\User;

class RemoveFromUserHandler
{
    public function __invoke(RemoveFromUser $command): void
    {
        $user = User::query()->findOrFail($command->userId);
        $role = $user->roles()->findOrFail($command->roleId);

        $user->roles()->detach($role->id);
    }
}
