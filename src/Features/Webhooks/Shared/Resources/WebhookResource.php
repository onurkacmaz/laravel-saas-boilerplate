<?php

namespace Features\Webhooks\Shared\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WebhookResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'name' => $this->name,
            'url' => $this->url,
            'events' => $this->events,
            // The signing secret is only disclosed to whoever creates the webhook
            // and to the organization owner.
            'secret' => $this->when(
                $request->routeIs('api.v1.webhooks.store') || ($request->user() && $request->user()->id === $this->organization?->owner_id),
                $this->secret
            ),
            'is_active' => $this->is_active,
            'headers' => $this->headers,
            'timeout' => $this->timeout,
            'retry_limit' => $this->retry_limit,
            'last_triggered_at' => $this->last_triggered_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
