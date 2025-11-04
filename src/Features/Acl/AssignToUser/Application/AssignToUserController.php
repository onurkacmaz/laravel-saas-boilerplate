<?php

namespace Features\Acl\AssignToUser\Application;

use Dedoc\Scramble\Attributes\Group;
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
                roleId: $request->input('role_id')
            )
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Role assigned successfully',
            'data' => $user,
        ]);
    }
}
