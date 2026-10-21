<?php

namespace Features\Tenant\AcceptInvitation\Application;

use Dedoc\Scramble\Attributes\Group;
use Features\Tenant\Shared\Resources\InvitationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Infrastructure\Http\ApiError;
use InvalidArgumentException;

#[Group('Invitations')]
class AcceptInvitationController
{
    /**
     * Accept an invitation.
     */
    public function __invoke(Request $request, string $token, AcceptInvitationHandler $handler): JsonResource|JsonResponse
    {
        try {
            $invitation = $handler(
                command: new AcceptInvitation(
                    token: $token,
                    user: $request->user()
                )
            );
        } catch (InvalidArgumentException $e) {
            return ApiError::fromException($e);
        }

        return $invitation->toResource(InvitationResource::class)->additional([
            'status' => 'success',
            'message' => __('Invitation accepted successfully'),
        ]);
    }
}
