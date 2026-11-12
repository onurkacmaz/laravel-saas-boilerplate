<?php

use App\Models\Organization;
use Features\Subscription\Infrastructure\Eloquent\Models\Plan;
use Features\Subscription\Infrastructure\Eloquent\Models\Subscription;
use Features\Subscription\Infrastructure\Paddle\PaddleBillable;
use Features\Subscription\Infrastructure\Paddle\PaddleSubscription;
use Features\Subscription\Shared\Events\OrganizationSubscriptionChanged;
use Features\Subscription\Shared\Support\ResourceLimits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['billing.past_due_grace_days' => 7]);

    Route::middleware(['api', 'auth:sanctum', 'tenant.context', 'subscription.required'])
        ->get('/api/_test/grace-premium', fn () => response()->json(['ok' => true]));

    [$this->admin, $this->organization] = userInOrganization('owner');
    $this->organization->forceFill(['stripe_id' => 'cus_grace'])->save();

    $this->pro = Plan::factory()->create([
        'price' => 2900,
        'stripe_price_id' => 'price_pro_grace',
        'paddle_price_id' => 'pri_pro_grace',
        'limits' => ['users' => 50],
    ]);
});

function graceStripeSubscription(array $attributes = []): Subscription
{
    return Subscription::factory()->create([
        'organization_id' => test()->organization->id,
        'stripe_id' => 'sub_grace',
        'stripe_price' => 'price_pro_grace',
        'plan_id' => test()->pro->id,
        ...$attributes,
    ]);
}

function graceStripeWebhook(string $status): array
{
    return [
        'id' => 'evt_'.uniqid(),
        'type' => 'customer.subscription.updated',
        'data' => ['object' => [
            'id' => 'sub_grace',
            'object' => 'subscription',
            'customer' => 'cus_grace',
            'status' => $status,
            'current_period_start' => now()->getTimestamp(),
            'items' => ['data' => [[
                'id' => 'si_grace',
                'quantity' => 1,
                'price' => ['id' => 'price_pro_grace', 'product' => 'prod_grace'],
            ]]],
            'metadata' => [],
        ]],
    ];
}

function gracePaddleSubscription(array $attributes = []): PaddleSubscription
{
    /** @var PaddleSubscription $subscription */
    $subscription = PaddleBillable::of(test()->organization)->subscriptions()->create([
        'type' => 'default',
        'paddle_id' => 'sub_paddle_grace',
        'status' => 'active',
        'plan_id' => test()->pro->id,
        ...$attributes,
    ]);
    $subscription->items()->create(['product_id' => 'pro_1', 'price_id' => 'pri_pro_grace', 'status' => 'active', 'quantity' => 1]);

    return $subscription;
}

function gracePaddleWebhook(string $status): array
{
    return [
        'event_type' => 'subscription.updated',
        'data' => [
            'id' => 'sub_paddle_grace',
            'customer_id' => 'ctm_grace',
            'status' => $status,
            'items' => [['price' => ['id' => 'pri_pro_grace', 'product_id' => 'pro_1'], 'status' => 'active', 'quantity' => 1]],
            'custom_data' => null,
        ],
    ];
}

function graceLimit(): mixed
{
    return (new ResourceLimits(test()->organization->fresh()))->getLimit('users');
}

