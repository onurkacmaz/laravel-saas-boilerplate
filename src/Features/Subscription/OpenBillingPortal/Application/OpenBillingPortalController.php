<?php

namespace Features\Subscription\OpenBillingPortal\Application;

use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Infrastructure\Tenant\TenantContext;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

#[Group('Subscriptions')]
class OpenBillingPortalController
{
    /**
     * Open the billing portal.
     *
     * Returns the address of a Stripe Customer Portal session, where the
     * organization updates its payment method, downloads its invoices and
     * manages its subscription. It sends people back to the billing page of
     * the frontend (`STRIPE_PORTAL_RETURN_PATH`, `/settings/billing` by
     * default). Organizations that never paid have no billing account: 404.
     */
    public function __invoke(OpenBillingPortalHandler $handler): JsonResponse
    {
        try {
            $url = $handler(new OpenBillingPortal(TenantContext::getOrganization()));
        } catch (HttpExceptionInterface $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            // Never leak payment provider details to clients.
            report($exception);

            return response()->json([
                'status' => 'error',
                'message' => __('Unable to open the billing portal. Please try again later.'),
            ], Response::HTTP_BAD_GATEWAY);
        }

        return response()->json([
            'status' => 'success',
            'message' => __('Billing portal session created'),
            'data' => ['url' => $url],
        ]);
    }
}
