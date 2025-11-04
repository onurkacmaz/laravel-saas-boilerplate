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
     */
    public function __invoke(UpdateOrganizationRequest $request, string $organizationId, UpdateOrganizationHandler $handler): JsonResource
    {
        $organization = $handler(
            command: new UpdateOrganization(
                organizationId: $organizationId,
                name: $request->input('name'),
                description: $request->input('description'),
                isActive: $request->boolean('is_active')
            )
        );

        return $organization->toResource(OrganizationResource::class)->additional([
            'status' => 'success',
            'message' => 'Organization updated successfully',
        ]);
    }
}