describe('with Stripe', function (): void {
    it('keeps the plan when a renewal payment fails', function (): void {
        Event::fake([OrganizationSubscriptionChanged::class]);
        graceStripeSubscription();

        $this->postJson('/stripe/webhook', graceStripeWebhook('past_due'))->assertOk();

        $subscription = Subscription::query()->where('stripe_id', 'sub_grace')->first();

        expect($subscription->past_due_at)->not->toBeNull()
            ->and($subscription->onPaymentGracePeriod())->toBeTrue()
            ->and($subscription->valid())->toBeTrue()
            ->and($this->organization->activeSubscription()?->is($subscription))->toBeTrue()
            ->and(graceLimit())->toBe(50);

        Event::assertDispatched(OrganizationSubscriptionChanged::class, fn (OrganizationSubscriptionChanged $event) => $event->plan?->is($this->pro));
    });

    it('shows the payment grace period on the subscription', function (): void {
        $this->freezeSecond();
        graceStripeSubscription(['stripe_status' => 'past_due']);

        $this->actingAs($this->admin, 'sanctum')
            ->withHeader('X-Organization-Id', $this->organization->id)
            ->getJson('/api/v1/subscriptions')
            ->assertOk()
            ->assertJsonPath('data.status', 'past_due')
            ->assertJsonPath('data.past_due_at', now()->toISOString())
            ->assertJsonPath('data.on_payment_grace_period', true)
            ->assertJsonPath('data.payment_grace_ends_at', now()->addDays(7)->toISOString());
    });

    it('keeps premium routes open during the payment grace period', function (): void {
        graceStripeSubscription(['stripe_status' => 'past_due']);

        $this->actingAs($this->admin, 'sanctum')
            ->withHeader('X-Organization-Id', $this->organization->id)
            ->getJson('/api/_test/grace-premium')
            ->assertOk();
    });

    it('drops the plan once the payment grace period is over', function (): void {
        graceStripeSubscription(['stripe_status' => 'past_due']);

        $this->travel(8)->days();

        expect($this->organization->activeSubscription())->toBeNull()
            ->and(graceLimit())->not->toBe(50);

        $this->actingAs($this->admin, 'sanctum')
            ->withHeader('X-Organization-Id', $this->organization->id)
            ->getJson('/api/_test/grace-premium')
            ->assertStatus(402);
    });

    it('drops the plan at once without a grace period', function (): void {
        config(['billing.past_due_grace_days' => 0]);
        graceStripeSubscription(['stripe_status' => 'past_due']);

        expect($this->organization->activeSubscription())->toBeNull();
    });

    it('gives no grace to subscriptions Stripe gave up on', function (string $status): void {
        graceStripeSubscription(['stripe_status' => $status]);

        expect($this->organization->activeSubscription())->toBeNull();
    })->with(['unpaid', 'incomplete']);

    it('starts the grace period from the first failure, not from the retries', function (): void {
        graceStripeSubscription(['stripe_status' => 'past_due']);
        $since = Subscription::query()->where('stripe_id', 'sub_grace')->value('past_due_at')->toISOString();

        $this->travel(3)->days();
        $this->postJson('/stripe/webhook', graceStripeWebhook('past_due'))->assertOk();

        expect(Subscription::query()->where('stripe_id', 'sub_grace')->value('past_due_at')->toISOString())->toBe($since);
    });

    it('clears the grace period once the payment goes through', function (): void {
        graceStripeSubscription(['stripe_status' => 'past_due']);

        $this->postJson('/stripe/webhook', graceStripeWebhook('active'))->assertOk();

        $subscription = Subscription::query()->where('stripe_id', 'sub_grace')->first();

        expect($subscription->past_due_at)->toBeNull()
            ->and($subscription->pastDueSince())->toBeNull()
            ->and($subscription->paymentGraceEndsAt())->toBeNull()
            ->and($subscription->onPaymentGracePeriod())->toBeFalse();
    });

    it('prefers an active subscription to a past due one', function (): void {
        graceStripeSubscription(['stripe_status' => 'past_due']);
        $active = graceStripeSubscription(['stripe_id' => 'sub_grace_2']);

        expect($this->organization->activeSubscription()?->is($active))->toBeTrue();
    });

    it('reports a payment grace period that ran out, once', function (): void {
        Event::fake([OrganizationSubscriptionChanged::class]);
        $expired = graceStripeSubscription(['stripe_status' => 'past_due']);
        $this->travel(8)->days();
        $other = Organization::factory()->create();
        Subscription::factory()->create(['organization_id' => $other->id, 'stripe_status' => 'past_due']);

        $this->artisan('subscriptions:end-payment-grace')
            ->expectsOutput('Ended 1 payment grace period(s).')
            ->assertSuccessful();
        $this->artisan('subscriptions:end-payment-grace')
            ->expectsOutput('Ended 0 payment grace period(s).')
            ->assertSuccessful();

        expect($expired->fresh()->payment_grace_ended_at)->not->toBeNull();
        Event::assertDispatchedTimes(OrganizationSubscriptionChanged::class, 1);
        Event::assertDispatched(OrganizationSubscriptionChanged::class, fn (OrganizationSubscriptionChanged $event) => $event->organization->is($this->organization)
            && $event->plan === null);
    });

    it('reports a later failure again after a recovery', function (): void {
        graceStripeSubscription(['stripe_status' => 'past_due']);
        $this->travel(8)->days();
        $this->artisan('subscriptions:end-payment-grace')->assertSuccessful();

        $this->postJson('/stripe/webhook', graceStripeWebhook('active'))->assertOk();
        $this->postJson('/stripe/webhook', graceStripeWebhook('past_due'))->assertOk();

        $subscription = Subscription::query()->where('stripe_id', 'sub_grace')->first();

        expect($subscription->payment_grace_ended_at)->toBeNull()
            ->and($subscription->onPaymentGracePeriod())->toBeTrue();
    });
});

