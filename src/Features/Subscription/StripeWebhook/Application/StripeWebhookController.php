<?php

namespace Features\Subscription\StripeWebhook\Application;

use App\Models\Organization;
use Dedoc\Scramble\Attributes\Group;
use Features\Subscription\Infrastructure\Eloquent\Models\Plan;
use Features\Subscription\Shared\Events\PaymentFailed;
use Features\Subscription\Shared\Events\PaymentSucceeded;
use Features\Subscription\Shared\Events\SubscriptionCanceled;
use Features\Subscription\Shared\Events\SubscriptionCreated;
use Features\Subscription\Shared\Events\SubscriptionUpdated;
use Features\Subscription\Shared\Events\SubscriptionUpgraded;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierController;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

#[Group('Stripe Webhooks')]
class StripeWebhookController extends CashierController
{
    /**
     * Handle customer subscription created.
     */
    public function handleCustomerSubscriptionCreated(array $payload): Response
    {
        $response = parent::handleCustomerSubscriptionCreated($payload) ?? $this->successMethod();

        $data = $payload['data']['object'] ?? [];
        $metadata = $data['metadata'] ?? [];

        [$organization, $subscription] = $this->resolveSubscription($data);

        if (! $organization) {
            return $response;
        }

        if ($subscription) {
            $updates = [];

            if ($planId = $this->resolvePlanId($data)) {
                $updates['plan_id'] = $planId;
            }

            $startTimestamp = $data['current_period_start'] ?? $data['start_date'] ?? null;

            if ($startTimestamp) {
                $updates['starts_at'] = Carbon::createFromTimestamp($startTimestamp);
            }

            if (! empty($updates)) {
                $subscription->forceFill($updates)->save();
            }

            event(new SubscriptionCreated($subscription->fresh(['plan'])));
        }

        if (isset($data['id'])) {
            $this->cancelPreviousSubscription($organization, $metadata, $data['id']);
        }

        return $response;
    }

    public function handleCustomerSubscriptionUpdated(array $payload): Response
    {
        // Cashier returns null (instead of a response) when it deletes an
        // "incomplete_expired" subscription.
        $response = parent::handleCustomerSubscriptionUpdated($payload) ?? $this->successMethod();

        [$organization, $subscription] = $this->resolveSubscription($payload['data']['object'] ?? []);

        if (! $organization || ! $subscription) {
            return $response;
        }

        // Keep the local plan in sync when the price changes (e.g. plan switch
        // from the Stripe customer portal).
        $planId = $this->resolvePlanId($payload['data']['object'] ?? []);
        $oldPlan = $subscription->plan;

        if ($planId && $planId !== $subscription->plan_id) {
            $subscription->forceFill(['plan_id' => $planId])->save();
        }

        $subscription = $subscription->fresh(['plan']);

        event(new SubscriptionUpdated($subscription));

        if ($oldPlan && $subscription->plan && $subscription->plan->price > $oldPlan->price) {
            event(new SubscriptionUpgraded($subscription, $oldPlan, $subscription->plan));
        }

        return $response;
    }

    public function handleCustomerSubscriptionDeleted(array $payload): Response
    {
        $response = parent::handleCustomerSubscriptionDeleted($payload) ?? $this->successMethod();

        [$organization, $subscription] = $this->resolveSubscription($payload['data']['object'] ?? []);

        if ($organization && $subscription) {
            event(new SubscriptionCanceled($subscription->fresh(['plan'])));
        }

        return $response;
    }

    /**
     * Find the organization and local subscription a Stripe subscription
     * payload refers to (by metadata first, then by Stripe customer id).
     *
     * @return array{0: Organization|null, 1: \Features\Subscription\Infrastructure\Eloquent\Models\Subscription|null}
     */
    protected function resolveSubscription(array $data): array
    {
        $organizationId = $data['metadata']['organization_id'] ?? null;
        $organization = null;

        if (is_string($organizationId) && Str::isUuid($organizationId)) {
            $organization = Organization::query()->find($organizationId);
        }

        if (! $organization && ! empty($data['customer']) && is_string($data['customer'])) {
            $organization = Organization::query()->where('stripe_id', $data['customer'])->first();
        }

        if (! $organization || empty($data['id'])) {
            return [$organization, null];
        }

        $subscription = $organization->subscriptions()
            ->where('stripe_id', $data['id'])
            ->first();

        return [$organization, $subscription];
    }

