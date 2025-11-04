<?php

namespace Features\Tenant\GetOrganizations\Application;

use Dedoc\Scramble\Attributes\Group;
use Features\Tenant\Shared\Resources\OrganizationResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Throwable;

#[Group('Organizations')]
class GetOrganizationsController
{
    /**
     * Get the authenticated user's organizations.
     *
     * @throws Throwable
     */
    public function __invoke(Request $request, GetOrganizationsHandler $handler): ResourceCollection
    {
        $organizations = $handler(
            command: new GetOrganizations(
                userId: $request->user()->id,
                page: $request->input('page'),
                perPage: $request->input('perPage')
            )
        );

        return $organizations->toResourceCollection(OrganizationResource::class)->additional([
            'status' => 'success',
            'message' => 'Organizations retrieved successfully.',
        ]);
    }
}
