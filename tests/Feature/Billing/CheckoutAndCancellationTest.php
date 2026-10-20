<?php

use Features\Subscription\Infrastructure\Eloquent\Models\Plan;
use Features\Subscription\Infrastructure\Eloquent\Models\Subscription;
use Features\Subscription\Shared\Notifications\SubscriptionCanceledNotification;
use Features\Subscription\Subscribe\Application\Subscribe;
use Features\Subscription\Subscribe\Application\SubscribeHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Support\FakeStripeClient;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['app.frontend_url' => 'https://app.example.test']);
    $this->stripe = FakeStripeClient::install();
    [$this->admin, $this->organization] = userInOrganization('admin');
    $this->plan = Plan::factory()->create(['price' => 2900, 'trial_days' => 14, 'stripe_price_id' => 'price_pro']);
});

function subscribeTo(Plan $plan, array $extra = [])
{
    return test()->actingAs(test()->admin, 'sanctum')
        ->withHeader('X-Organization-Id', test()->organization->id)
        ->postJson('/api/v1/subscriptions', ['plan_id' => $plan->id, ...$extra]);
}

it('creates a Stripe checkout session for paid plans', function (): void {
    subscribeTo($this->plan, ['use_trial' => true, 'quantity' => 3])
        ->assertCreated()
        ->assertJsonPath('data.checkout_url', 'https://checkout.stripe.test/session');

    $customer = $this->stripe->lastCall('customers.create');
    $session = $this->stripe->lastCall('checkout.sessions.create');

    expect($customer['email'])->toBe($this->organization->owner->email)
        ->and($customer['name'])->toBe($this->organization->name)
        ->and($session['mode'])->toBe('subscription')
        ->and($session['line_items'][0])->toMatchArray(['price' => 'price_pro', 'quantity' => 3])
        ->and($session['subscription_data']['metadata'])->toMatchArray([
            'organization_id' => $this->organization->id,
            'plan_id' => $this->plan->id,
        ])
        ->and($session['subscription_data'])->toHaveKey('trial_end')
        ->and($session['success_url'])->toStartWith('https://app.example.test/subscriptions/success')
        ->and($session['cancel_url'])->toBe('https://app.example.test/subscriptions/cancel')
        ->and($this->organization->fresh()->stripe_id)->not->toBeNull();
});

it('only grants the trial once per organization', function (): void {
    Subscription::factory()->create([
        'organization_id' => $this->organization->id,
        'stripe_status' => 'canceled',
        'trial_ends_at' => now()->subMonth(),
        'ends_at' => now()->subWeek(),
    ]);

    subscribeTo($this->plan, ['use_trial' => true])->assertCreated();

    expect($this->stripe->lastCall('checkout.sessions.create')['subscription_data'])->not->toHaveKey('trial_end');
});

it('passes the current subscription so it can be replaced after checkout', function (): void {
    $current = Subscription::factory()->create(['organization_id' => $this->organization->id]);

    subscribeTo($this->plan)->assertCreated();

    expect($this->stripe->lastCall('checkout.sessions.create')['subscription_data']['metadata'])->toMatchArray([
        'previous_subscription_id' => $current->id,
        'previous_subscription_stripe_id' => $current->stripe_id,
    ]);
});

it('refuses to subscribe twice to the same plan', function (): void {
    Subscription::factory()->create(['organization_id' => $this->organization->id, 'plan_id' => $this->plan->id]);

    subscribeTo($this->plan)
        ->assertUnprocessable()
        ->assertJsonPath('errors.plan_id.0', 'The organization is already subscribed to this plan.');
});

it('refuses paid plans that are not wired to a Stripe price', function (): void {
    $plan = Plan::factory()->create(['price' => 900, 'stripe_price_id' => null]);

    subscribeTo($plan)->assertUnprocessable();
});

it('hides payment provider errors from the client', function (): void {
    $this->stripe->failWith = 'Invalid API Key provided: sk_live_****';

    $response = subscribeTo($this->plan)->assertStatus(502);

    expect($response->getContent())->not->toContain('sk_live');
});

