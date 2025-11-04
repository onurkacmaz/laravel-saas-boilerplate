<?php

namespace Features\Acl\GetPermissions\Application;

use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group('Permissions')]
class GetPermissionsController
{
    /**
     * Get all permissions.
     */
    public function __invoke(GetPermissionsHandler $handler): JsonResponse
    {
        $permissions = $handler(
            command: new GetPermissions
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Permissions retrieved successfully',
            'data' => $permissions,
        ]);
    }
}
