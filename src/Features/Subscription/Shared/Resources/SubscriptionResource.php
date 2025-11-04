<?php

namespace Features\Subscription\Shared\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'plan' => new PlanResource($this->whenLoaded('plan')),
            'status' => $this->status->value,
            'stripe_status' => $this->stripe_status,
            'stripe_price' => $this->stripe_price,
            'quantity' => $this->quantity,
            'cancel_at_period_end' => $this->cancel_at_period_end,
            'cancel_at' => $this->cancel_at?->toISOString(),
            'resume_at' => $this->resume_at?->toISOString(),
            'trial_ends_at' => $this->trial_ends_at?->toISOString(),
            'starts_at' => $this->starts_at?->toISOString(),
            'ends_at' => $this->ends_at?->toISOString(),
            'canceled_at' => $this->canceled_at?->toISOString(),
            'is_active' => $this->isActive(),
            'is_trialing' => $this->isTrialing(),
            'is_canceled' => $this->isCanceled(),
            'is_expired' => $this->isExpired(),
            'on_grace_period' => $this->onGracePeriod(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
