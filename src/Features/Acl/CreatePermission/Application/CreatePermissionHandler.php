<?php

namespace Features\Acl\CreatePermission\Application;

use App\Models\Permission;
use Illuminate\Support\Str;

class CreatePermissionHandler
{
    public function __invoke(CreatePermission $command): Permission
    {
        $command->slug = $command->slug ?? Str::slug($command->name);

        return Permission::query()->create([
            'name' => $command->name,
            'slug' => $command->slug,
            'description' => $command->description,
        ])->load('roles');
    }
}
