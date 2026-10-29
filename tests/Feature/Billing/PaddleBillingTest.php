<?php

use App\Models\Organization;
use Features\Subscription\Infrastructure\Eloquent\Models\Plan;
use Features\Subscription\Infrastructure\Eloquent\Models\Subscription;
use Features\Subscription\Infrastructure\Paddle\PaddleBillable;
use Features\Subscription\Infrastructure\Paddle\PaddleCustomer;
use Features\Subscription\Infrastructure\Paddle\PaddleSubscription;
use Features\Subscription\Shared\Contracts\BillingGateway;
use Features\Subscription\Shared\Events\PaymentSucceeded;
use Features\Subscription\Shared\Events\SubscriptionUpgraded;
use Features\Subscription\Shared\Notifications\PaymentSucceededNotification;
use Features\Subscription\Shared\Support\CancelSubscriptions;
use Features\Subscription\Shared\Support\ResourceLimits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'billing.provider' => 'paddle',
        'cashier.api_key' => 'pdl_test_key',
        'cashier.client_side_token' => 'test_client_token',
        'cashier.sandbox' => true,
        'cashier.webhook_secret' => null,
        'app.frontend_url' => 'https://app.example.test',
    ]);

    [$this->admin, $this->organization] = userInOrganization('owner');
    $this->free = Plan::factory()->create(['price' => 0, 'trial_days' => 0, 'stripe_price_id' => null]);
    $this->plan = Plan::factory()->create([
        'price' => 2900,
        'trial_days' => 14,
        'paddle_price_id' => 'pri_pro',
        'paddle_trial_price_id' => 'pri_pro_trial',
    ]);
});

function paddleApi(array $responses = []): void
{
    Http::fake([
        'sandbox-api.paddle.com/customers?*' => Http::response(['data' => []]),
        'sandbox-api.paddle.com/customers' => Http::response(['data' => ['id' => 'ctm_1', 'name' => 'Acme', 'email' => 'owner@example.test']]),
        'sandbox-api.paddle.com/transactions' => Http::response(['data' => [
            'id' => 'txn_1',
            'checkout' => ['url' => 'https://api.example.test/paddle/checkout?_ptxn=txn_1'],
        ]]),
        ...$responses,
    ]);
}

function paddleCustomer(Organization $organization): PaddleCustomer
{
    return PaddleBillable::of($organization)->customer()->create([
        'paddle_id' => 'ctm_1',
        'name' => $organization->name,
        'email' => 'owner@example.test',
    ]);
}

function paddleSubscription(Organization $organization, Plan $plan, array $attributes = []): PaddleSubscription
{
    /** @var PaddleSubscription $subscription */
    $subscription = PaddleBillable::of($organization)->subscriptions()->create([
        'type' => 'default',
        'paddle_id' => 'sub_1',
        'status' => 'active',
        'plan_id' => $plan->id,
        ...$attributes,
    ]);
    $subscription->items()->create(['product_id' => 'pro_1', 'price_id' => $plan->paddle_price_id, 'status' => 'active', 'quantity' => 1]);

    return $subscription;
}

function paddleSubscriptionPayload(string $event, array $data): array
{
    return [
        'event_type' => $event,
        'data' => [
            'id' => 'sub_1',
            'customer_id' => 'ctm_1',
            'status' => 'active',
            'items' => [['price' => ['id' => 'pri_pro', 'product_id' => 'pro_1'], 'status' => 'active', 'quantity' => 1]],
            'custom_data' => null,
            ...$data,
        ],
    ];
}

function paddleTransactionPayload(array $totals): array
{
    return [
        'event_type' => 'transaction.completed',
        'data' => [
            'id' => 'txn_'.uniqid(),
            'customer_id' => 'ctm_1',
            'subscription_id' => 'sub_1',
            'invoice_number' => null,
            'status' => 'completed',
            'currency_code' => 'USD',
            'billed_at' => now()->toIso8601String(),
            'details' => ['totals' => ['tax' => '0', ...$totals]],
            'custom_data' => ['organization_id' => test()->organization->id],
        ],
    ];
}

