<?php

namespace Features\Tenant\CreateOrganization\Application;

use Dedoc\Scramble\Attributes\Group;
use Features\Tenant\Shared\Resources\OrganizationResource;
use Illuminate\Http\Resources\Json\JsonResource;

#[Group('Organizations')]
class CreateOrganizationController
{
    /**
     * Create a new organization.
     */
    public function __invoke(CreateOrganizationRequest $request, CreateOrganizationHandler $handler): JsonResource
    {
        $organization = $handler(
            command: new CreateOrganization(
                userId: $request->user()->id,
                name: $request->input('name'),
                description: $request->input('description')
            )
        );

        return $organization->toResource(OrganizationResource::class)->additional([
            'status' => 'success',
            'message' => 'Organizations created successfully',
        ]);
    }
}
