<?php

namespace Features\Acl\UpdateRole\Application;

use App\Models\Role;
use Features\Acl\Shared\Support\AclRules;
use Features\Acl\UpdateRole\Events\RoleUpdated;
use Illuminate\Support\Str;

class UpdateRoleHandler
{
    public function __invoke(UpdateRole $command): Role
    {
        $role = Role::query()->findOrFail($command->roleId);

        AclRules::ensureMutable($role);

        $attributes = array_intersect_key($command->attributes, array_flip(['name', 'slug', 'description', 'is_default']));

        if (array_key_exists('slug', $attributes) && empty($attributes['slug'])) {
            unset($attributes['slug']);
        }

        if (isset($attributes['name']) && ! isset($attributes['slug']) && ! isset($command->attributes['slug'])) {
            $attributes['slug'] = Str::slug($attributes['name']);
        }

        $role->update($attributes);

        event(new RoleUpdated($role));

        return $role->fresh();
    }
}
