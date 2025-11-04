<?php

namespace Features\Webhooks\Shared\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WebhookDeliveryResource extends JsonResource
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
            'webhook_id' => $this->webhook_id,
            'event_name' => $this->event_name,
            'payload' => $this->payload,
            'status' => $this->status,
            'status_code' => $this->status_code,
            'response_body' => $this->response_body,
            'error_message' => $this->error_message,
            'attempt' => $this->attempt,
            'delivered_at' => $this->delivered_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
