<?php

namespace Features\Subscription\StripeWebhook\Application;

use App\Models\Organization;
use Dedoc\Scramble\Attributes\Group;
use Features\Subscription\Shared\Events\PaymentFailed;
use Features\Subscription\Shared\Events\PaymentSucceeded;
use Features\Subscription\Shared\Events\SubscriptionCanceled;
use Features\Subscription\Shared\Events\SubscriptionCreated;
use Features\Subscription\Shared\Events\SubscriptionUpdated;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
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
        $response = parent::handleCustomerSubscriptionCreated($payload);

        $data = $payload['data']['object'];
        $metadata = $data['metadata'] ?? [];
        $organizationId = $metadata['organization_id'] ?? null;

        if (! $organizationId) {
            return $response;
        }

        $organization = Organization::query()->find($organizationId);

        if (! $organization) {
            return $response;
        }

        $subscription = $organization->subscriptions()
            ->where('stripe_id', $data['id'])
            ->first();

        if ($subscription) {
            $updates = [];

            if (isset($metadata['plan_id'])) {
                $updates['plan_id'] = $metadata['plan_id'];
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

        $this->cancelPreviousSubscription(
            $organization,
            $metadata,
            $data['id']
        );

        return $response;
    }

    /**
     * Handle customer subscription updated.
     */
    public function handleCustomerSubscriptionUpdated(array $payload): Response
    {
        $response = parent::handleCustomerSubscriptionUpdated($payload);

        $data = $payload['data']['object'];
        $metadata = $data['metadata'] ?? [];
        $organizationId = $metadata['organization_id'] ?? null;

        if (! $organizationId) {
            return $response;
        }

        $organization = Organization::query()->find($organizationId);

        if (! $organization) {
            return $response;
        }

        $subscription = $organization->subscriptions()
            ->where('stripe_id', $data['id'])
            ->first();

        if ($subscription) {
            event(new SubscriptionUpdated($subscription->fresh(['plan'])));
        }

        return $response;
    }

    /**
     * Handle customer subscription deleted.
     */
    public function handleCustomerSubscriptionDeleted(array $payload): Response
    {
        $response = parent::handleCustomerSubscriptionDeleted($payload);

        $data = $payload['data']['object'];
        $metadata = $data['metadata'] ?? [];
        $organizationId = $metadata['organization_id'] ?? null;

        if (! $organizationId) {
            return $response;
        }

        $organization = Organization::query()->find($organizationId);

        if ($organization && $subscription = $organization->subscription($data['id'])) {
            event(new SubscriptionCanceled($subscription));
        }

        return $response;
    }

    /**
     * Handle invoice payment succeeded.
     */
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

        if ($customerId) {
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

        if ($customerId) {
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