function subscribeWithPaddle(Plan $plan, array $extra = [])
{
    return test()->actingAs(test()->admin, 'sanctum')
        ->withHeader('X-Organization-Id', test()->organization->id)
        ->postJson('/api/v1/subscriptions', ['plan_id' => $plan->id, ...$extra]);
}

it('resolves the Paddle gateway from the configuration', function (): void {
    expect(app(BillingGateway::class)->name())->toBe('paddle');
});

it('opens a Paddle checkout with the trial price', function (): void {
    paddleApi();

    subscribeWithPaddle($this->plan)
        ->assertCreated()
        ->assertJsonPath('data.action', 'checkout')
        ->assertJsonPath('data.session_id', 'txn_1')
        ->assertJsonPath('data.checkout_url', 'https://api.example.test/paddle/checkout?_ptxn=txn_1');

    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'https://sandbox-api.paddle.com/transactions'
            && $request['items'] === [['price_id' => 'pri_pro_trial', 'quantity' => 1]]
            && $request['customer_id'] === 'ctm_1'
            && $request['custom_data']['organization_id'] === test()->organization->id
            && $request['custom_data']['plan_id'] === test()->plan->id
            && $request['custom_data']['subscription_type'] === 'default';
    });

    expect(PaddleBillable::of($this->organization)->customer->paddle_id)->toBe('ctm_1');
});

it('charges the price without a trial once the organization had one', function (): void {
    paddleApi();
    paddleCustomer($this->organization);
    paddleSubscription($this->organization, $this->plan, ['status' => 'canceled', 'had_trial' => true, 'ends_at' => now()->subDay()]);

    subscribeWithPaddle($this->plan)->assertCreated();

    Http::assertSent(fn (Request $request) => $request->url() === 'https://sandbox-api.paddle.com/transactions'
        && $request['items'][0]['price_id'] === 'pri_pro');
});

it('skips the trial when the request opts out', function (): void {
    paddleApi();

    subscribeWithPaddle($this->plan, ['use_trial' => false])->assertCreated();

    Http::assertSent(fn (Request $request) => $request->url() === 'https://sandbox-api.paddle.com/transactions'
        && $request['items'][0]['price_id'] === 'pri_pro');
});

it('refuses paid plans that are not wired to a Paddle price', function (): void {
    $plan = Plan::factory()->create(['price' => 900, 'paddle_price_id' => null]);

    subscribeWithPaddle($plan)->assertUnprocessable();
});

it('fails cleanly without a default payment link', function (): void {
    paddleApi(['sandbox-api.paddle.com/transactions' => Http::response(['data' => ['id' => 'txn_1', 'checkout' => ['url' => null]]])]);

    subscribeWithPaddle($this->plan)->assertStatus(502);
});

it('creates the subscription from the webhook and ends the free plan', function (): void {
    paddleCustomer($this->organization);
    $free = Subscription::factory()->create([
        'organization_id' => $this->organization->id,
        'plan_id' => $this->free->id,
        'stripe_id' => Subscription::makeLocalStripeId(),
        'stripe_status' => 'active',
    ]);

    $this->postJson('/paddle/webhook', paddleSubscriptionPayload('subscription.created', [
        'status' => 'trialing',
        'next_billed_at' => now()->addDays(14)->toIso8601String(),
        'items' => [['price' => ['id' => 'pri_pro_trial', 'product_id' => 'pro_1'], 'status' => 'trialing', 'quantity' => 1]],
        'custom_data' => [
            'organization_id' => $this->organization->id,
            'plan_id' => $this->plan->id,
            'previous_subscription_id' => $free->id,
            'subscription_type' => 'default',
        ],
    ]))->assertOk();

    $subscription = PaddleSubscription::query()->firstWhere('paddle_id', 'sub_1');

    expect($subscription->plan_id)->toBe($this->plan->id)
        ->and($subscription->had_trial)->toBeTrue()
        ->and($free->fresh()->canceled())->toBeTrue()
        ->and($this->organization->activeSubscription()->is($subscription))->toBeTrue();

    $this->actingAs($this->admin, 'sanctum')
        ->withHeader('X-Organization-Id', $this->organization->id)
        ->getJson('/api/v1/subscriptions')
        ->assertOk()
        ->assertJsonPath('data.provider', 'paddle')
        ->assertJsonPath('data.status', 'trialing')
        ->assertJsonPath('data.plan.id', $this->plan->id);
});

