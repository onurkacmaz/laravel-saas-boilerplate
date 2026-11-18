<?php

namespace Features\Subscription\PolarWebhook\Application;

use App\Models\Organization;
use Dedoc\Scramble\Attributes\Group;
use Features\Subscription\Infrastructure\Eloquent\Models\Plan;
use Features\Subscription\Infrastructure\Polar\PolarSubscription;
use Features\Subscription\Infrastructure\Polar\PolarWebhookSignature;
use Features\Subscription\Shared\Events\PaymentFailed;
use Features\Subscription\Shared\Events\PaymentSucceeded;
use Features\Subscription\Shared\Events\SubscriptionCanceled;
use Features\Subscription\Shared\Events\SubscriptionCreated;
use Features\Subscription\Shared\Events\SubscriptionUpdated;
use Features\Subscription\Shared\Events\SubscriptionUpgraded;
use Features\Subscription\Subscribe\Application\AssignFreePlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Keeps the Polar subscriptions in sync and raises the application's
 * subscription and payment events, like the Stripe and Paddle webhooks.
 *
 * Every subscription.* event carries the whole subscription: each one is
 * stored as the latest state, unless a newer one is stored already.
 */
#[Group('Polar Webhooks')]
class PolarWebhookController
{
    /**
     * Handle a Polar webhook call.
     *
     * The signature is verified when POLAR_WEBHOOK_SECRET is set. In
     * production an unsigned event could come from anyone, so they are
     * refused until it is; Polar retries them meanwhile.
     */
    public function __invoke(Request $request): Response
    {
        $secret = (string) config('services.polar.webhook_secret');

        if ($secret === '') {
            if (app()->isProduction()) {
                Log::critical('A Polar webhook was refused: set POLAR_WEBHOOK_SECRET to the secret of the webhook endpoint.');

                return new Response('Webhook signing secret is not configured.', 503);
            }
        } elseif (! PolarWebhookSignature::verify($request, $secret)) {
            return new Response('Invalid signature.', 403);
        }

        $payload = json_decode($request->getContent(), true);

        if (! is_array($payload) || ! is_string($payload['type'] ?? null) || ! is_array($payload['data'] ?? null)) {
            return new Response('Invalid payload.', 400);
        }

        // Polar retries a delivery under the same webhook-id: it is handled once.
        $deliveryId = (string) $request->header('webhook-id', '');
        $deliveryKey = $deliveryId === '' ? null : 'polar-webhook:'.hash('sha256', $deliveryId);

        if ($deliveryKey && ! Cache::add($deliveryKey, true, now()->addDays(3))) {
            return new Response('', 202);
        }

        try {
            $type = $payload['type'];

            if (str_starts_with($type, 'subscription.')) {
                $this->syncSubscription($payload['data']);
            } elseif ($type === 'order.paid') {
                $this->handleOrderPaid($payload['data']);
            }
        } catch (Throwable $exception) {
            // Let Polar's retry run it again.
            if ($deliveryKey) {
                Cache::forget($deliveryKey);
            }

            throw $exception;
        }

        return new Response('', 202);
    }

