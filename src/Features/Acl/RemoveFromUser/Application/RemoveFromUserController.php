<?php

namespace Features\Acl\RemoveFromUser\Application;

use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Response;

#[Group('Roles')]
class RemoveFromUserController
{
    /**
     * Remove a role from a user.
     */
    public function __invoke(string $userId, string $roleId, RemoveFromUserHandler $handler): Response
    {
        $handler(
            command: new RemoveFromUser(
                userId: $userId,
                roleId: $roleId
            )
        );

        return response()->noContent();
    }
}
