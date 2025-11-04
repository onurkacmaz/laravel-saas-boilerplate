<?php

namespace Features\Acl\DetachPermission\Application;

use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Response;

#[Group('Roles')]
class DetachPermissionController
{
    /**
     * Detach a permission from a role.
     */
    public function __invoke(string $roleId, string $permissionId, DetachPermissionHandler $handler): Response
    {
        $handler(
            command: new DetachPermission(
                roleId: $roleId,
                permissionId: $permissionId
            )
        );

        return response()->noContent();
    }
}