describe('with Paddle', function (): void {
    beforeEach(function (): void {
        config([
            'billing.provider' => 'paddle',
            'cashier.api_key' => 'pdl_test_key',
            'cashier.sandbox' => true,
            'cashier.webhook_secret' => null,
        ]);

        PaddleBillable::of($this->organization)->customer()->create([
            'paddle_id' => 'ctm_grace',
            'name' => $this->organization->name,
            'email' => 'owner@example.test',
        ]);
    });

    it('keeps the plan when a renewal payment fails', function (): void {
        Event::fake([OrganizationSubscriptionChanged::class]);
        gracePaddleSubscription();

        $this->postJson('/paddle/webhook', gracePaddleWebhook('past_due'))->assertOk();

        $subscription = PaddleSubscription::query()->where('paddle_id', 'sub_paddle_grace')->first();

        expect($subscription->past_due_at)->not->toBeNull()
            ->and($subscription->onPaymentGracePeriod())->toBeTrue()
            ->and($subscription->valid())->toBeTrue()
            ->and($this->organization->activeSubscription()?->providerId())->toBe('sub_paddle_grace')
            ->and(graceLimit())->toBe(50);

        Event::assertDispatched(OrganizationSubscriptionChanged::class, fn (OrganizationSubscriptionChanged $event) => $event->plan?->is($this->pro));
    });

    it('keeps premium routes open during the payment grace period', function (): void {
        gracePaddleSubscription(['status' => 'past_due']);

        $this->actingAs($this->admin, 'sanctum')
            ->withHeader('X-Organization-Id', $this->organization->id)
            ->getJson('/api/_test/grace-premium')
            ->assertOk();
    });

    it('drops the plan once the payment grace period is over', function (): void {
        gracePaddleSubscription(['status' => 'past_due']);

        $this->travel(8)->days();

        expect($this->organization->activeSubscription())->toBeNull()
            ->and(graceLimit())->not->toBe(50);
    });

    it('clears the grace period once the payment goes through', function (): void {
        gracePaddleSubscription(['status' => 'past_due']);

        $this->postJson('/paddle/webhook', gracePaddleWebhook('active'))->assertOk();

        expect(PaddleSubscription::query()->where('paddle_id', 'sub_paddle_grace')->value('past_due_at'))->toBeNull();
    });

    it('prefers an active subscription to a past due one', function (): void {
        gracePaddleSubscription(['paddle_id' => 'sub_paddle_old']);
        $this->travel(1)->minutes();
        // The past due one is the newest.
        gracePaddleSubscription(['status' => 'past_due']);

        expect($this->organization->activeSubscription()?->providerId())->toBe('sub_paddle_old');
    });

    it('reports a payment grace period that ran out, once', function (): void {
        Event::fake([OrganizationSubscriptionChanged::class]);
        $subscription = gracePaddleSubscription(['status' => 'past_due']);
        $this->travel(8)->days();

        $this->artisan('subscriptions:end-payment-grace')
            ->expectsOutput('Ended 1 payment grace period(s).')
            ->assertSuccessful();
        $this->artisan('subscriptions:end-payment-grace')
            ->expectsOutput('Ended 0 payment grace period(s).')
            ->assertSuccessful();

        expect($subscription->fresh()->payment_grace_ended_at)->not->toBeNull();
        Event::assertDispatchedTimes(OrganizationSubscriptionChanged::class, 1);
        Event::assertDispatched(OrganizationSubscriptionChanged::class, fn (OrganizationSubscriptionChanged $event) => $event->organization->is($this->organization)
            && $event->plan === null);
    });
});
