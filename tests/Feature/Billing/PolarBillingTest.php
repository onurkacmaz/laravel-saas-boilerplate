<?php

use App\Models\Organization;
use Features\Subscription\Infrastructure\Eloquent\Models\Plan;
use Features\Subscription\Infrastructure\Eloquent\Models\Subscription;
use Features\Subscription\Infrastructure\Polar\PolarSubscription;
use Features\Subscription\Infrastructure\Polar\PolarWebhookSignature;
use Features\Subscription\Shared\Contracts\BillingGateway;
use Features\Subscription\Shared\Events\PaymentFailed;
use Features\Subscription\Shared\Events\PaymentSucceeded;
use Features\Subscription\Shared\Events\SubscriptionCanceled;
use Features\Subscription\Shared\Events\SubscriptionCreated;
use Features\Subscription\Shared\Events\SubscriptionUpgraded;
use Features\Subscription\Shared\Notifications\PaymentSucceededNotification;
use Features\Subscription\Shared\Notifications\SubscriptionCreatedNotification;
use Features\Subscription\Shared\Support\CancelSubscriptions;
use Features\Subscription\Shared\Support\ResourceLimits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

const POLAR_API = 'https://sandbox-api.polar.sh/v1';

beforeEach(function (): void {
    config([
        'billing.provider' => 'polar',
        'services.polar.access_token' => 'polar_oat_test',
        'services.polar.sandbox' => true,
        'services.polar.webhook_secret' => null,
        'services.polar.api_version' => '2026-10',
        'app.frontend_url' => 'https://app.example.test',
    ]);

    [$this->admin, $this->organization] = userInOrganization('owner');
    $this->free = Plan::factory()->create(['price' => 0, 'trial_days' => 0, 'stripe_price_id' => null]);
    $this->plan = Plan::factory()->create([
        'price' => 2900,
        'trial_days' => 14,
        'polar_product_id' => 'prod_pro',
    ]);
});

/**
 * A Polar timestamp, later on each call: the webhooks of a test describe
 * successive states of the subscription.
 */
function polarTime(): string
{
    static $tick = 0;

    return now()->addSeconds(++$tick)->format('Y-m-d\TH:i:s.u\Z');
}

/**
 * A subscription as Polar's API and webhooks describe it.
 */
function polarSubscriptionData(array $data = []): array
{
    return [
        'id' => 'sub_1',
        'created_at' => now()->format('Y-m-d\TH:i:s.u\Z'),
        'modified_at' => polarTime(),
        'status' => 'active',
        'amount' => 2900,
        'currency' => 'usd',
        'recurring_interval' => 'month',
        'current_period_start' => now()->toIso8601String(),
        'current_period_end' => now()->addMonth()->startOfSecond()->toIso8601String(),
        'trial_start' => null,
        'trial_end' => null,
        'cancel_at_period_end' => false,
        'canceled_at' => null,
        'started_at' => now()->toIso8601String(),
        'ends_at' => null,
        'ended_at' => null,
        'past_due_at' => null,
        'customer_id' => 'cus_1',
        'product_id' => 'prod_pro',
        'seats' => null,
        'metadata' => [],
        'customer' => ['id' => 'cus_1', 'external_id' => test()->organization->id, 'email' => 'owner@example.test'],
        ...$data,
    ];
}

function polarWebhook(string $type, array $data, array $headers = [])
{
    return test()->postJson('/polar/webhook', [
        'type' => $type,
        'timestamp' => now()->toIso8601String(),
        'api_version' => '2026-10',
        'data' => $data,
    ], $headers);
}

function polarSubscription(Organization $organization, Plan $plan, array $attributes = []): PolarSubscription
{
    return PolarSubscription::query()->create([
        'organization_id' => $organization->id,
        'plan_id' => $plan->id,
        'polar_id' => 'sub_1',
        'polar_customer_id' => 'cus_1',
        'polar_product_id' => $plan->polar_product_id,
        'status' => 'active',
        'current_period_end' => now()->addMonth(),
        ...$attributes,
    ]);
}

function polarCheckoutApi(): void
{
    Http::fake([POLAR_API.'/checkouts/' => Http::response([
        'id' => 'chk_1',
        'url' => 'https://sandbox.polar.sh/checkout/polar_c_1',
        'expires_at' => now()->addHour()->toIso8601String(),
        'customer_id' => null,
    ], 201)]);
}

