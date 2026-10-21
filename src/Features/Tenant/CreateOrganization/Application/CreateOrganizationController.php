<?php

namespace Features\Tenant\CreateOrganization\Application;

use Dedoc\Scramble\Attributes\Group;
use Features\Tenant\Shared\Resources\OrganizationResource;
use Illuminate\Http\JsonResponse;

#[Group('Organizations')]
class CreateOrganizationController
{
    /**
     * Create a new organization.
     */
    public function __invoke(CreateOrganizationRequest $request, CreateOrganizationHandler $handler): JsonResponse
    {
        $organization = $handler(
            command: new CreateOrganization(
                userId: $request->user()->id,
                name: (string) $request->validated('name'),
                description: $request->validated('description')
            )
        );

        return $organization->toResource(OrganizationResource::class)->additional([
            'status' => 'success',
            'message' => __('Organization created successfully'),
        ])->response()->setStatusCode(201);
    }
}
