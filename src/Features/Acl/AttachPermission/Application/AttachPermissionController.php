<?php

namespace Features\Acl\AttachPermission\Application;

use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group('Roles')]
class AttachPermissionController
{
    /**
     * Attach a permission to a role.
     */
    public function __invoke(AttachPermissionRequest $request, string $roleId, AttachPermissionHandler $handler): JsonResponse
    {
        $role = $handler(
            command: new AttachPermission(
                roleId: $roleId,
                permissionId: $request->input('permission_id'),
            )
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Permission attached successfully',
            'data' => $role,
        ]);
    }
}