function subscribeWithPolar(Plan $plan, array $extra = [])
{
    return test()->actingAs(test()->admin, 'sanctum')
        ->withHeader('X-Organization-Id', test()->organization->id)
        ->postJson('/api/v1/subscriptions', ['plan_id' => $plan->id, ...$extra]);
}

it('resolves the Polar gateway from the configuration', function (): void {
    expect(app(BillingGateway::class)->name())->toBe('polar');
});

it('opens a Polar checkout with the plan trial', function (): void {
    polarCheckoutApi();

    subscribeWithPolar($this->plan)
        ->assertCreated()
        ->assertJsonPath('data.action', 'checkout')
        ->assertJsonPath('data.session_id', 'chk_1')
        ->assertJsonPath('data.checkout_url', 'https://sandbox.polar.sh/checkout/polar_c_1');

    Http::assertSent(function (Request $request): bool {
        return $request->url() === POLAR_API.'/checkouts/'
            && $request->hasHeader('Authorization', 'Bearer polar_oat_test')
            && $request->hasHeader('Polar-Version', '2026-10')
            && $request['products'] === ['prod_pro']
            && $request['external_customer_id'] === test()->organization->id
            && $request['customer_email'] === test()->admin->email
            && $request['metadata']['organization_id'] === test()->organization->id
            && $request['metadata']['plan_id'] === test()->plan->id
            && $request['allow_trial'] === true
            && $request['trial_interval'] === 'day'
            && $request['trial_interval_count'] === 14
            && $request['success_url'] === 'https://app.example.test/subscriptions/success?session_id={CHECKOUT_ID}'
            && $request['return_url'] === 'https://app.example.test/subscriptions/cancel'
            && ! array_key_exists('seats', $request->data());
    });
});

it('offers no trial once the organization had one', function (): void {
    polarCheckoutApi();
    polarSubscription($this->organization, $this->plan, ['status' => 'canceled', 'had_trial' => true, 'ends_at' => now()->subDay()]);

    subscribeWithPolar($this->plan)->assertCreated();

    Http::assertSent(fn (Request $request) => $request['allow_trial'] === false
        && ! array_key_exists('trial_interval', $request->data()));
});

it('skips the trial when the request opts out', function (): void {
    polarCheckoutApi();

    subscribeWithPolar($this->plan, ['use_trial' => false])->assertCreated();

    Http::assertSent(fn (Request $request) => $request['allow_trial'] === false);
});

it('sends the quantity as the number of seats', function (): void {
    polarCheckoutApi();

    subscribeWithPolar($this->plan, ['quantity' => 5])->assertCreated();

    Http::assertSent(fn (Request $request) => $request['seats'] === 5);
});

it('refuses paid plans that are not sold as a Polar product', function (): void {
    $plan = Plan::factory()->create(['price' => 900, 'polar_product_id' => null]);

    subscribeWithPolar($plan)->assertUnprocessable();
});

it('fails cleanly when Polar refuses the checkout', function (): void {
    Http::fake([POLAR_API.'/checkouts/' => Http::response(['detail' => 'Unauthorized'], 401)]);

    subscribeWithPolar($this->plan)->assertStatus(502);
});

it('fails cleanly when Polar returns no checkout URL', function (): void {
    Http::fake([POLAR_API.'/checkouts/' => Http::response(['id' => 'chk_1'], 201)]);

    subscribeWithPolar($this->plan)->assertStatus(502);
});

