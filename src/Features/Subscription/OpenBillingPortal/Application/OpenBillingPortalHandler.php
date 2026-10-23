<?php

namespace Features\Subscription\OpenBillingPortal\Application;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class OpenBillingPortalHandler
{
    /**
     * A Stripe Customer Portal session for the organization, which returns to
     * the application's billing page.
     */
    public function __invoke(OpenBillingPortal $command): string
    {
        $organization = $command->organization;

        // Free plans never reach Stripe, so there is no customer to manage.
        if (! $organization->hasStripeId()) {
            throw new NotFoundHttpException(__('There is no billing account yet: subscribe to a paid plan first.'));
        }

        return $organization->billingPortalUrl($this->returnUrl());
    }

    private function returnUrl(): string
    {
        $base = rtrim((string) (config('app.frontend_url') ?: config('app.url')), '/');

        return $base.'/'.ltrim((string) config('services.stripe.portal_return_path', '/settings/billing'), '/');
    }
}
