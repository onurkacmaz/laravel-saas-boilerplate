<?php

namespace Features\Acl\UpdatePermission\Application;

use App\Models\Permission;
use Features\Acl\Shared\Support\AclRules;

class UpdatePermissionHandler
{
    public function __invoke(UpdatePermission $command): Permission
    {
        $permission = Permission::query()->findOrFail($command->permissionId);

        AclRules::ensureMutable($permission);

        $attributes = array_intersect_key($command->attributes, array_flip(['name', 'slug', 'description']));

        // Permission slugs are referenced from code, so renaming a permission
        // never silently changes its slug; an empty slug keeps the current one.
        if (array_key_exists('slug', $attributes) && empty($attributes['slug'])) {
            unset($attributes['slug']);
        }

        $permission->update($attributes);

        return $permission->fresh();
    }
}