it('creates the subscription from the webhook and ends the free plan', function (): void {
    Event::fake([SubscriptionCreated::class]);
    $free = Subscription::factory()->create([
        'organization_id' => $this->organization->id,
        'plan_id' => $this->free->id,
        'stripe_id' => Subscription::makeLocalStripeId(),
        'stripe_status' => 'active',
    ]);

    polarWebhook('subscription.created', polarSubscriptionData([
        'status' => 'trialing',
        'trial_start' => now()->toIso8601String(),
        'trial_end' => now()->addDays(14)->toIso8601String(),
        'metadata' => [
            'organization_id' => $this->organization->id,
            'plan_id' => $this->plan->id,
            'previous_subscription_id' => $free->id,
        ],
    ]))->assertAccepted();

    $subscription = PolarSubscription::query()->firstWhere('polar_id', 'sub_1');

    expect($subscription->plan_id)->toBe($this->plan->id)
        ->and($subscription->organization_id)->toBe($this->organization->id)
        ->and($subscription->had_trial)->toBeTrue()
        ->and($subscription->isTrialing())->toBeTrue()
        ->and($free->fresh()->canceled())->toBeTrue()
        ->and($this->organization->activeSubscription()->is($subscription))->toBeTrue();

    Event::assertDispatchedTimes(SubscriptionCreated::class, 1);

    $this->actingAs($this->admin, 'sanctum')
        ->withHeader('X-Organization-Id', $this->organization->id)
        ->getJson('/api/v1/subscriptions')
        ->assertOk()
        ->assertJsonPath('data.provider', 'polar')
        ->assertJsonPath('data.status', 'trialing')
        ->assertJsonPath('data.plan.id', $this->plan->id);
});

it('welcomes the organization to its trial', function (): void {
    Notification::fake();

    polarWebhook('subscription.created', polarSubscriptionData([
        'status' => 'trialing',
        'trial_start' => now()->toIso8601String(),
        'trial_end' => now()->addDays(14)->toIso8601String(),
    ]))->assertAccepted();

    Notification::assertSentTo($this->admin, SubscriptionCreatedNotification::class, function (SubscriptionCreatedNotification $notification): bool {
        return str_contains(implode(' ', $notification->toMail($this->admin)->introLines), 'trial period is active');
    });
});

it('creates the subscription once, whichever event arrives first', function (): void {
    Event::fake([SubscriptionCreated::class]);
    $created = polarSubscriptionData(['status' => 'incomplete']);
    $updated = polarSubscriptionData(['status' => 'active']);

    // The update was sent after the creation, but arrives first.
    polarWebhook('subscription.updated', $updated)->assertAccepted();
    polarWebhook('subscription.created', $created)->assertAccepted();

    expect(PolarSubscription::query()->count())->toBe(1)
        ->and(PolarSubscription::query()->value('status'))->toBe('active')
        ->and(PolarSubscription::query()->value('plan_id'))->toBe($this->plan->id);

    Event::assertDispatchedTimes(SubscriptionCreated::class, 1);
});

it('ignores a subscription of an unknown organization', function (): void {
    polarWebhook('subscription.created', polarSubscriptionData([
        'customer' => ['id' => 'cus_1', 'external_id' => null],
    ]))->assertAccepted();

    expect(PolarSubscription::query()->count())->toBe(0);
});

it('refuses a payload that is not a Polar event', function (): void {
    $this->postJson('/polar/webhook', ['hello' => 'world'])->assertBadRequest();
});

it('acknowledges the events it does not handle', function (): void {
    polarWebhook('customer.updated', ['id' => 'cus_1'])->assertAccepted();
});

it('follows a plan change made in the customer portal', function (): void {
    Event::fake([SubscriptionUpgraded::class]);
    $subscription = polarSubscription($this->organization, $this->plan);
    $business = Plan::factory()->create(['price' => 9900, 'polar_product_id' => 'prod_business']);

    polarWebhook('subscription.updated', polarSubscriptionData([
        'product_id' => 'prod_business',
        // The checkout's metadata stays on the subscription.
        'metadata' => ['plan_id' => $this->plan->id],
    ]))->assertAccepted();

    expect($subscription->fresh()->plan_id)->toBe($business->id);
    Event::assertDispatched(SubscriptionUpgraded::class);
});

it('keeps the plan until the end of the period when canceled in the portal', function (): void {
    $subscription = polarSubscription($this->organization, $this->plan);
    $endsAt = now()->addMonth()->startOfSecond();

    polarWebhook('subscription.updated', polarSubscriptionData([
        'cancel_at_period_end' => true,
        'canceled_at' => now()->toIso8601String(),
        'ends_at' => $endsAt->toIso8601String(),
    ]))->assertAccepted();

    $subscription->refresh();

    expect($subscription->onGracePeriod())->toBeTrue()
        ->and($subscription->isActive())->toBeTrue()
        ->and($subscription->cancel_at_period_end)->toBeTrue()
        ->and($subscription->cancel_at->equalTo($endsAt))->toBeTrue()
        ->and($subscription->canceled_at)->toBeNull()
        ->and($this->organization->activeSubscription()->is($subscription))->toBeTrue();
});

