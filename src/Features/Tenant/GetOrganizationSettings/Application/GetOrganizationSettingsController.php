<?php

namespace Features\Tenant\GetOrganizationSettings\Application;

use Dedoc\Scramble\Attributes\Group;
use Features\Tenant\Shared\Resources\OrganizationSettingResource;
use Illuminate\Http\JsonResponse;

#[Group('Organizations')]
class GetOrganizationSettingsController
{
    /**
     * Get the settings for an organization.
     */
    public function __invoke(string $organizationId, GetOrganizationSettingsHandler $handler): JsonResponse
    {
        $settings = $handler(
            command: new GetOrganizationSettings(
                organizationId: $organizationId
            )
        );

        return $settings->toResource(OrganizationSettingResource::class)->additional([
            'status' => 'success',
            'message' => __('Organization settings retrieved successfully.'),
        ])->response()->setStatusCode(200);
    }
}
