<?php

namespace Features\Subscription\Infrastructure\Paddle;

use App\Models\Organization;
use Carbon\CarbonInterface;
use Features\Subscription\Infrastructure\Eloquent\Models\Plan;
use Features\Subscription\Shared\Contracts\BillingGateway;
use Features\Subscription\Shared\Contracts\BillingSubscription;
use Features\Subscription\Subscribe\Application\CheckoutSession;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Laravel\Paddle\Cashier;
use Laravel\Paddle\Exceptions\PaddleException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Paddle Billing through Laravel Cashier Paddle. Paddle is the merchant of
 * record: it collects the taxes and pays the seller out.
 */
class PaddleGateway implements BillingGateway
{
    public function name(): string
    {
        return 'paddle';
    }

    public function priceId(Plan $plan): ?string
    {
        return $plan->paddle_price_id;
    }

    public function subscriptions(Organization $organization): Collection
    {
        return $this->query($organization)->get();
    }

    public function activeSubscription(Organization $organization): ?BillingSubscription
    {
        return $this->query($organization)
            ->whereIn('status', [PaddleSubscription::STATUS_ACTIVE, PaddleSubscription::STATUS_TRIALING])
            ->with('plan')
            ->first();
    }

    public function trialsEndingBetween(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return PaddleSubscription::query()
            ->where('status', PaddleSubscription::STATUS_TRIALING)
            ->whereBetween('trial_ends_at', [$from, $to])
            ->with(['organization.owner', 'plan'])
            ->get();
    }

    public function hasUsedTrial(Organization $organization): bool
    {
        return $this->query($organization)->where('had_trial', true)->exists();
    }

    /**
     * A Paddle transaction for the plan's price. Its checkout URL is the
     * default payment link set in Paddle, which opens Paddle Checkout on it
     * (see the paddle.checkout route).
     */
    public function checkout(Organization $organization, Plan $plan, bool $withTrial, ?int $quantity, array $metadata): CheckoutSession
    {
        $customer = PaddleBillable::of($organization)->createAsCustomer();

        $priceId = $withTrial && $plan->paddle_trial_price_id ? $plan->paddle_trial_price_id : $plan->paddle_price_id;

        $transaction = Cashier::api('POST', 'transactions', [
            'items' => [['price_id' => $priceId, 'quantity' => $quantity ?? 1]],
            'customer_id' => $customer->paddle_id,
            'custom_data' => [...$metadata, 'subscription_type' => 'default'],
        ])['data'];

        $url = $transaction['checkout']['url'] ?? null;

        if (! $url) {
            throw new RuntimeException('Paddle returned no checkout URL: set the default payment link (Paddle > Checkout > Checkout settings) to '.route('paddle.checkout').'.');
        }

        return new CheckoutSession(
            id: $transaction['id'],
            url: $url,
            expiresAt: now()->addDay()->getTimestamp(),
            customerId: $customer->paddle_id,
        );
    }

    public function swap(BillingSubscription $subscription, Plan $plan): void
    {
        /** @var PaddleSubscription $subscription */
        $priceId = $this->priceId($plan);

        // Choosing the plan again while it is scheduled to end resumes it.
        if ($subscription->onGracePeriod()) {
            $subscription->stopCancelation();
        }

        if ($subscription->plan_id === $plan->id) {
            return;
        }

        try {
            // The difference is charged at once, as Stripe does when the
            // interval changes; Paddle leaves the subscription unchanged when
            // the payment fails.
            $subscription->prorateImmediately()->swap([[
                'price_id' => $priceId,
                'quantity' => $subscription->quantity ?? 1,
            ]]);
        } catch (PaddleException $exception) {
            // Paddle refuses the change when the prorated charge fails.
            report($exception);

            throw ValidationException::withMessages([
                'plan_id' => __('Your card was declined. Update your payment method and try again.'),
            ]);
        }
    }

    /**
     * Paddle's customer portal has no return address: its pages link back to
     * the seller's website set in Paddle.
     */
    public function portalUrl(Organization $organization, string $returnUrl): string
    {
        $customer = PaddleBillable::of($organization)->customer;

        // Free plans never reach Paddle, so there is no customer to manage.
        if (! $customer) {
            throw new NotFoundHttpException(__('There is no billing account yet: subscribe to a paid plan first.'));
        }

        $session = Cashier::api('POST', "customers/{$customer->paddle_id}/portal-sessions", [
            'subscription_ids' => $this->query($organization)->whereNot('status', PaddleSubscription::STATUS_CANCELED)->pluck('paddle_id')->all(),
        ])['data'];

        return $session['urls']['general']['overview'];
    }

    /**
     * @return Builder<PaddleSubscription>
     */
    private function query(Organization $organization): Builder
    {
        return PaddleSubscription::query()
            ->where('billable_type', (new PaddleBillable)->getMorphClass())
            ->where('billable_id', $organization->getKey())
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }
}
