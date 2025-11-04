<?php

namespace Features\Subscription\Infrastructure\Middleware;

use Closure;
use Illuminate\Http\Request;
use Infrastructure\Tenant\TenantContext;
use Symfony\Component\HttpFoundation\Response;

class RequireActiveSubscription
{
    /**
     * Handle an incoming request.
     *
     * Ensures that the organization has an active subscription.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $organization = TenantContext::getOrganization();

        if (! $organization) {
            return response()->json([
                'message' => 'Organization context is required.',
            ], 403);
        }

        if (! $organization->hasActiveSubscription()) {
            return response()->json([
                'message' => 'Active subscription required. Please subscribe to a plan.',
                'requires_subscription' => true,
            ], 402); // 402 Payment Required
        }

        $subscription = $organization->activeSubscription();

        // Check if the subscription is valid (not expired, not past due)
        if (! $subscription->hasValidSubscription()) {
            return response()->json([
                'message' => 'Your subscription is not valid. Please update your payment method or renew your subscription.',
                'subscription_status' => $subscription->status->value,
            ], 402);
        }

        return $next($request);
    }
}
