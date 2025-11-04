<?php

namespace Features\Acl\CreatePermission\Application;

use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

#[Group('Permissions')]
class CreatePermissionController
{
    /**
     * Create a new permission.
     */
    public function __invoke(CreatePermissionRequest $request, CreatePermissionHandler $handler): JsonResponse
    {
        $permission = $handler(
            command: new CreatePermission(
                name: $request->input('name'),
                slug: $request->input('slug'),
                description: $request->input('description'),
            )
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Permission created successfully',
            'data' => $permission,
        ], Response::HTTP_CREATED);
    }
}
