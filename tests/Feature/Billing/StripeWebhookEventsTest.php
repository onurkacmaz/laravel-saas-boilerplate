<?php

use App\Models\Organization;
use Features\Subscription\Infrastructure\Eloquent\Models\Plan;
use Features\Subscription\Infrastructure\Eloquent\Models\Subscription;
use Features\Subscription\Shared\Events\PaymentFailed;
use Features\Subscription\Shared\Events\PaymentSucceeded;
use Features\Subscription\Shared\Events\SubscriptionCanceled;
use Features\Subscription\Shared\Events\SubscriptionCreated;
use Features\Subscription\Shared\Events\SubscriptionUpdated;
use Features\Subscription\Shared\Events\SubscriptionUpgraded;
use Features\Subscription\Shared\Notifications\PaymentFailedNotification;
use Features\Subscription\Shared\Notifications\PaymentSucceededNotification;
use Features\Subscription\Shared\Notifications\SubscriptionCanceledNotification;
use Features\Subscription\Shared\Notifications\SubscriptionUpgradedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function stripeSubscriptionPayload(string $type, Organization $organization, string $stripeId, ?Plan $plan, array $overrides = []): array
{
    return [
        'id' => 'evt_'.uniqid(),
        'type' => $type,
        'data' => [
            'object' => array_replace_recursive([
                'id' => $stripeId,
                'object' => 'subscription',
                'customer' => $organization->stripe_id,
                'status' => 'active',
                'current_period_start' => now()->getTimestamp(),
                'items' => [
                    'data' => [[
                        'id' => 'si_'.$stripeId,
                        'quantity' => 1,
                        'price' => ['id' => $plan?->stripe_price_id ?? 'price_unknown', 'product' => $plan?->stripe_product_id ?? 'prod_unknown'],
                    ]],
                ],
                'metadata' => [],
            ], $overrides),
        ],
    ];
}

function invoicePayload(string $type, Organization $organization, array $overrides = []): array
{
    return [
        'id' => 'evt_'.uniqid(),
        'type' => $type,
        'data' => ['object' => array_merge([
            'id' => 'in_123',
            'customer' => $organization->stripe_id,
            'amount_due' => 2900,
            'amount_paid' => 2900,
            'currency' => 'usd',
        ], $overrides)],
    ];
}

beforeEach(function (): void {
    $this->organization = Organization::factory()->create(['stripe_id' => 'cus_webhook_test']);
    $this->basic = Plan::factory()->create(['price' => 1000, 'stripe_price_id' => 'price_basic']);
    $this->pro = Plan::factory()->create(['price' => 5000, 'stripe_price_id' => 'price_pro']);
});

it('creates the local subscription from the Stripe price when metadata is missing', function (): void {
    Event::fake([SubscriptionCreated::class]);

    $this->postJson('/stripe/webhook', stripeSubscriptionPayload('customer.subscription.created', $this->organization, 'sub_new', $this->pro))
        ->assertOk();

    $subscription = Subscription::query()->where('stripe_id', 'sub_new')->firstOrFail();

    expect($subscription->organization_id)->toBe($this->organization->id)
        ->and($subscription->plan_id)->toBe($this->pro->id)
        ->and($subscription->stripe_status)->toBe('active');

    Event::assertDispatched(SubscriptionCreated::class);
});

it('ignores a plan id in the metadata that does not exist', function (): void {
    Event::fake([SubscriptionCreated::class]);

    $payload = stripeSubscriptionPayload('customer.subscription.created', $this->organization, 'sub_meta', $this->basic, [
        'metadata' => ['organization_id' => $this->organization->id, 'plan_id' => '99999999-9999-4999-9999-999999999999'],
    ]);

    $this->postJson('/stripe/webhook', $payload)->assertOk();

    expect(Subscription::query()->where('stripe_id', 'sub_meta')->value('plan_id'))->toBe($this->basic->id);
});

it('syncs plan changes and reports upgrades', function (): void {
    Notification::fake();
    Event::fake([SubscriptionUpdated::class]);

    $subscription = Subscription::factory()->create([
        'organization_id' => $this->organization->id,
        'stripe_id' => 'sub_existing',
        'plan_id' => $this->basic->id,
    ]);

    $this->postJson('/stripe/webhook', stripeSubscriptionPayload('customer.subscription.updated', $this->organization, 'sub_existing', $this->pro))
        ->assertOk();

    expect($subscription->fresh()->plan_id)->toBe($this->pro->id);

    Event::assertDispatched(SubscriptionUpdated::class);
    Notification::assertSentTo($this->organization->owner, SubscriptionUpgradedNotification::class);
});

