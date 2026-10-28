<?php

namespace Features\Subscription\PaddleWebhook\Application;

use App\Models\Organization;
use Dedoc\Scramble\Attributes\Group;
use Features\Subscription\Infrastructure\Eloquent\Models\Plan;
use Features\Subscription\Shared\Events\PaymentFailed;
use Features\Subscription\Shared\Events\PaymentSucceeded;
use Features\Subscription\Shared\Events\SubscriptionCanceled;
use Features\Subscription\Shared\Events\SubscriptionCreated;
use Features\Subscription\Shared\Events\SubscriptionUpdated;
use Features\Subscription\Shared\Events\SubscriptionUpgraded;
use Features\Subscription\Subscribe\Application\AssignFreePlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Paddle\Cashier;
use Laravel\Paddle\Http\Controllers\WebhookController;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Keeps the Paddle subscriptions in sync (Cashier Paddle) and raises the
 * application's subscription and payment events, like the Stripe webhook.
 */
#[Group('Paddle Webhooks')]
class PaddleWebhookController extends WebhookController
{
    /**
     * Handle a Paddle webhook call.
     *
     * Cashier Paddle verifies the signature only when PADDLE_WEBHOOK_SECRET is
     * set. In production an unsigned event could come from anyone, so they
     * are refused until it is; Paddle retries them meanwhile.
     */
    public function __invoke(Request $request)
    {
        if (! config('cashier.webhook_secret') && app()->isProduction()) {
            Log::critical('A Paddle webhook was refused: set PADDLE_WEBHOOK_SECRET to the secret key of the notification destination.');

            return new Response('Webhook signing secret is not configured.', 503);
        }

        return parent::__invoke($request);
    }

    protected function handleSubscriptionCreated(array $payload)
    {
        $data = $payload['data'];

        // Paddle retries deliveries: the events are raised once.
        if ($this->subscriptionExists($data['id'])) {
            return;
        }

        parent::handleSubscriptionCreated($payload);

        if (! $subscription = $this->findSubscription($data['id'])) {
            return;
        }

        $subscription->forceFill([
            'plan_id' => $this->resolvePlanId($data),
            'had_trial' => $subscription->trial_ends_at !== null,
        ])->save();

        event(new SubscriptionCreated($subscription->fresh(['plan'])));

        if ($organization = $subscription->organization) {
            $this->cancelPreviousSubscription($organization, $data['custom_data'] ?? []);
        }
    }

    protected function handleSubscriptionUpdated(array $payload)
    {
        $data = $payload['data'];

        parent::handleSubscriptionUpdated($payload);

        if (! $subscription = $this->findSubscription($data['id'])) {
            return;
        }

        // Keep the local plan in sync when the price changes (e.g. a plan
        // switch from the customer portal).
        $oldPlan = $subscription->plan;
        $planId = $this->resolvePlanId($data, fromCustomData: false);

        if ($planId && $planId !== $subscription->plan_id) {
            $subscription->forceFill(['plan_id' => $planId])->save();
        }

        $subscription = $subscription->fresh(['plan']);

        event(new SubscriptionUpdated($subscription));

        if ($oldPlan && $subscription->plan && $subscription->plan->price > $oldPlan->price) {
            event(new SubscriptionUpgraded($subscription, $oldPlan, $subscription->plan));
        }
    }

    protected function handleSubscriptionCanceled(array $payload)
    {
        parent::handleSubscriptionCanceled($payload);

        if (! $subscription = $this->findSubscription($payload['data']['id'])) {
            return;
        }

        event(new SubscriptionCanceled($subscription->fresh(['plan'])));

        // A paid plan that ended (a downgrade, a cancellation) leaves the
        // organization on the free plan rather than without any plan.
        if (($organization = $subscription->organization) && AssignFreePlan::freePlanId()) {
            app(AssignFreePlan::class)($organization);
        }
    }

    protected function handleTransactionCompleted(array $payload)
    {
        $data = $payload['data'];

        if ($this->transactionExists($data['id'])) {
            return;
        }

        parent::handleTransactionCompleted($payload);

        if ($organization = $this->findOrganization($data)) {
            event(new PaymentSucceeded($organization, $this->payment($data)));
        }
    }

    protected function handleTransactionPaymentFailed(array $payload): void
    {
        $data = $payload['data'];

        if ($organization = $this->findOrganization($data)) {
            event(new PaymentFailed($organization, $this->payment($data)));
        }
    }

    /**
     * The organization of a transaction: from its custom data, else from the
     * Paddle customer.
     */
    private function findOrganization(array $data): ?Organization
    {
        $organizationId = $data['custom_data']['organization_id'] ?? null;

        if (is_string($organizationId) && Str::isUuid($organizationId)
            && $organization = Organization::query()->find($organizationId)) {
            return $organization;
        }

        $billable = empty($data['customer_id']) ? null : Cashier::findBillable($data['customer_id']);

        return $billable ? Organization::query()->find($billable->getKey()) : null;
    }

    /**
     * The local plan: the plan_id of the checkout's custom data, else the
     * plan sold at the subscription's price.
     */
    private function resolvePlanId(array $data, bool $fromCustomData = true): ?string
    {
        $planId = $fromCustomData ? ($data['custom_data']['plan_id'] ?? null) : null;

        if (is_string($planId) && Str::isUuid($planId) && Plan::query()->whereKey($planId)->exists()) {
            return $planId;
        }

        $priceId = $data['items'][0]['price']['id'] ?? null;

        if (! is_string($priceId) || $priceId === '') {
            return null;
        }

        return Plan::query()
            ->where('paddle_price_id', $priceId)
            ->orWhere('paddle_trial_price_id', $priceId)
            ->value('id');
    }

    /**
     * The free plan the organization was on before paying ends at once.
     */
    private function cancelPreviousSubscription(Organization $organization, array $customData): void
    {
        $previousId = $customData['previous_subscription_id'] ?? null;

        if (! is_string($previousId) || $previousId === '') {
            return;
        }

        $previous = $organization->subscriptions()->local()->withTrashed()->find($previousId);

        if (! $previous || $previous->canceled()) {
            return;
        }

        try {
            $previous->cancelNow();
        } catch (Throwable $exception) {
            report($exception);

            $previous->markAsCanceled();
        }
    }

    /**
     * A Paddle transaction in the shape the payment events carry (amounts in
     * the currency's smallest unit).
     *
     * @return array<string, mixed>
     */
    private function payment(array $data): array
    {
        // grand_total is what was charged: the total less the credit of a
        // prorated plan change.
        $totals = $data['details']['totals'] ?? [];
        $total = (int) ($totals['grand_total'] ?? $totals['total'] ?? 0);

        return [
            'id' => $data['id'] ?? null,
            'amount_paid' => $total,
            'amount_due' => $total,
            'currency' => strtolower((string) ($data['currency_code'] ?? '')),
            'status' => $data['status'] ?? null,
            'subscription_id' => $data['subscription_id'] ?? null,
        ];
    }
}
