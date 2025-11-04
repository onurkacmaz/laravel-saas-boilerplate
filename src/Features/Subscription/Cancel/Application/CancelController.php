<?php

namespace Features\Subscription\Cancel\Application;

use Dedoc\Scramble\Attributes\Group;
use Features\Subscription\Shared\Resources\SubscriptionResource;
use Illuminate\Http\Resources\Json\JsonResource;
use Infrastructure\Tenant\TenantContext;

#[Group('Subscriptions')]
class CancelController
{
    /**
     * Cancel a subscription.
     */
    public function __invoke(CancelHandler $handler): JsonResource
    {
        $subscription = $handler(
            command: new Cancel(
                organization: TenantContext::getOrganization()
            )
        );

        return $subscription->toResource(SubscriptionResource::class)->additional([
            'status' => 'success',
            'message' => 'Subscription cancelled successfully',
        ]);
    }
}
