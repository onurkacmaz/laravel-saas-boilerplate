<?php

namespace Features\Tenant\GetInvitations\Application;

use Dedoc\Scramble\Attributes\Group;
use Features\Tenant\Shared\Resources\InvitationResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Throwable;

#[Group('Invitations')]
class GetInvitationsController
{
    /**
     * Get all invitations for the current organization.
     *
     * @throws Throwable
     */
    public function __invoke(GetInvitationsHandler $handler): ResourceCollection
    {
        return $handler(
            command: new GetInvitations
        )->toResourceCollection(InvitationResource::class)->additional([
            'status' => 'success',
            'message' => 'Invitations retrieved successfully.',
        ]);
    }
}
