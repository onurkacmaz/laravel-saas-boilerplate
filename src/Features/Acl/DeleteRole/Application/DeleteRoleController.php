<?php

namespace Features\Acl\DeleteRole\Application;

use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Response;

#[Group('Roles')]
class DeleteRoleController
{
    /**
     * Delete a role by ID.
     */
    public function __invoke(string $roleId, DeleteRoleHandler $handler): Response
    {
        $handler(
            command: new DeleteRole(
                roleId: $roleId
            )
        );

        return response()->noContent();
    }
}
