<?php

namespace Features\Acl\UpdatePermission\Application;

use App\Models\Permission;
use Illuminate\Support\Str;

class UpdatePermissionHandler
{
    public function __invoke(UpdatePermission $command): Permission
    {
        $permission = Permission::query()->findOrFail($command->permissionId);

        if ($command->name && ! $command->slug) {
            $command->slug = Str::slug($command->name);
        }

        return tap($permission)->update([
            'name' => $command->name,
            'slug' => $command->slug,
            'description' => $command->description,
        ]);
    }
}
