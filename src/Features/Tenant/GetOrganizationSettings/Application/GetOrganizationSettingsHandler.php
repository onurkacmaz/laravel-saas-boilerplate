<?php

namespace Features\Tenant\GetOrganizationSettings\Application;

use App\Models\Organization;
use App\Models\OrganizationSetting;

class GetOrganizationSettingsHandler
{
    public function __invoke(GetOrganizationSettings $command)
    {
        $organization = Organization::query()->findOrFail($command->organizationId);

        return $organization->setting ?? OrganizationSetting::query()->create([
            'organization_id' => $organization?->id,
            ...OrganizationSetting::defaultAttributes(),
        ]);
    }
}
