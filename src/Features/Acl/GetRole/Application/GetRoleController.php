<?php

namespace Features\Acl\GetRole\Application;

use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group('Roles')]
class GetRoleController
{
    /**
     * Get a role by ID.
     */
    public function __invoke(string $roleId, GetRoleHandler $handler): JsonResponse
    {
        $role = $handler(
            command: new GetRole(
                roleId: $roleId
            )
        );

        return response()->json([
            'status' => 'success',
            'message' => __('Role retrieved successfully'),
            'data' => $role,
        ]);
    }
}
