<?php

namespace Features\Acl\CreatePermission\Application;

use App\Models\Permission;
use Illuminate\Support\Str;

class CreatePermissionHandler
{
    public function __invoke(CreatePermission $command): Permission
    {
        return Permission::query()->create([
            'name' => $command->name,
            'slug' => $command->slug ?: Str::slug($command->name, '.'),
            'description' => $command->description,
        ])->load('roles');
    }
}
