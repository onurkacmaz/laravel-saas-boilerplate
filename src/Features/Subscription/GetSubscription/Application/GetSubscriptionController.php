<?php

namespace Features\Subscription\GetSubscription\Application;

use Dedoc\Scramble\Attributes\Group;
use Features\Subscription\Shared\Resources\SubscriptionResource;
use Illuminate\Http\Resources\Json\JsonResource;

#[Group('Subscriptions')]
class GetSubscriptionController
{
    /**
     * Get subscription for current organization
     */
    public function __invoke(GetSubscriptionHandler $handler): JsonResource
    {
        $subscription = $handler(
            command: new GetSubscription
        );

        return $subscription->toResource(SubscriptionResource::class)->additional([
            'status' => 'success',
            'message' => 'Subscription retrieved',
        ]);
    }
}