it('ignores a repeated subscription.created delivery', function (): void {
    paddleCustomer($this->organization);
    $payload = paddleSubscriptionPayload('subscription.created', ['custom_data' => ['plan_id' => $this->plan->id]]);

    $this->postJson('/paddle/webhook', $payload)->assertOk();
    $this->postJson('/paddle/webhook', $payload)->assertOk();

    expect(PaddleSubscription::query()->count())->toBe(1);
});

it('follows a plan change made in the customer portal', function (): void {
    Event::fake([SubscriptionUpgraded::class]);
    paddleCustomer($this->organization);
    $subscription = paddleSubscription($this->organization, $this->plan);
    $business = Plan::factory()->create(['price' => 9900, 'paddle_price_id' => 'pri_business']);

    $this->postJson('/paddle/webhook', paddleSubscriptionPayload('subscription.updated', [
        'items' => [['price' => ['id' => 'pri_business', 'product_id' => 'pro_2'], 'status' => 'active', 'quantity' => 1]],
    ]))->assertOk();

    expect($subscription->fresh()->plan_id)->toBe($business->id);
    Event::assertDispatched(SubscriptionUpgraded::class);
});

it('puts the organization back on the free plan when the subscription ends', function (): void {
    paddleCustomer($this->organization);
    paddleSubscription($this->organization, $this->plan);

    $this->postJson('/paddle/webhook', paddleSubscriptionPayload('subscription.canceled', [
        'status' => 'canceled',
        'canceled_at' => now()->toIso8601String(),
    ]))->assertOk();

    $active = $this->organization->activeSubscription();

    expect($active->provider())->toBe('local')
        ->and($active->plan_id)->toBe($this->free->id);
});

it('switches paid plans in place, so Paddle prorates the change', function (): void {
    paddleCustomer($this->organization);
    $subscription = paddleSubscription($this->organization, $this->plan);
    $yearly = Plan::factory()->create(['price' => 29000, 'paddle_price_id' => 'pri_pro_yearly']);
    Http::fake(['sandbox-api.paddle.com/subscriptions/sub_1' => Http::response(['data' => [
        'status' => 'active',
        'items' => [['price' => ['id' => 'pri_pro_yearly', 'product_id' => 'pro_1'], 'status' => 'active', 'quantity' => 1]],
    ]])]);

    subscribeWithPaddle($yearly)
        ->assertCreated()
        ->assertJsonPath('data.action', 'activated');

    Http::assertSent(fn (Request $request) => $request->method() === 'PATCH'
        && $request['items'] === [['price_id' => 'pri_pro_yearly', 'quantity' => 1]]
        && $request['proration_billing_mode'] === 'prorated_immediately');

    expect($subscription->fresh()->plan_id)->toBe($yearly->id);
});

it('keeps the plan when Paddle refuses the change', function (): void {
    paddleCustomer($this->organization);
    $subscription = paddleSubscription($this->organization, $this->plan);
    $yearly = Plan::factory()->create(['price' => 29000, 'paddle_price_id' => 'pri_pro_yearly']);
    Http::fake(['sandbox-api.paddle.com/subscriptions/sub_1' => Http::response(['error' => ['type' => 'request_error', 'code' => 'subscription_payment_declined', 'detail' => 'Payment declined']], 400)]);

    subscribeWithPaddle($yearly)->assertUnprocessable();

    expect($subscription->fresh()->plan_id)->toBe($this->plan->id);
});

