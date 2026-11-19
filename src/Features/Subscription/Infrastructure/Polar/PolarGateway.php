<?php

namespace Features\Subscription\Infrastructure\Polar;

use App\Models\Organization;
use Carbon\CarbonInterface;
use Features\Subscription\Infrastructure\Eloquent\Models\Plan;
use Features\Subscription\Shared\Contracts\BillingGateway;
use Features\Subscription\Shared\Contracts\BillingSubscription;
use Features\Subscription\Shared\Support\BillingUrls;
use Features\Subscription\Shared\Support\PaymentGracePeriod;
use Features\Subscription\Subscribe\Application\CheckoutSession;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Polar, through its HTTP API. Polar is the merchant of record: it collects
 * the taxes and pays the seller out.
 *
 * The organization is Polar's customer, under its own id as the external
 * customer ID; each paid plan is sold as a Polar product.
 */
class PolarGateway implements BillingGateway
{
    public function __construct(
        private readonly PolarApi $api = new PolarApi,
    ) {}

    public function name(): string
    {
        return 'polar';
    }

    public function priceId(Plan $plan): ?string
    {
        return $plan->polar_product_id;
    }

    public function subscriptions(Organization $organization): Collection
    {
        return $this->query($organization)->get();
    }

    public function activeSubscription(Organization $organization): ?BillingSubscription
    {
        return $this->query($organization)
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $query) => $query
                    ->whereIn('status', [PolarSubscription::STATUS_ACTIVE, PolarSubscription::STATUS_TRIALING])
                    ->where(fn (Builder $query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now())))
                ->orWhere(fn (Builder $query) => PaymentGracePeriod::ongoing($query, 'status')))
            ->reorder()
            ->orderByRaw('case when status = ? then 1 else 0 end', [PolarSubscription::STATUS_PAST_DUE])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->with('plan')
            ->first();
    }

    public function paymentGracePeriodsEnded(): Collection
    {
        return PolarSubscription::query()
            ->where(fn (Builder $query) => PaymentGracePeriod::endedUnreported($query, 'status'))
            ->with('plan')
            ->get();
    }

    public function trialsEndingBetween(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return PolarSubscription::query()
            ->where('status', PolarSubscription::STATUS_TRIALING)
            ->whereBetween('trial_ends_at', [$from, $to])
            ->with(['organization.owner', 'plan'])
            ->get();
    }

    public function hasUsedTrial(Organization $organization): bool
    {
        return $this->query($organization)->where('had_trial', true)->exists();
    }

    /**
     * A Polar checkout session for the plan's product. The plan's trial is set
     * on the checkout, so it overrides any trial set on the product; without
     * it, the checkout offers no trial at all.
     *
     * A quantity is sent as the number of seats: the product must then be
     * priced per seat.
     *
     * @throws RequestException
     */
    public function checkout(Organization $organization, Plan $plan, bool $withTrial, ?int $quantity, array $metadata): CheckoutSession
    {
        $trial = $withTrial && $plan->trial_days > 0
            ? ['allow_trial' => true, 'trial_interval' => 'day', 'trial_interval_count' => $plan->trial_days]
            : ['allow_trial' => false];

        $checkout = $this->api->createCheckout(array_filter([
            'products' => [$this->priceId($plan)],
            'external_customer_id' => $organization->id,
            'customer_name' => $organization->name,
            'customer_email' => $organization->owner?->email,
            // Copied by Polar to the subscription the checkout creates.
            'metadata' => array_map('strval', $metadata),
            'seats' => $quantity,
            ...$trial,
            'success_url' => BillingUrls::frontend().'/subscriptions/success?session_id={CHECKOUT_ID}',
            'return_url' => BillingUrls::cancel(),
        ], fn (mixed $value) => $value !== null));

        if (empty($checkout['url']) || empty($checkout['id'])) {
            throw new RuntimeException('Polar returned no checkout URL.');
        }

        return new CheckoutSession(
            id: $checkout['id'],
            url: $checkout['url'],
            expiresAt: isset($checkout['expires_at'])
                ? Carbon::parse($checkout['expires_at'])->getTimestamp()
                : now()->addHour()->getTimestamp(),
            customerId: $checkout['customer_id'] ?? null,
        );
    }

    /**
     * Polar prorates the change: the difference goes on the next invoice,
     * unless the billing interval changes, when it is charged at once. Polar
     * leaves the subscription unchanged when that payment fails.
     */
    public function swap(BillingSubscription $subscription, Plan $plan): void
    {
        /** @var PolarSubscription $subscription */

        // Choosing the plan again while it is scheduled to end resumes it;
        // Polar also refuses to change the product of such a subscription.
        if ($subscription->onGracePeriod()) {
            $subscription->stopCancelation();
        }

        if ($subscription->plan_id === $plan->id) {
            return;
        }

        try {
            $subscription->updateOnPolar([
                'product_id' => $this->priceId($plan),
                'proration_behavior' => 'prorate',
            ]);
        } catch (RequestException $exception) {
            // 402: the charge failed or needs the customer to authenticate.
            if ($exception->response->status() !== 402) {
                throw $exception;
            }

            report($exception);

            throw ValidationException::withMessages([
                'plan_id' => __('Your card was declined. Update your payment method and try again.'),
            ]);
        }
    }

    /**
     * A customer portal session of the organization, which links back to the
     * given address.
     */
    public function portalUrl(Organization $organization, string $returnUrl): string
    {
        // Free plans never reach Polar, so there is no customer to manage.
        if (! $this->query($organization)->exists()) {
            throw new NotFoundHttpException(__('There is no billing account yet: subscribe to a paid plan first.'));
        }

        $session = $this->api->createCustomerSession($organization->id, $returnUrl);

        if (empty($session['customer_portal_url'])) {
            throw new RuntimeException('Polar returned no customer portal URL.');
        }

        return $session['customer_portal_url'];
    }

    /**
     * @return Builder<PolarSubscription>
     */
    private function query(Organization $organization): Builder
    {
        return PolarSubscription::query()
            ->where('organization_id', $organization->getKey())
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }
}