it('does not report a downgrade as an upgrade', function (): void {
    Event::fake([SubscriptionUpgraded::class, SubscriptionUpdated::class]);

    Subscription::factory()->create([
        'organization_id' => $this->organization->id,
        'stripe_id' => 'sub_existing',
        'plan_id' => $this->pro->id,
    ]);

    $this->postJson('/stripe/webhook', stripeSubscriptionPayload('customer.subscription.updated', $this->organization, 'sub_existing', $this->basic))
        ->assertOk();

    Event::assertNotDispatched(SubscriptionUpgraded::class);
});

it('handles subscriptions that expired before completing their payment', function (): void {
    Subscription::factory()->create([
        'organization_id' => $this->organization->id,
        'stripe_id' => 'sub_incomplete',
        'stripe_status' => 'incomplete',
    ]);

    $this->postJson('/stripe/webhook', stripeSubscriptionPayload('customer.subscription.updated', $this->organization, 'sub_incomplete', $this->basic, [
        'status' => 'incomplete_expired',
    ]))->assertOk();

    expect(Subscription::query()->where('stripe_id', 'sub_incomplete')->exists())->toBeFalse();
});

it('marks deleted subscriptions as canceled and notifies the owner', function (): void {
    Notification::fake();

    $subscription = Subscription::factory()->create([
        'organization_id' => $this->organization->id,
        'stripe_id' => 'sub_gone',
        'plan_id' => $this->basic->id,
    ]);

    $this->postJson('/stripe/webhook', stripeSubscriptionPayload('customer.subscription.deleted', $this->organization, 'sub_gone', $this->basic, [
        'status' => 'canceled',
    ]))->assertOk();

    expect($subscription->fresh()->stripe_status)->toBe('canceled')
        ->and($subscription->fresh()->ends_at)->not->toBeNull();

    Notification::assertSentTo($this->organization->owner, SubscriptionCanceledNotification::class);
});

it('notifies the owner and admins when an invoice payment fails', function (): void {
    Notification::fake();

    [$admin] = userInOrganization('admin', $this->organization);
    [$member] = userInOrganization('member', $this->organization);

    $this->postJson('/stripe/webhook', invoicePayload('invoice.payment_failed', $this->organization))->assertOk();

    Notification::assertSentTo($this->organization->owner, PaymentFailedNotification::class);
    Notification::assertSentTo($admin, PaymentFailedNotification::class);
    Notification::assertNotSentTo($member, PaymentFailedNotification::class);
});

it('notifies the owner about successful invoice payments', function (): void {
    Notification::fake();

    $this->postJson('/stripe/webhook', invoicePayload('invoice.payment_succeeded', $this->organization))->assertOk();

    Notification::assertSentTo($this->organization->owner, PaymentSucceededNotification::class);
});

it('reports standalone payment intents but not those of invoices', function (): void {
    Event::fake([PaymentSucceeded::class, PaymentFailed::class]);

    $this->postJson('/stripe/webhook', invoicePayload('payment_intent.succeeded', $this->organization, ['invoice' => 'in_123']))->assertOk();
    $this->postJson('/stripe/webhook', invoicePayload('payment_intent.payment_failed', $this->organization, ['invoice' => 'in_123']))->assertOk();

    Event::assertNotDispatched(PaymentSucceeded::class);
    Event::assertNotDispatched(PaymentFailed::class);

    $this->postJson('/stripe/webhook', invoicePayload('payment_intent.succeeded', $this->organization, ['id' => 'pi_1', 'amount_received' => 500]))->assertOk();
    $this->postJson('/stripe/webhook', invoicePayload('payment_intent.payment_failed', $this->organization, ['id' => 'pi_2']))->assertOk();

    Event::assertDispatchedTimes(PaymentSucceeded::class, 1);
    Event::assertDispatchedTimes(PaymentFailed::class, 1);
});

it('ignores events of unknown customers', function (): void {
    Event::fake([PaymentFailed::class, PaymentSucceeded::class, SubscriptionCanceled::class]);

    $stranger = new Organization(['name' => 'Ghost']);
    $stranger->stripe_id = 'cus_unknown';

    $this->postJson('/stripe/webhook', invoicePayload('invoice.payment_failed', $stranger))->assertOk();
    $this->postJson('/stripe/webhook', invoicePayload('invoice.payment_succeeded', $stranger))->assertOk();
    $this->postJson('/stripe/webhook', invoicePayload('payment_method.attached', $stranger, ['id' => 'pm_1']))->assertOk();
    $this->postJson('/stripe/webhook', stripeSubscriptionPayload('customer.subscription.deleted', $stranger, 'sub_x', null))->assertOk();
    $this->postJson('/stripe/webhook', stripeSubscriptionPayload('customer.subscription.updated', $stranger, 'sub_x', null))->assertOk();

    Event::assertNotDispatched(PaymentFailed::class);
    Event::assertNotDispatched(PaymentSucceeded::class);
    Event::assertNotDispatched(SubscriptionCanceled::class);
});
