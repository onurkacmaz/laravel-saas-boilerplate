<?php

namespace Features\Acl\CreateRole\Application;

use App\Models\Role;
use Features\Acl\CreateRole\Events\RoleCreated;
use Illuminate\Support\Str;

class CreateRoleHandler
{
    public function __invoke(CreateRole $command): Role
    {
        $command->slug = $command->slug ?? Str::slug($command->name);

        $role = Role::query()->create([
            'name' => $command->name,
            'slug' => $command->slug,
            'description' => $command->description,
            'is_default' => $command->isDefault ?? false,
        ]);

        event(new RoleCreated($role));

        return $role;
    }
}
