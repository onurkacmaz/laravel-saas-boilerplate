<?php

namespace Features\Tenant\UpdateOrganization\Application;

use Dedoc\Scramble\Attributes\Group;
use Features\Tenant\Shared\Resources\OrganizationResource;
use Illuminate\Http\Resources\Json\JsonResource;

#[Group('Organizations')]
class UpdateOrganizationController
{
    /**
     * Update an organization by ID.
     *
     * Only the fields present in the request are updated.
     */
    public function __invoke(UpdateOrganizationRequest $request, string $organizationId, UpdateOrganizationHandler $handler): JsonResource
    {
        $organization = $handler(
            command: new UpdateOrganization(
                organizationId: $organizationId,
                attributes: $request->validated(),
            )
        );

        return $organization->toResource(OrganizationResource::class)->additional([
            'status' => 'success',
            'message' => __('Organization updated successfully'),
        ]);
    }
}
