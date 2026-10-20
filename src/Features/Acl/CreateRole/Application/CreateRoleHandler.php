<?php

namespace Features\Acl\CreateRole\Application;

use App\Models\Role;
use Features\Acl\CreateRole\Events\RoleCreated;
use Illuminate\Support\Str;

class CreateRoleHandler
{
    public function __invoke(CreateRole $command): Role
    {
        // The organization is stamped by the tenant scope trait.
        $role = Role::query()->create([
            'name' => $command->name,
            'slug' => $command->slug ?: Str::slug($command->name),
            'description' => $command->description,
            'is_default' => $command->isDefault,
        ]);

        event(new RoleCreated($role));

        return $role;
    }
}
