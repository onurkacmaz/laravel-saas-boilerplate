<?php

namespace Features\Tenant\SendInvitation\Application;

use Dedoc\Scramble\Attributes\Group;
use Features\Tenant\Shared\Resources\InvitationResource;
use Illuminate\Http\JsonResponse;
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
                    email: $request->validated('email'),
                    roleId: $request->validated('role_id'),
                    invitedBy: $request->user()
                )
            );
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $e->getCode() ?? Response::HTTP_BAD_REQUEST);
        }

        return $invitation->toResource(InvitationResource::class)->additional([
            'status' => 'success',
            'message' => 'Invitation sent successfully.',
        ])->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