it('moves to the free plan at the end of the paid period', function (): void {
    paddleCustomer($this->organization);
    $subscription = paddleSubscription($this->organization, $this->plan);
    $endsAt = now()->addMonth()->startOfSecond();
    Http::fake(['sandbox-api.paddle.com/subscriptions/sub_1/cancel' => Http::response(['data' => [
        'status' => 'active',
        'scheduled_change' => ['action' => 'cancel', 'effective_at' => $endsAt->toIso8601String()],
    ]])]);

    subscribeWithPaddle($this->free)
        ->assertCreated()
        ->assertJsonPath('data.action', 'scheduled');

    $subscription->refresh();

    expect($subscription->onGracePeriod())->toBeTrue()
        ->and($subscription->ends_at->equalTo($endsAt))->toBeTrue()
        ->and($this->organization->activeSubscription()->is($subscription))->toBeTrue();
});

it('opens the Paddle customer portal', function (): void {
    paddleCustomer($this->organization);
    paddleSubscription($this->organization, $this->plan);
    Http::fake(['sandbox-api.paddle.com/customers/ctm_1/portal-sessions' => Http::response(['data' => [
        'urls' => ['general' => ['overview' => 'https://customer-portal.paddle.com/cpl_1']],
    ]])]);

    $this->actingAs($this->admin, 'sanctum')
        ->withHeader('X-Organization-Id', $this->organization->id)
        ->postJson('/api/v1/subscriptions/portal')
        ->assertOk()
        ->assertJsonFragment(['url' => 'https://customer-portal.paddle.com/cpl_1']);

    Http::assertSent(fn (Request $request) => $request['subscription_ids'] === ['sub_1']);
});

it('has no portal before the organization pays', function (): void {
    $this->actingAs($this->admin, 'sanctum')
        ->withHeader('X-Organization-Id', $this->organization->id)
        ->postJson('/api/v1/subscriptions/portal')
        ->assertNotFound();
});

it('reports completed payments', function (): void {
    Event::fake([PaymentSucceeded::class]);
    paddleCustomer($this->organization);

    $this->postJson('/paddle/webhook', [
        'event_type' => 'transaction.completed',
        'data' => [
            'id' => 'txn_1',
            'customer_id' => 'ctm_1',
            'subscription_id' => 'sub_1',
            'invoice_number' => '123-10001',
            'status' => 'completed',
            'currency_code' => 'USD',
            'billed_at' => now()->toIso8601String(),
            'details' => ['totals' => ['total' => '2900', 'tax' => '0']],
            'custom_data' => ['organization_id' => $this->organization->id],
        ],
    ])->assertOk();

    Event::assertDispatched(PaymentSucceeded::class, fn (PaymentSucceeded $event) => $event->organization->is($this->organization)
        && $event->paymentData['amount_paid'] === 2900
        && $event->paymentData['currency'] === 'usd');
});

it('reports the amount charged, after the credit of a plan change', function (): void {
    Event::fake([PaymentSucceeded::class]);
    paddleCustomer($this->organization);

    $this->postJson('/paddle/webhook', paddleTransactionPayload(['total' => '29000', 'grand_total' => '27100']))->assertOk();

    Event::assertDispatched(PaymentSucceeded::class, fn (PaymentSucceeded $event) => $event->paymentData['amount_paid'] === 27100);
});

it('sends no payment e-mail for the $0 transaction that starts a trial', function (): void {
    Notification::fake();
    paddleCustomer($this->organization);

    $this->postJson('/paddle/webhook', paddleTransactionPayload(['total' => '0', 'grand_total' => '0']))->assertOk();

    Notification::assertNotSentTo($this->organization->owner, PaymentSucceededNotification::class);
});

