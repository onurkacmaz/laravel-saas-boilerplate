<?php

namespace Features\Tenant\UpdateOrganizationSettings\Application;

use Dedoc\Scramble\Attributes\Group;
use Features\Tenant\Shared\Resources\OrganizationSettingResource;
use Illuminate\Http\Resources\Json\JsonResource;

#[Group('Organizations')]
class UpdateOrganizationSettingsController
{
    /**
     * Update the settings for an organization.
     */
    public function __invoke(UpdateOrganizationSettingsRequest $request, string $organizationId, UpdateOrganizationSettingsHandler $handler): JsonResource
    {
        $settings = $handler(
            command: new UpdateOrganizationSettings(
                organizationId: $organizationId,
                data: $request->validated()
            )
        );

        return $settings->toResource(OrganizationSettingResource::class)->additional([
            'status' => 'success',
            'message' => __('Organization settings updated successfully.'),
        ]);
    }
}
