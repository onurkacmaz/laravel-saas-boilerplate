<?php

namespace Features\Tenant\DeleteOrganization\Application;

use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Response;

#[Group('Organizations')]
class DeleteOrganizationController
{
    /**
     * Delete an organization by ID.
     */
    public function __invoke(string $organizationId, DeleteOrganizationHandler $handler): Response
    {
        $handler(
            command: new DeleteOrganization(
                organizationId: $organizationId
            )
        );

        return response()->noContent();
    }
}
