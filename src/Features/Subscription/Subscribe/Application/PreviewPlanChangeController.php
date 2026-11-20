<?php

namespace Features\Subscription\Subscribe\Application;

use Dedoc\Scramble\Attributes\Group;
use Features\Subscription\Infrastructure\Eloquent\Models\Plan;
use Illuminate\Http\JsonResponse;
use Infrastructure\Tenant\TenantContext;
use Symfony\Component\HttpFoundation\Response;

#[Group('Subscriptions')]
class PreviewPlanChangeController
{
    /**
     * Preview a plan change.
     *
     * What subscribing to the plan would charge the payment method on file at
     * once, when it moves a paid subscription to another paid plan. `data` is
     * null when the plan goes through checkout instead, or the payment
     * provider cannot preview the change. Nothing is changed.
     */
    public function __invoke(PreviewPlanChangeRequest $request, SubscribeHandler $handler): JsonResponse
    {
        $organization = TenantContext::getOrganization();

        if (! $organization) {
            return response()->json([
                'status' => 'error',
                'message' => __('Organization context is required.'),
            ], Response::HTTP_BAD_REQUEST);
        }

        $preview = $handler->preview($organization, Plan::query()->findOrFail($request->validated('plan_id')));

        return response()->json([
            'status' => 'success',
            'data' => $preview?->toArray(),
        ]);
    }
}
