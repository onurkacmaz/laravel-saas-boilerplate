<?php

namespace Features\Acl\CreateRole\Application;

use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

#[Group('Roles')]
class CreateRoleController
{
    /**
     * Create a new role.
     */
    public function __invoke(CreateRoleRequest $request, CreateRoleHandler $handler): JsonResponse
    {
        $role = $handler(
            command: new CreateRole(
                name: $request->input('name'),
                slug: $request->input('slug'),
                description: $request->input('description'),
                isDefault: $request->boolean('is_default'),
            )
        );

        return response()->json([
            'status' => 'success',
            'message' => __('Role created successfully'),
            'data' => $role,
        ], Response::HTTP_CREATED);
    }
}
