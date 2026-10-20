<?php

use App\Models\User;
use Features\Subscription\Infrastructure\Eloquent\Models\Plan;
use Features\Subscription\Infrastructure\Eloquent\Models\Subscription;
use Features\Subscription\Shared\Notifications\PaymentFailedNotification;
use Features\Subscription\Shared\Notifications\SubscriptionCreatedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Support\FakeStripeClient;

uses(RefreshDatabase::class);

/**
 * Free plan on creation → upgrade through Stripe Checkout → Stripe confirms
 * the subscription → payment fails → subscription ends.
 */
it('moves an organization from the free plan to a paid plan and back', function (): void {
    Notification::fake();
    $stripe = FakeStripeClient::install();
    $owner = User::factory()->create();

    $free = Plan::factory()->create(['slug' => 'free', 'price' => 0, 'stripe_price_id' => null, 'trial_days' => 0, 'limits' => ['users' => 1]]);
    $pro = Plan::factory()->create(['slug' => 'pro', 'price' => 2900, 'stripe_price_id' => 'price_pro', 'trial_days' => 0, 'limits' => ['users' => 25]]);

    // 1. A new organization is automatically on the free plan.
    $organizationId = $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/organizations', ['name' => 'Acme'])
        ->assertCreated()
        ->json('data.id');

    $asOwner = fn () => $this->actingAs($owner, 'sanctum')->withHeader('X-Organization-Id', $organizationId);

    $asOwner()->getJson('/api/v1/subscriptions')->assertOk()->assertJsonPath('data.plan.id', $free->id);
    $asOwner()->getJson('/api/v1/usage/limits')->assertJsonPath('data.users', 1);

    $freeSubscription = Subscription::query()->where('organization_id', $organizationId)->firstOrFail();

    // 2. The owner starts the Stripe checkout for the paid plan.
    $asOwner()->postJson('/api/v1/subscriptions', ['plan_id' => $pro->id])
        ->assertCreated()
        ->assertJsonPath('data.checkout_url', 'https://checkout.stripe.test/session');

    $checkout = $stripe->lastCall('checkout.sessions.create');
    $customerId = $checkout['customer'];
    $metadata = $checkout['subscription_data']['metadata'];

    expect($metadata['previous_subscription_id'])->toBe($freeSubscription->id);

    // 3. Stripe confirms the subscription after payment.
    $this->postJson('/stripe/webhook', [
        'type' => 'customer.subscription.created',
        'data' => ['object' => [
            'id' => 'sub_paid',
            'customer' => $customerId,
            'status' => 'active',
            'current_period_start' => now()->getTimestamp(),
            'items' => ['data' => [['id' => 'si_1', 'quantity' => 1, 'price' => ['id' => 'price_pro', 'product' => 'prod_pro']]]],
            'metadata' => $metadata,
        ]],
    ])->assertOk();

    expect($freeSubscription->fresh()->stripe_status)->toBe('canceled');

    $asOwner()->getJson('/api/v1/subscriptions')
        ->assertOk()
        ->assertJsonPath('data.plan.id', $pro->id)
        ->assertJsonPath('data.is_active', true);
    $asOwner()->getJson('/api/v1/usage/limits')->assertJsonPath('data.users', 25);

    Notification::assertSentTo($owner, SubscriptionCreatedNotification::class);

    // 4. A renewal payment fails: the owner is told to update the card.
    $this->postJson('/stripe/webhook', [
        'type' => 'invoice.payment_failed',
        'data' => ['object' => ['id' => 'in_1', 'customer' => $customerId, 'amount_due' => 2900, 'currency' => 'usd']],
    ])->assertOk();

    Notification::assertSentTo($owner, PaymentFailedNotification::class);

    // 5. Stripe gives up and deletes the subscription: no active plan anymore.
    $this->postJson('/stripe/webhook', [
        'type' => 'customer.subscription.deleted',
        'data' => ['object' => [
            'id' => 'sub_paid',
            'customer' => $customerId,
            'status' => 'canceled',
            'items' => ['data' => [['id' => 'si_1', 'price' => ['id' => 'price_pro', 'product' => 'prod_pro']]]],
            'metadata' => $metadata,
        ]],
    ])->assertOk();

    $asOwner()->getJson('/api/v1/subscriptions')->assertNotFound();
    $asOwner()->getJson('/api/v1/usage/limits')->assertJsonPath('data.users', 5);
});
