<?php

namespace Features\Tenant\Shared\Resources;

use Features\Auth\Shared\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Related users always go through UserResource so that sensitive user
     * attributes are never serialized.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'owner_id' => $this->owner_id,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'owner' => new UserResource($this->whenLoaded('owner')),
            'users_count' => $this->whenCounted('users'),
            'users' => $this->whenLoaded('users', fn () => $this->users->map(fn ($user) => [
                ...(new UserResource($user))->toArray($request),
                'role_id' => $user->pivot?->role_id,
                'joined_at' => $user->pivot?->created_at,
            ])->values()),
        ];
    }
}
