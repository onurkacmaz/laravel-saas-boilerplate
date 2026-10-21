<?php

namespace Features\Tenant\GetOrganization\Application;

use Dedoc\Scramble\Attributes\Group;
use Features\Tenant\Shared\Resources\OrganizationResource;
use Illuminate\Http\Resources\Json\JsonResource;
use Throwable;

#[Group('Organizations')]
class GetOrganizationController
{
    /**
     * Get an organization by ID.
     *
     * @throws Throwable
     */
    public function __invoke(string $organizationId, GetOrganizationHandler $handler): JsonResource
    {
        $organization = $handler(
            command: new GetOrganization(
                organizationId: $organizationId
            )
        );

        return $organization->toResource(OrganizationResource::class)->additional([
            'status' => 'success',
            'message' => __('Organization retrieved successfully.'),
        ]);
    }
}