it('switches to a free plan by cancelling the current subscription', function (): void {
    $free = Plan::factory()->create(['price' => 0, 'stripe_price_id' => null]);
    $paid = Subscription::factory()->create(['organization_id' => $this->organization->id, 'stripe_id' => 'sub_paid']);

    subscribeTo($free)->assertCreated();

    expect($this->stripe->lastCall('subscriptions.cancel')['id'])->toBe('sub_paid')
        ->and($paid->fresh()->stripe_status)->toBe('canceled')
        ->and($this->organization->activeSubscription()->plan_id)->toBe($free->id);
});

it('requires an organization when the handler is used directly', function (): void {
    app(SubscribeHandler::class)(new Subscribe(planId: $this->plan->id, useTrial: false, quantity: null));
})->throws(InvalidArgumentException::class, 'Organization context is required.');

it('cancels a local subscription and notifies the owner', function (): void {
    Notification::fake();

    $subscription = Subscription::factory()->create([
        'organization_id' => $this->organization->id,
        'stripe_id' => Subscription::makeLocalStripeId(),
        'plan_id' => $this->plan->id,
    ]);

    $this->actingAs($this->admin, 'sanctum')
        ->withHeader('X-Organization-Id', $this->organization->id)
        ->deleteJson('/api/v1/subscriptions')
        ->assertOk()
        ->assertJsonPath('data.status', 'canceled');

    expect($subscription->fresh()->canceled_at)->not->toBeNull();

    Notification::assertSentTo($this->organization->owner, SubscriptionCanceledNotification::class);
});

it('cancels Stripe subscriptions at period end or immediately', function (): void {
    $subscription = Subscription::factory()->create([
        'organization_id' => $this->organization->id,
        'stripe_id' => 'sub_stripe',
        'plan_id' => $this->plan->id,
    ]);
    $this->organization->forceFill(['stripe_id' => 'cus_1'])->save();

    $this->actingAs($this->admin, 'sanctum')
        ->withHeader('X-Organization-Id', $this->organization->id)
        ->deleteJson('/api/v1/subscriptions')
        ->assertOk();

    expect($this->stripe->lastCall('subscriptions.update'))->toMatchArray(['id' => 'sub_stripe', 'cancel_at_period_end' => true]);

    // Already scheduled: a second (non immediate) cancel is a no-op.
    $subscription->forceFill(['ends_at' => now()->addWeek()])->save();
    $this->stripe->calls = [];

    $this->actingAs($this->admin, 'sanctum')
        ->withHeader('X-Organization-Id', $this->organization->id)
        ->deleteJson('/api/v1/subscriptions')
        ->assertOk();

    expect($this->stripe->calls)->toBe([]);

    $this->actingAs($this->admin, 'sanctum')
        ->withHeader('X-Organization-Id', $this->organization->id)
        ->deleteJson('/api/v1/subscriptions', ['immediately' => true])
        ->assertOk();

    expect($this->stripe->lastCall('subscriptions.cancel')['id'])->toBe('sub_stripe')
        ->and($subscription->fresh()->stripe_status)->toBe('canceled');
});

it('returns 404 when there is nothing to cancel', function (): void {
    $this->actingAs($this->admin, 'sanctum')
        ->withHeader('X-Organization-Id', $this->organization->id)
        ->deleteJson('/api/v1/subscriptions')
        ->assertNotFound();
});

it('forbids members from changing the subscription', function (): void {
    [$member] = userInOrganization('member', $this->organization);

    $this->actingAs($member, 'sanctum')
        ->withHeader('X-Organization-Id', $this->organization->id)
        ->postJson('/api/v1/subscriptions', ['plan_id' => $this->plan->id])
        ->assertForbidden();

    $this->actingAs($member, 'sanctum')
        ->withHeader('X-Organization-Id', $this->organization->id)
        ->deleteJson('/api/v1/subscriptions')
        ->assertForbidden();
});