it('puts the organization back on the free plan when the subscription ends', function (): void {
    Event::fake([SubscriptionCanceled::class]);
    $subscription = polarSubscription($this->organization, $this->plan);

    polarWebhook('subscription.revoked', polarSubscriptionData([
        'status' => 'canceled',
        'canceled_at' => now()->toIso8601String(),
        'ended_at' => now()->toIso8601String(),
    ]))->assertAccepted();

    $active = $this->organization->activeSubscription();

    expect($active->provider())->toBe('local')
        ->and($active->plan_id)->toBe($this->free->id)
        ->and($subscription->fresh()->subscriptionStatus()->value)->toBe('expired')
        ->and($subscription->fresh()->canceled_at)->not->toBeNull();

    Event::assertDispatchedTimes(SubscriptionCanceled::class, 1);
});

it('ignores a state older than the one stored', function (): void {
    $newer = polarSubscriptionData(['status' => 'active']);
    polarSubscription($this->organization, $this->plan, ['polar_modified_at' => $newer['modified_at']]);

    $older = [...polarSubscriptionData(['status' => 'canceled', 'ended_at' => now()->toIso8601String()]), 'modified_at' => now()->subMinute()->format('Y-m-d\TH:i:s.u\Z')];

    polarWebhook('subscription.updated', $older)->assertAccepted();

    expect(PolarSubscription::query()->value('status'))->toBe('active');
});

it('switches paid plans in place, so Polar prorates the change', function (): void {
    $subscription = polarSubscription($this->organization, $this->plan);
    $yearly = Plan::factory()->create(['price' => 29000, 'polar_product_id' => 'prod_pro_yearly']);
    Http::fake([POLAR_API.'/subscriptions/sub_1' => Http::response(polarSubscriptionData(['product_id' => 'prod_pro_yearly']))]);

    subscribeWithPolar($yearly)
        ->assertCreated()
        ->assertJsonPath('data.action', 'activated');

    Http::assertSent(fn (Request $request) => $request->method() === 'PATCH'
        && $request->url() === POLAR_API.'/subscriptions/sub_1'
        && $request['product_id'] === 'prod_pro_yearly'
        && $request['proration_behavior'] === 'prorate');

    expect($subscription->fresh()->plan_id)->toBe($yearly->id)
        ->and($subscription->fresh()->polar_product_id)->toBe('prod_pro_yearly');
});

it('keeps the plan when Polar cannot charge the change', function (): void {
    $subscription = polarSubscription($this->organization, $this->plan);
    $yearly = Plan::factory()->create(['price' => 29000, 'polar_product_id' => 'prod_pro_yearly']);
    Http::fake([POLAR_API.'/subscriptions/sub_1' => Http::response(['error' => 'PaymentFailed', 'detail' => 'Your card was declined.'], 402)]);

    subscribeWithPolar($yearly)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('plan_id');

    expect($subscription->fresh()->plan_id)->toBe($this->plan->id);
});

it('reports other errors of a plan change as a provider failure', function (): void {
    polarSubscription($this->organization, $this->plan);
    $yearly = Plan::factory()->create(['price' => 29000, 'polar_product_id' => 'prod_pro_yearly']);
    Http::fake([POLAR_API.'/subscriptions/sub_1' => Http::response(['error' => 'SubscriptionLocked'], 409)]);

    subscribeWithPolar($yearly)->assertStatus(502);
});

it('moves to the free plan at the end of the paid period', function (): void {
    $subscription = polarSubscription($this->organization, $this->plan);
    $endsAt = now()->addMonth()->startOfSecond();
    Http::fake([POLAR_API.'/subscriptions/sub_1' => Http::response(polarSubscriptionData([
        'cancel_at_period_end' => true,
        'ends_at' => $endsAt->toIso8601String(),
    ]))]);

    subscribeWithPolar($this->free)
        ->assertCreated()
        ->assertJsonPath('data.action', 'scheduled');

    Http::assertSent(fn (Request $request) => $request->method() === 'PATCH'
        && $request['cancel_at_period_end'] === true);

    $subscription->refresh();

    expect($subscription->onGracePeriod())->toBeTrue()
        ->and($subscription->ends_at->equalTo($endsAt))->toBeTrue()
        ->and($this->organization->activeSubscription()->is($subscription))->toBeTrue();
});

