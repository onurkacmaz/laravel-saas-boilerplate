<?php

namespace Features\Acl\AssignToUser\Application;

use Dedoc\Scramble\Attributes\Group;
use Features\Auth\Shared\Resources\UserResource;
use Illuminate\Http\JsonResponse;

#[Group('Roles')]
class AssignToUserController
{
    /**
     * Assign a role to a user.
     */
    public function __invoke(AssignToUserRequest $request, string $userId, AssignToUserHandler $handler): JsonResponse
    {
        $user = $handler(
            command: new AssignToUser(
                userId: $userId,
                roleId: (string) $request->validated('role_id')
            )
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Role assigned successfully',
            'data' => [
                ...(new UserResource($user))->toArray($request),
                'roles' => $user->roles,
            ],
        ]);
    }
}
