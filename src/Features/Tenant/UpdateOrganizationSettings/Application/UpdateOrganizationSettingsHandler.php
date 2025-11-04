<?php

namespace Features\Tenant\UpdateOrganizationSettings\Application;

use App\Models\Organization;
use App\Models\OrganizationSetting;

class UpdateOrganizationSettingsHandler
{
    public function __invoke(UpdateOrganizationSettings $command): OrganizationSetting
    {
        $organization = Organization::query()->findOrFail($command->organizationId);

        $setting = $organization?->setting;

        if (! $setting) {
            $setting = OrganizationSetting::query()->create([
                'organization_id' => $organization?->id,
                ...OrganizationSetting::defaultAttributes(),
                ...$command->data,
            ]);
        } else {
            $setting->update($command->data);
        }

        return $setting->fresh();
    }
}