it('refuses unsigned webhooks in production', function (): void {
    app()->detectEnvironment(fn () => 'production');

    $this->postJson('/paddle/webhook', paddleSubscriptionPayload('subscription.created', []))
        ->assertStatus(503);

    expect(PaddleSubscription::query()->count())->toBe(0);
});

it('serves the checkout page Paddle opens the checkout on', function (): void {
    $this->get('/paddle/checkout?_ptxn=txn_1')
        ->assertOk()
        ->assertSee('cdn.paddle.com/paddle/v2/paddle.js', false)
        ->assertSee("Paddle.Environment.set('sandbox')", false)
        ->assertSee('test_client_token', false)
        ->assertSee('https:\/\/app.example.test\/subscriptions\/success?session_id=txn_1', false);
});

it('cancels Paddle subscriptions when the organization is deleted', function (): void {
    paddleCustomer($this->organization);
    $subscription = paddleSubscription($this->organization, $this->plan);
    Http::fake(['sandbox-api.paddle.com/subscriptions/sub_1/cancel' => Http::response(['data' => [
        'status' => 'canceled',
        'canceled_at' => now()->toIso8601String(),
    ]])]);

    app(CancelSubscriptions::class)($this->organization);

    expect($subscription->fresh()->canceled())->toBeTrue();
    Http::assertSent(fn (Request $request) => $request['effective_from'] === 'immediately');
});

it('resumes a subscription scheduled to end when its plan is chosen again', function (): void {
    paddleCustomer($this->organization);
    $subscription = paddleSubscription($this->organization, $this->plan, ['ends_at' => now()->addWeek()]);
    Http::fake(['sandbox-api.paddle.com/subscriptions/sub_1' => Http::response(['data' => ['status' => 'active']])]);

    subscribeWithPaddle($this->plan)
        ->assertCreated()
        ->assertJsonPath('data.action', 'activated');

    Http::assertSent(fn (Request $request) => $request->method() === 'PATCH'
        && array_key_exists('scheduled_change', $request->data())
        && $request['scheduled_change'] === null);
    Http::assertSentCount(1);

    expect($subscription->fresh()->onGracePeriod())->toBeFalse();
});

it('finds the active Paddle subscription of the organization', function (): void {
    paddleCustomer($this->organization);
    paddleSubscription($this->organization, $this->plan, ['status' => 'past_due']);

    expect($this->organization->activeSubscription())->toBeNull();

    paddleSubscription($this->organization, $this->plan, ['paddle_id' => 'sub_2', 'status' => 'active']);

    expect($this->organization->activeSubscription()->providerId())->toBe('sub_2')
        ->and($this->organization->subscribedToPlan($this->plan->id))->toBeTrue();
});

it('does not put an organization paying with Paddle on the free plan', function (): void {
    paddleCustomer($this->organization);
    paddleSubscription($this->organization, $this->plan);

    $this->artisan('subscriptions:assign-free-plan')->assertSuccessful();

    expect($this->organization->subscriptions()->local()->exists())->toBeFalse();
});

it('ignores a malformed transaction on the checkout page', function (): void {
    $this->get('/paddle/checkout?_ptxn=txn_1%26next=evil')
        ->assertOk()
        ->assertDontSee('evil');
});

it('opens premium routes and plan limits to a Paddle subscription', function (): void {
    Route::middleware(['api', 'auth:sanctum', 'tenant.context', 'subscription.required'])
        ->get('/api/_test/paddle-premium', fn () => response()->json(['ok' => true]));
    $this->plan->update(['limits' => ['users' => 50]]);
    paddleCustomer($this->organization);
    paddleSubscription($this->organization, $this->plan);

    $this->actingAs($this->admin, 'sanctum')
        ->withHeader('X-Organization-Id', $this->organization->id)
        ->getJson('/api/_test/paddle-premium')
        ->assertOk();

    expect((new ResourceLimits($this->organization))->getLimit('users'))->toBe(50);
});