    /**
     * Resolve the local plan from the metadata, falling back to the Stripe price.
     */
    protected function resolvePlanId(array $data): ?string
    {
        $planId = $data['metadata']['plan_id'] ?? null;

        if (is_string($planId) && Str::isUuid($planId) && Plan::query()->whereKey($planId)->exists()) {
            return $planId;
        }

        $priceId = $data['items']['data'][0]['price']['id'] ?? $data['plan']['id'] ?? null;

        if (! is_string($priceId) || $priceId === '') {
            return null;
        }

        return Plan::query()->where('stripe_price_id', $priceId)->value('id');
    }

    public function handleInvoicePaymentSucceeded(array $payload): Response
    {
        $data = $payload['data']['object'];
        $customerId = $data['customer'] ?? null;

        if ($customerId) {
            $organization = Organization::query()->where('stripe_id', $customerId)->first();
            if ($organization) {
                event(new PaymentSucceeded($organization, $data));
            }
        }

        return new JsonResponse(['message' => 'Webhook handled'], 200);
    }

    /**
     * Handle invoice payment failed.
     */
    public function handleInvoicePaymentFailed(array $payload): Response
    {
        $data = $payload['data']['object'];
        $customerId = $data['customer'] ?? null;

        if ($customerId) {
            $organization = Organization::query()->where('stripe_id', $customerId)->first();
            if ($organization) {
                event(new PaymentFailed($organization, $data));
            }
        }

        return new JsonResponse(['message' => 'Webhook handled'], 200);
    }

    /**
     * Handle payment intent succeeded.
     */
    public function handlePaymentIntentSucceeded(array $payload): Response
    {
        $data = $payload['data']['object'];
        $customerId = $data['customer'] ?? null;

        // Payment intents of subscription invoices are already reported by the
        // matching invoice.* event; notifying twice would duplicate e-mails.
        if ($customerId && empty($data['invoice'])) {
            $organization = Organization::query()->where('stripe_id', $customerId)->first();
            if ($organization) {
                event(new PaymentSucceeded($organization, $data));
            }
        }

        return new JsonResponse(['message' => 'Webhook handled'], 200);
    }

    /**
     * Handle payment intent payment failed.
     */
    public function handlePaymentIntentPaymentFailed(array $payload): Response
    {
        $data = $payload['data']['object'];
        $customerId = $data['customer'] ?? null;

        // Payment intents of subscription invoices are already reported by the
        // matching invoice.* event; notifying twice would duplicate e-mails.
        if ($customerId && empty($data['invoice'])) {
            $organization = Organization::query()->where('stripe_id', $customerId)->first();
            if ($organization) {
                event(new PaymentFailed($organization, $data));
            }
        }

        return new JsonResponse(['message' => 'Webhook handled'], 200);
    }

    /**
     * Handle payment method attached.
     */
    public function handlePaymentMethodAttached(array $payload): Response
    {
        $data = $payload['data']['object'];
        $customerId = $data['customer'] ?? null;

        if ($customerId) {
            $organization = Organization::query()->where('stripe_id', $customerId)->first();
            $organization?->updateDefaultPaymentMethod($data['id']);
        }

        return new JsonResponse(['message' => 'Webhook handled'], 200);
    }

    protected function cancelPreviousSubscription(Organization $organization, array $metadata, string $newStripeId): void
    {
        $previousLocalId = $metadata['previous_subscription_id'] ?? null;
        $previousStripeId = $metadata['previous_subscription_stripe_id'] ?? null;

        if (! $previousLocalId && ! $previousStripeId) {
            return;
        }

        $previousSubscription = null;

        if ($previousLocalId) {
            $previousSubscription = $organization->subscriptions()
                ->withTrashed()
                ->firstWhere('id', $previousLocalId);
        }

        if (! $previousSubscription && $previousStripeId) {
            $previousSubscription = $organization->subscriptions()
                ->withTrashed()
                ->firstWhere('stripe_id', $previousStripeId);
        }

        if (! $previousSubscription || $previousSubscription->stripe_id === $newStripeId) {
            return;
        }

        try {
            $previousSubscription->cancelNow();
        } catch (Throwable $exception) {
            report($exception);

            $previousSubscription->markAsCanceled();
        }
    }
}
