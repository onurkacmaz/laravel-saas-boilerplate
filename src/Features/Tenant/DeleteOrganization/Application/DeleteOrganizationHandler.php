<?php

namespace Features\Tenant\DeleteOrganization\Application;

use App\Models\Organization;
use Features\Tenant\DeleteOrganization\Events\OrganizationDeleted;

class DeleteOrganizationHandler
{
    public function __invoke(DeleteOrganization $command): void
    {
        $organization = Organization::query()->findOrFail($command->organizationId);

        $deleted = $organization?->delete();

        if ($deleted) {
            event(new OrganizationDeleted($organization));
        }
    }
}
