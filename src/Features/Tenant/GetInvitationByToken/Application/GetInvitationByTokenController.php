<?php

namespace Features\Tenant\GetInvitationByToken\Application;

use Dedoc\Scramble\Attributes\Group;
use Features\Tenant\Shared\Resources\InvitationResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

#[Group('Invitations')]
class GetInvitationByTokenController
{
    /**
     * Get an invitation by token.
     */
    public function __invoke(Request $request, string $token, GetInvitationByTokenHandler $handler): JsonResource
    {
        $invitation = $handler(
            command: new GetInvitationByToken(
                token: $token
            )
        );

        return $invitation->toResource(InvitationResource::class)->additional([
            'status' => 'success',
            'message' => __('Invitation retrieved successfully.'),
        ]);
    }
}
