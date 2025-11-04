<?php

namespace Features\Acl\UpdatePermission\Application;

use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group('Permissions')]
class UpdatePermissionController
{
    /**
     * Update a permission by ID.
     */
    public function __invoke(UpdatePermissionRequest $request, string $permissionId, UpdatePermissionHandler $handler): JsonResponse
    {
        $permission = $handler(
            command: new UpdatePermission(
                permissionId: $permissionId,
                name: $request->input('name'),
                slug: $request->input('slug'),
                description: $request->input('description')
            )
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Permission updated successfully',
            'data' => $permission,
        ]);
    }
}
