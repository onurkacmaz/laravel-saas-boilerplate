<?php

namespace Features\Tenant\RejectInvitation\Application;

use Dedoc\Scramble\Attributes\Group;
use Features\Tenant\Shared\Resources\InvitationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Infrastructure\Http\ApiError;
use InvalidArgumentException;

#[Group('Invitations')]
class RejectInvitationController
{
    /**
     * Reject an invitation.
     */
    public function __invoke(Request $request, string $token, RejectInvitationHandler $handler): JsonResponse|JsonResource
    {
        try {
            $invitation = $handler(
                command: new RejectInvitation(
                    token: $token,
                    user: $request->user(),
                )
            );
        } catch (InvalidArgumentException $e) {
            return ApiError::fromException($e, 422);
        }

        return $invitation->toResource(InvitationResource::class)->additional([
            'status' => 'success',
            'message' => __('Invitation rejected successfully'),
        ]);
    }
}