it('resumes a subscription scheduled to end when its plan is chosen again', function (): void {
    $subscription = polarSubscription($this->organization, $this->plan, ['ends_at' => now()->addWeek()]);
    Http::fake([POLAR_API.'/subscriptions/sub_1' => Http::response(polarSubscriptionData())]);

    subscribeWithPolar($this->plan)
        ->assertCreated()
        ->assertJsonPath('data.action', 'activated');

    Http::assertSent(fn (Request $request) => $request->method() === 'PATCH'
        && $request->data() === ['cancel_at_period_end' => false]);
    Http::assertSentCount(1);

    expect($subscription->fresh()->onGracePeriod())->toBeFalse();
});

it('opens the Polar customer portal', function (): void {
    polarSubscription($this->organization, $this->plan);
    Http::fake([POLAR_API.'/customer-sessions/' => Http::response([
        'token' => 'polar_cst_1',
        'customer_portal_url' => 'https://sandbox.polar.sh/acme/portal?customer_session_token=polar_cst_1',
    ], 201)]);

    $this->actingAs($this->admin, 'sanctum')
        ->withHeader('X-Organization-Id', $this->organization->id)
        ->postJson('/api/v1/subscriptions/portal')
        ->assertOk()
        ->assertJsonFragment(['url' => 'https://sandbox.polar.sh/acme/portal?customer_session_token=polar_cst_1']);

    Http::assertSent(fn (Request $request) => $request['external_customer_id'] === $this->organization->id
        && $request['return_url'] === 'https://app.example.test/settings/billing');
});

it('fails cleanly when Polar returns no portal URL', function (): void {
    polarSubscription($this->organization, $this->plan);
    Http::fake([POLAR_API.'/customer-sessions/' => Http::response(['token' => 'polar_cst_1'], 201)]);

    $this->actingAs($this->admin, 'sanctum')
        ->withHeader('X-Organization-Id', $this->organization->id)
        ->postJson('/api/v1/subscriptions/portal')
        ->assertStatus(502);
});

it('has no portal before the organization pays', function (): void {
    $this->actingAs($this->admin, 'sanctum')
        ->withHeader('X-Organization-Id', $this->organization->id)
        ->postJson('/api/v1/subscriptions/portal')
        ->assertNotFound();
});

it('reports paid orders', function (): void {
    Event::fake([PaymentSucceeded::class]);

    polarWebhook('order.paid', [
        'id' => 'ord_1',
        'status' => 'paid',
        'total_amount' => 2900,
        'currency' => 'usd',
        'billing_reason' => 'subscription_cycle',
        'subscription_id' => 'sub_1',
        'metadata' => [],
        'customer' => ['id' => 'cus_1', 'external_id' => $this->organization->id],
    ])->assertAccepted();

    Event::assertDispatched(PaymentSucceeded::class, fn (PaymentSucceeded $event) => $event->organization->is($this->organization)
        && $event->paymentData['amount_paid'] === 2900
        && $event->paymentData['currency'] === 'usd');
});

it('finds the organization of an order from the checkout metadata', function (): void {
    Event::fake([PaymentSucceeded::class]);

    polarWebhook('order.paid', [
        'id' => 'ord_1',
        'total_amount' => 2900,
        'currency' => 'USD',
        'metadata' => ['organization_id' => $this->organization->id],
        'customer' => ['id' => 'cus_1', 'external_id' => null],
    ])->assertAccepted();

    Event::assertDispatched(PaymentSucceeded::class, fn (PaymentSucceeded $event) => $event->organization->is($this->organization)
        && $event->paymentData['currency'] === 'usd');
});

it('sends no payment e-mail for the $0 order that starts a trial', function (): void {
    Notification::fake();

    polarWebhook('order.paid', [
        'id' => 'ord_1',
        'total_amount' => 0,
        'currency' => 'usd',
        'customer' => ['id' => 'cus_1', 'external_id' => $this->organization->id],
    ])->assertAccepted();

    Notification::assertNotSentTo($this->organization->owner, PaymentSucceededNotification::class);
});

