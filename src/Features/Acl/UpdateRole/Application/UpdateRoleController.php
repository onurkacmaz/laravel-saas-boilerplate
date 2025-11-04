<?php

namespace Features\Acl\UpdateRole\Application;

use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group('Roles')]
class UpdateRoleController
{
    /**
     * Update a role by ID.
     */
    public function __invoke(UpdateRoleRequest $request, string $roleId, UpdateRoleHandler $handler): JsonResponse
    {
        $role = $handler(
            command: new UpdateRole(
                roleId: $roleId,
                name: $request->input('name'),
                slug: $request->input('slug'),
                description: $request->input('description'),
                isDefault: $request->boolean('is_default')
            )
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Role updated successfully',
            'data' => $role,
        ]);
    }
}
