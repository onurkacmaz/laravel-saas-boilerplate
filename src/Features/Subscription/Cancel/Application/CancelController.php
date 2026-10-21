<?php

namespace Features\Subscription\Cancel\Application;

use Dedoc\Scramble\Attributes\Group;
use Features\Subscription\Shared\Resources\SubscriptionResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Infrastructure\Tenant\TenantContext;

#[Group('Subscriptions')]
class CancelController
{
    /**
     * Cancel the current subscription.
     *
     * By default the subscription stays active until the end of the billing
     * period. Send `immediately=true` to cancel right away.
     */
    public function __invoke(Request $request, CancelHandler $handler): JsonResource
    {
        $subscription = $handler(
            command: new Cancel(
                organization: TenantContext::getOrganization(),
                immediately: $request->boolean('immediately'),
            )
        );

        return $subscription->toResource(SubscriptionResource::class)->additional([
            'status' => 'success',
            'message' => __('Subscription cancelled successfully'),
        ]);
    }
}