it('reports a failed renewal payment once', function (): void {
    Event::fake([PaymentFailed::class]);
    polarSubscription($this->organization, $this->plan);
    $pastDueAt = now()->subHour()->startOfSecond();

    polarWebhook('subscription.updated', polarSubscriptionData([
        'status' => 'past_due',
        'past_due_at' => $pastDueAt->toIso8601String(),
    ]))->assertAccepted();
    polarWebhook('subscription.past_due', polarSubscriptionData([
        'status' => 'past_due',
        'past_due_at' => $pastDueAt->toIso8601String(),
    ]))->assertAccepted();

    $subscription = PolarSubscription::query()->first();

    expect($subscription->past_due_at->equalTo($pastDueAt))->toBeTrue()
        ->and($subscription->onPaymentGracePeriod())->toBeTrue()
        ->and($subscription->subscriptionStatus()->value)->toBe('past_due');

    Event::assertDispatchedTimes(PaymentFailed::class, 1);
    Event::assertDispatched(PaymentFailed::class, fn (PaymentFailed $event) => $event->paymentData['amount_due'] === 2900);
});

it('refuses unsigned webhooks in production', function (): void {
    app()->detectEnvironment(fn () => 'production');

    polarWebhook('subscription.created', polarSubscriptionData())->assertStatus(503);

    expect(PolarSubscription::query()->count())->toBe(0);
});

describe('signed webhooks', function (): void {
    beforeEach(function (): void {
        $this->secret = 'whsec_'.base64_encode('polar-test-signing-key');
        config(['services.polar.webhook_secret' => $this->secret]);
    });

    function signedPolarWebhook(string $secret, array $data, ?int $timestamp = null, string $id = 'msg_1')
    {
        $body = json_encode(['type' => 'subscription.created', 'timestamp' => now()->toIso8601String(), 'data' => $data]);
        $timestamp ??= now()->getTimestamp();

        return test()->call('POST', '/polar/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_WEBHOOK_ID' => $id,
            'HTTP_WEBHOOK_TIMESTAMP' => (string) $timestamp,
            'HTTP_WEBHOOK_SIGNATURE' => PolarWebhookSignature::sign($id, $timestamp, $body, $secret),
        ], $body);
    }

    it('accepts a webhook signed with the secret', function (): void {
        signedPolarWebhook($this->secret, polarSubscriptionData())->assertAccepted();

        expect(PolarSubscription::query()->count())->toBe(1);
    });

    it('accepts the signature of an older Polar secret', function (): void {
        // Secrets generated before 8 September 2026 sign with the bytes of the whole string.
        $secret = 'whsec_legacy-secret-not-base64!';
        config(['services.polar.webhook_secret' => $secret]);

        $body = json_encode(['type' => 'subscription.created', 'data' => polarSubscriptionData()]);
        $timestamp = now()->getTimestamp();
        $signature = 'v1,'.base64_encode(hash_hmac('sha256', "msg_1.$timestamp.$body", $secret, true));

        $this->call('POST', '/polar/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_WEBHOOK_ID' => 'msg_1',
            'HTTP_WEBHOOK_TIMESTAMP' => (string) $timestamp,
            'HTTP_WEBHOOK_SIGNATURE' => "v1,bm90LXRoaXMtb25l {$signature}",
        ], $body)->assertAccepted();
    });

    it('refuses a webhook signed with another secret', function (): void {
        signedPolarWebhook('whsec_'.base64_encode('another-key'), polarSubscriptionData())->assertForbidden();

        expect(PolarSubscription::query()->count())->toBe(0);
    });

    it('refuses a webhook without a signature', function (): void {
        polarWebhook('subscription.created', polarSubscriptionData())->assertForbidden();
    });

    it('refuses a replayed webhook', function (): void {
        signedPolarWebhook($this->secret, polarSubscriptionData(), now()->subMinutes(10)->getTimestamp())->assertForbidden();
    });

    it('handles a redelivery once', function (): void {
        Event::fake([PaymentSucceeded::class]);
        $body = json_encode(['type' => 'order.paid', 'data' => [
            'id' => 'ord_1',
            'total_amount' => 2900,
            'currency' => 'usd',
            'customer' => ['id' => 'cus_1', 'external_id' => $this->organization->id],
        ]]);
        $timestamp = now()->getTimestamp();
        $server = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_WEBHOOK_ID' => 'msg_order',
            'HTTP_WEBHOOK_TIMESTAMP' => (string) $timestamp,
            'HTTP_WEBHOOK_SIGNATURE' => PolarWebhookSignature::sign('msg_order', $timestamp, $body, $this->secret),
        ];

        $this->call('POST', '/polar/webhook', [], [], [], $server, $body)->assertAccepted();
        $this->call('POST', '/polar/webhook', [], [], [], $server, $body)->assertAccepted();

        Event::assertDispatchedTimes(PaymentSucceeded::class, 1);
    });
});

