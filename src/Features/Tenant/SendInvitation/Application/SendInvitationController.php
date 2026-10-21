<?php

namespace Features\Tenant\SendInvitation\Application;

use Dedoc\Scramble\Attributes\Group;
use Features\Tenant\Shared\Resources\InvitationResource;
use Illuminate\Http\JsonResponse;
use Infrastructure\Http\ApiError;
use Infrastructure\Tenant\TenantContext;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

#[Group('Invitations')]
class SendInvitationController
{
    /**
     * Send an invitation to join an organization.
     */
    public function __invoke(SendInvitationRequest $request, SendInvitationHandler $handler): JsonResponse
    {
        try {
            $invitation = $handler(
                command: new SendInvitation(
                    organization: TenantContext::getOrganization(),
                    email: (string) $request->validated('email'),
                    roleId: (string) $request->validated('role_id'),
                    invitedBy: $request->user()
                )
            );
        } catch (InvalidArgumentException $e) {
            return ApiError::fromException($e);
        }

        return $invitation->toResource(InvitationResource::class)->additional([
            'status' => 'success',
            'message' => __('Invitation sent successfully.'),
        ])->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
