<?php

namespace Features\Tenant\RemoveUserFromOrganization\Application;

use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Response;

#[Group('Organizations')]
class RemoveUserFromOrganizationController
{
    /**
     * Remove a user from an organization.
     */
    public function __invoke(string $organizationId, string $userId, RemoveUserFromOrganizationHandler $handler): Response
    {
        $handler(
            command: new RemoveUserFromOrganization(
                organizationId: $organizationId,
                userId: $userId
            )
        );

        return response()->noContent();
    }
}
