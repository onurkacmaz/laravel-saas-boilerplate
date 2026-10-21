<?php

namespace Features\Acl\GetRoles\Application;

use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group('Roles')]
class GetRolesController
{
    /**
     * Get all roles.
     */
    public function __invoke(GetRolesHandler $handler): JsonResponse
    {
        $roles = $handler(
            command: new GetRoles
        );

        return response()->json([
            'status' => 'success',
            'message' => __('Roles retrieved successfully'),
            'data' => $roles,
        ]);
    }
}
