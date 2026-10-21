<?php

namespace Features\Subscription\Infrastructure\Middleware;

use Closure;
use Features\Subscription\Shared\Support\ResourceLimits;
use Illuminate\Http\Request;
use Infrastructure\Tenant\TenantContext;
use Symfony\Component\HttpFoundation\Response;

class RequirePlanFeature
{
    /**
     * Ensure the organization's plan includes every given feature (the plan's
     * `features` list).
     *
     * Usage: ->middleware('plan.feature:webhooks')
     */
    public function handle(Request $request, Closure $next, string ...$features): Response
    {
        $organization = TenantContext::getOrganization();

        if (! $organization) {
            return response()->json([
                'message' => 'Organization context is required.',
            ], 403);
        }

        $limits = new ResourceLimits($organization);

        foreach ($features as $feature) {
            if (! $limits->hasFeature($feature)) {
                return response()->json([
                    'message' => "Your plan does not include the {$feature} feature.",
                    'feature' => $feature,
                    'upgrade_required' => true,
                ], 402); // 402 Payment Required
            }
        }

        return $next($request);
    }
}
