<?php

namespace Features\Tenant\AddUserToOrganization\Application;

use Dedoc\Scramble\Attributes\Group;
use Features\Auth\Shared\Resources\UserResource;
use Illuminate\Http\JsonResponse;

#[Group('Organizations')]
class AddUserToOrganizationController
{
    /**
     * Add a user to an organization.
     */
    public function __invoke(AddUserToOrganizationRequest $request, string $organizationId, AddUserToOrganizationHandler $handler): JsonResponse
    {
        $user = $handler(
            command: new AddUserToOrganization(
                organizationId: $organizationId,
                userId: $request->input('user_id'),
                roleId: $request->input('role_id')
            )
        );

        return $user->toResource(UserResource::class)->additional([
            'status' => 'success',
            'message' => 'User added to organization successfully.',
        ])->response()->setStatusCode(201);
    }
}
