<?php

namespace Features\Tenant\UpdateOrganizationSettings\Application;

use App\Models\Organization;
use App\Models\OrganizationSetting;

class UpdateOrganizationSettingsHandler
{
    public function __invoke(UpdateOrganizationSettings $command): OrganizationSetting
    {
        $organization = Organization::query()->findOrFail($command->organizationId);

        $setting = OrganizationSetting::query()->firstOrCreate(
            ['organization_id' => $organization->id],
            OrganizationSetting::defaultAttributes(),
        );

        $data = $command->data;

        // Nested preference objects are merged, so a client can toggle a single
        // flag without resending (and accidentally erasing) the other ones.
        foreach (['notification_preferences', 'feature_flags'] as $key) {
            if (array_key_exists($key, $data) && is_array($data[$key])) {
                $data[$key] = array_merge($setting->{$key} ?? [], $data[$key]);
            }
        }

        if (isset($data['currency'])) {
            $data['currency'] = strtoupper($data['currency']);
        }

        $setting->update($data);

        return $setting->fresh();
    }
}
