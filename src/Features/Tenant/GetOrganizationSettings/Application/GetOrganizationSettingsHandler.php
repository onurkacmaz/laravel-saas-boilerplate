<?php

namespace Features\Tenant\GetOrganizationSettings\Application;

use App\Models\Organization;
use App\Models\OrganizationSetting;

class GetOrganizationSettingsHandler
{
    public function __invoke(GetOrganizationSettings $command): OrganizationSetting
    {
        $organization = Organization::query()->findOrFail($command->organizationId);

        // firstOrCreate keeps concurrent first reads from racing on the unique index.
        return OrganizationSetting::query()->firstOrCreate(
            ['organization_id' => $organization->id],
            OrganizationSetting::defaultAttributes(),
        );
    }
}