it('cancels Polar subscriptions when the organization is deleted', function (): void {
    $subscription = polarSubscription($this->organization, $this->plan);
    Http::fake([POLAR_API.'/subscriptions/sub_1' => Http::response(polarSubscriptionData([
        'status' => 'canceled',
        'ended_at' => now()->toIso8601String(),
    ]))]);

    app(CancelSubscriptions::class)($this->organization);

    expect($subscription->fresh()->status)->toBe('canceled')
        ->and($subscription->fresh()->valid())->toBeFalse();
    Http::assertSent(fn (Request $request) => $request->method() === 'DELETE');
});

it('marks the subscription canceled when Polar cannot be told', function (): void {
    $subscription = polarSubscription($this->organization, $this->plan);
    Http::fake([POLAR_API.'/subscriptions/sub_1' => Http::response(['error' => 'AlreadyCanceledSubscription'], 403)]);

    app(CancelSubscriptions::class)($this->organization);

    expect($subscription->fresh()->status)->toBe('canceled')
        ->and($subscription->fresh()->ends_at)->not->toBeNull();
});

it('finds the active Polar subscription of the organization', function (): void {
    // Past due beyond the payment grace period.
    $this->travel(-(config('billing.past_due_grace_days') + 1))->days();
    polarSubscription($this->organization, $this->plan, ['status' => 'past_due']);
    $this->travelBack();

    expect($this->organization->activeSubscription())->toBeNull();

    polarSubscription($this->organization, $this->plan, ['polar_id' => 'sub_2', 'status' => 'active']);

    expect($this->organization->activeSubscription()->providerId())->toBe('sub_2')
        ->and($this->organization->subscribedToPlan($this->plan->id))->toBeTrue();
});

it('does not count a subscription that already ended', function (): void {
    polarSubscription($this->organization, $this->plan, ['ends_at' => now()->subMinute()]);

    expect($this->organization->activeSubscription())->toBeNull();
});

it('does not put an organization paying with Polar on the free plan', function (): void {
    polarSubscription($this->organization, $this->plan);

    $this->artisan('subscriptions:assign-free-plan')->assertSuccessful();

    expect($this->organization->subscriptions()->local()->exists())->toBeFalse();
});

it('reminds the organization that its Polar trial ends', function (): void {
    $subscription = polarSubscription($this->organization, $this->plan, [
        'status' => 'trialing',
        'trial_ends_at' => now()->addDays(3),
        'had_trial' => true,
    ]);

    $ending = app(BillingGateway::class)->trialsEndingBetween(now()->addDays(2), now()->addDays(4));

    expect($ending->pluck('id')->all())->toBe([$subscription->id])
        ->and($ending->first()->isTrialing())->toBeTrue()
        ->and($ending->first()->subscriptionStatus()->value)->toBe('trialing')
        ->and(app(BillingGateway::class)->hasUsedTrial($this->organization))->toBeTrue();
});

it('opens premium routes and plan limits to a Polar subscription', function (): void {
    Route::middleware(['api', 'auth:sanctum', 'tenant.context', 'subscription.required'])
        ->get('/api/_test/polar-premium', fn () => response()->json(['ok' => true]));
    $this->plan->update(['limits' => ['users' => 50]]);
    polarSubscription($this->organization, $this->plan, ['seats' => 5]);

    $this->actingAs($this->admin, 'sanctum')
        ->withHeader('X-Organization-Id', $this->organization->id)
        ->getJson('/api/_test/polar-premium')
        ->assertOk();

    expect((new ResourceLimits($this->organization))->getLimit('users'))->toBe(50)
        ->and($this->organization->activeSubscription()->quantity)->toBe(5);
});

it('lists the Polar subscriptions in the data export', function (): void {
    polarSubscription($this->organization, $this->plan);

    expect(app(BillingGateway::class)->subscriptions($this->organization)->first()->starts_at)->toBeInstanceOf(Carbon::class)
        ->and(app(BillingGateway::class)->subscriptions($this->organization)->first()->providerStatus())->toBe('active');
});
