<?php

namespace Features\Subscription\Infrastructure\Polar;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * The few calls of Polar's API the application makes, pinned to the API
 * version of services.polar.api_version. Polar's PHP SDK is not meant for
 * production yet, so its HTTP API is used directly.
 *
 * Every call throws a RequestException when Polar answers with an error.
 */
class PolarApi
{
    public const string PRODUCTION_URL = 'https://api.polar.sh/v1';

    public const string SANDBOX_URL = 'https://sandbox-api.polar.sh/v1';

    /**
     * Create a checkout session.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed> the checkout session
     *
     * @throws RequestException
     */
    public function createCheckout(array $attributes): array
    {
        return $this->request()->post('checkouts/', $attributes)->json();
    }

    /**
     * Update a subscription: change its product, cancel it at the end of the
     * period or take the cancellation back.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed> the subscription
     *
     * @throws RequestException a 402 when the prorated charge fails: nothing changed
     */
    public function updateSubscription(string $id, array $attributes): array
    {
        return $this->request()->patch('subscriptions/'.rawurlencode($id), $attributes)->json();
    }

    /**
     * End a subscription at once.
     *
     * @return array<string, mixed> the subscription
     *
     * @throws RequestException
     */
    public function revokeSubscription(string $id): array
    {
        return $this->request()->delete('subscriptions/'.rawurlencode($id))->json();
    }

    /**
     * A session of the customer portal for the customer with this external ID.
     *
     * @return array<string, mixed> the customer session, with its customer_portal_url
     *
     * @throws RequestException
     */
    public function createCustomerSession(string $externalCustomerId, ?string $returnUrl = null): array
    {
        return $this->request()->post('customer-sessions/', array_filter([
            'external_customer_id' => $externalCustomerId,
            'return_url' => $returnUrl,
        ]))->json();
    }

    public static function baseUrl(): string
    {
        return config('services.polar.sandbox') ? self::SANDBOX_URL : self::PRODUCTION_URL;
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(self::baseUrl())
            ->withToken((string) config('services.polar.access_token'))
            ->withHeaders(['Polar-Version' => (string) config('services.polar.api_version')])
            ->acceptJson()
            ->asJson()
            ->connectTimeout(5)
            ->timeout(20)
            ->throw();
    }
}
