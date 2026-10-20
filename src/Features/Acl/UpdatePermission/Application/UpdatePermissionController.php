<?php

namespace Features\Acl\UpdatePermission\Application;

use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group('Permissions')]
class UpdatePermissionController
{
    /**
     * Update a permission.
     *
     * Only the fields present in the request are updated. System permissions are read-only.
     */
    public function __invoke(UpdatePermissionRequest $request, string $permissionId, UpdatePermissionHandler $handler): JsonResponse
    {
        $permission = $handler(
            command: new UpdatePermission(
                permissionId: $permissionId,
                attributes: $request->validated(),
            )
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Permission updated successfully',
            'data' => $permission,
        ]);
    }
}
