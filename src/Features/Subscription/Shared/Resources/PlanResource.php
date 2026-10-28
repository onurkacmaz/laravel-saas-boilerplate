<?php

namespace Features\Subscription\Shared\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'price' => $this->price,
            'formatted_price' => $this->getFormattedPrice(),
            'currency' => $this->currency,
            'interval' => $this->interval->value,
            'trial_days' => $this->trial_days,
            'is_active' => $this->is_active,
            'stripe_product_id' => $this->stripe_product_id,
            'stripe_price_id' => $this->stripe_price_id,
            'paddle_price_id' => $this->paddle_price_id,
            'paddle_trial_price_id' => $this->paddle_trial_price_id,
            'features' => $this->features,
            'limits' => $this->limits,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
