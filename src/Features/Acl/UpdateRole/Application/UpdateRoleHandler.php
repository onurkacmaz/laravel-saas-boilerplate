<?php

namespace Features\Acl\UpdateRole\Application;

use App\Models\Role;
use Features\Acl\UpdateRole\Events\RoleUpdated;
use Illuminate\Support\Str;

class UpdateRoleHandler
{
    public function __invoke(UpdateRole $command): Role
    {
        $role = Role::query()->findOrFail($command->roleId);

        if ($command->name && ! $command->slug) {
            $command->slug = Str::slug($command->name);
        }

        $role->update([
            'name' => $command->name,
            'slug' => $command->slug,
            'description' => $command->description,
            'is_default' => $command->isDefault ?? false,
        ]);

        event(new RoleUpdated($role));

        return $role->fresh();
    }
}
