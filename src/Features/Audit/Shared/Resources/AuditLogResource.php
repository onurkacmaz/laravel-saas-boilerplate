<?php

namespace Features\Audit\Shared\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event' => $this->event,
            'description' => $this->description,
            'user' => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ] : null,
            'organization' => $this->organization ? [
                'id' => $this->organization->id,
                'name' => $this->organization->name,
            ] : null,
            'auditable' => $this->auditable_type ? [
                'type' => class_basename($this->auditable_type),
                'id' => $this->auditable_id,
            ] : null,
            'changes' => $this->changes,
            'metadata' => $this->metadata ?? [],
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