    /**
     * Store the subscription as Polar describes it, and raise the events of
     * what changed.
     *
     * @param  array<string, mixed>  $data
     */
    private function syncSubscription(array $data): void
    {
        if (! is_string($data['id'] ?? null) || ! is_string($data['status'] ?? null)) {
            return;
        }

        $subscription = PolarSubscription::query()->firstWhere('polar_id', $data['id']);

        // A late delivery: the row holds this state, or a newer one, already.
        if ($subscription?->isNewerThan($data)) {
            return;
        }

        $organization = $subscription?->organization ?? $this->findOrganization($data);

        if (! $organization) {
            Log::warning('A Polar subscription matches no organization: its customer needs the organization id as external ID.', [
                'subscription' => $data['id'],
            ]);

            return;
        }

        $isNew = $subscription === null;
        $subscription ??= new PolarSubscription(['organization_id' => $organization->id]);
        $oldPlan = $subscription->plan;
        $oldStatus = $subscription->status;

        $subscription->fillFromPolar($data);

        // The checkout's plan_id names the plan once; later, the product does
        // (a plan change from the customer portal keeps the old metadata).
        if ($planId = $this->resolvePlanId($data, fromMetadata: $isNew)) {
            $subscription->plan_id = $planId;
        }

        $subscription->save();
        $subscription = $subscription->fresh(['plan']);

        if ($isNew) {
            event(new SubscriptionCreated($subscription));

            $this->cancelPreviousSubscription($organization, (array) ($data['metadata'] ?? []));

            return;
        }

        event(new SubscriptionUpdated($subscription));

        if ($oldPlan && $subscription->plan && $subscription->plan->price > $oldPlan->price) {
            event(new SubscriptionUpgraded($subscription, $oldPlan, $subscription->plan));
        }

        // Polar has no failed payment event: a renewal that could not be
        // charged turns the subscription past due.
        if ($oldStatus !== PolarSubscription::STATUS_PAST_DUE && $subscription->status === PolarSubscription::STATUS_PAST_DUE) {
            event(new PaymentFailed($organization, [
                'id' => $data['id'],
                'amount_due' => (int) ($data['amount'] ?? 0),
                'currency' => strtolower((string) ($data['currency'] ?? '')),
                'status' => $subscription->status,
                'subscription_id' => $data['id'],
            ]));
        }

        if ($oldStatus !== PolarSubscription::STATUS_CANCELED && $subscription->status === PolarSubscription::STATUS_CANCELED) {
            event(new SubscriptionCanceled($subscription));

            // A paid plan that ended (a downgrade, a cancellation, payment
            // retries exhausted) leaves the organization on the free plan
            // rather than without any plan.
            if (AssignFreePlan::freePlanId()) {
                app(AssignFreePlan::class)($organization);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function handleOrderPaid(array $data): void
    {
        if (! $organization = $this->findOrganization($data)) {
            return;
        }

        $total = (int) ($data['total_amount'] ?? 0);

        event(new PaymentSucceeded($organization, [
            'id' => $data['id'] ?? null,
            'amount_paid' => $total,
            'amount_due' => $total,
            'currency' => strtolower((string) ($data['currency'] ?? '')),
            'status' => $data['status'] ?? null,
            'billing_reason' => $data['billing_reason'] ?? null,
            'subscription_id' => $data['subscription_id'] ?? null,
        ]));
    }

    /**
     * The organization of a subscription or an order: the customer's external
     * ID, else the checkout's metadata.
     *
     * @param  array<string, mixed>  $data
     */
    private function findOrganization(array $data): ?Organization
    {
        $candidates = [
            $data['customer']['external_id'] ?? null,
            $data['metadata']['organization_id'] ?? null,
        ];

        foreach ($candidates as $organizationId) {
            if (is_string($organizationId) && Str::isUuid($organizationId)
                && $organization = Organization::query()->find($organizationId)) {
                return $organization;
            }
        }

        return null;
    }

    /**
     * The local plan: the plan_id of the checkout's metadata, else the plan
     * sold as the subscription's product.
     *
     * @param  array<string, mixed>  $data
     */
    private function resolvePlanId(array $data, bool $fromMetadata): ?string
    {
        $planId = $fromMetadata ? ($data['metadata']['plan_id'] ?? null) : null;

        if (is_string($planId) && Str::isUuid($planId) && Plan::query()->whereKey($planId)->exists()) {
            return $planId;
        }

        $productId = $data['product_id'] ?? null;

        if (! is_string($productId) || $productId === '') {
            return null;
        }

        return Plan::query()->where('polar_product_id', $productId)->value('id');
    }

    /**
     * The free plan the organization was on before paying ends at once.
     *
     * @param  array<string, mixed>  $metadata
     */
    private function cancelPreviousSubscription(Organization $organization, array $metadata): void
    {
        $previousId = $metadata['previous_subscription_id'] ?? null;

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
}
