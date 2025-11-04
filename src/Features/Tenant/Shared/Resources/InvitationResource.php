<?php

namespace Features\Tenant\Shared\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvitationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'organization' => new OrganizationResource($this->whenLoaded('organization')),
            'email' => $this->email,
            'invited_by' => $this->invited_by,
            'role_id' => $this->role_id,
            'status' => $this->status,
            'token' => $this->when(
                $request->routeIs('api.v1.invitations.show-by-token'),
                $this->token
            ),
            'expires_at' => $this->expires_at?->toISOString(),
            'accepted_at' => $this->accepted_at?->toISOString(),
            'is_pending' => $this->isPending(),
            'is_expired' => $this->isExpired(),
            'can_be_accepted' => $this->canBeAccepted(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
