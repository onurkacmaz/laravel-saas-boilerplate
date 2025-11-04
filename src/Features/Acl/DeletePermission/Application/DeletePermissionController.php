<?php

namespace Features\Acl\DeletePermission\Application;

use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Response;

#[Group('Permissions')]
class DeletePermissionController
{
    /**
     * Delete a permission by ID.
     */
    public function __invoke(string $permissionId, DeletePermissionHandler $handler): Response
    {
        $handler(
            command: new DeletePermission(
                permissionId: $permissionId
            )
        );

        return response()->noContent();
    }
}
