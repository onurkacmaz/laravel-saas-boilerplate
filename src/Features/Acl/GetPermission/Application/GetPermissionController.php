<?php

namespace Features\Acl\GetPermission\Application;

use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group('Permissions')]
class GetPermissionController
{
    /**
     * Get a permission by ID.
     */
    public function __invoke(string $permissionId, GetPermissionHandler $handler): JsonResponse
    {
        $permission = $handler(
            command: new GetPermission(
                permissionId: $permissionId
            )
        );

        return response()->json([
            'status' => 'success',
            'message' => __('Permission retrieved successfully'),
            'data' => $permission,
        ]);
    }
}
