<?php

namespace Features\Subscription\Shared\Support;

/**
 * The frontend pages billing sends people to.
 */
class BillingUrls
{
    public static function frontend(): string
    {
        return rtrim((string) (config('app.frontend_url') ?: config('app.url')), '/');
    }

    public static function success(string $sessionId): string
    {
        return sprintf('%s/subscriptions/success?session_id=%s', self::frontend(), $sessionId);
    }

    public static function cancel(): string
    {
        return self::frontend().'/subscriptions/cancel';
    }

    /** The billing page the provider's customer portal returns to. */
    public static function portalReturn(): string
    {
        return self::frontend().'/'.ltrim((string) config('services.stripe.portal_return_path', '/settings/billing'), '/');
    }
}
