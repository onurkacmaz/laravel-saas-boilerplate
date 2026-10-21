<?php

namespace Features\Acl\UpdateRole\Application;

use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group('Roles')]
class UpdateRoleController
{
    /**
     * Update a role.
     *
     * Only the fields present in the request are updated. System roles are read-only.
     */
    public function __invoke(UpdateRoleRequest $request, string $roleId, UpdateRoleHandler $handler): JsonResponse
    {
        $role = $handler(
            command: new UpdateRole(
                roleId: $roleId,
                attributes: $request->validated(),
            )
        );

        return response()->json([
            'status' => 'success',
            'message' => __('Role updated successfully'),
            'data' => $role,
        ]);
    }
}
