<?php

use App\Models\Organization;
use Features\Subscription\Infrastructure\Eloquent\Models\Plan;
use Features\Subscription\Infrastructure\Eloquent\Models\Subscription;
use Features\Subscription\Shared\Events\SubscriptionUpgraded;
use Features\Subscription\Shared\Notifications\SubscriptionCanceledNotification;
use Features\Subscription\Subscribe\Application\Subscribe;
use Features\Subscription\Subscribe\Application\SubscribeHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Stripe\Exception\CardException;
use Tests\Support\FakeStripeClient;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['app.frontend_url' => 'https://app.example.test']);
    $this->stripe = FakeStripeClient::install();
    [$this->admin, $this->organization] = userInOrganization('admin');
    $this->plan = Plan::factory()->create(['price' => 2900, 'trial_days' => 14, 'stripe_price_id' => 'price_pro']);
});

function paidSubscription(Organization $organization, Plan $plan): Subscription
{
    $subscription = Subscription::factory()->create([
        'organization_id' => $organization->id,
        'stripe_id' => 'sub_paid',
        'stripe_price' => $plan->stripe_price_id,
        'plan_id' => $plan->id,
        'quantity' => 1,
    ]);
    $subscription->items()->create([
        'stripe_id' => 'si_paid',
        'stripe_product' => 'prod_pro',
        'stripe_price' => $plan->stripe_price_id,
        'quantity' => 1,
    ]);

    return $subscription;
}

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

it('grants the plan trial when the request does not mention it', function (): void {
    subscribeTo($this->plan)->assertCreated();

    expect($this->stripe->lastCall('checkout.sessions.create')['subscription_data'])->toHaveKey('trial_end');
});

it('skips the trial when the request opts out', function (): void {
    subscribeTo($this->plan, ['use_trial' => false])->assertCreated();

    expect($this->stripe->lastCall('checkout.sessions.create')['subscription_data'])->not->toHaveKey('trial_end');
});

it('passes the current free subscription so it can be replaced after checkout', function (): void {
    $current = Subscription::factory()->create([
        'organization_id' => $this->organization->id,
        'stripe_id' => Subscription::makeLocalStripeId(),
    ]);

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

it('switches paid plans in place, so Stripe prorates the change', function (): void {
    Event::fake([SubscriptionUpgraded::class]);
    $yearly = Plan::factory()->create(['price' => 29000, 'stripe_price_id' => 'price_pro_yearly']);
    $this->organization->forceFill(['stripe_id' => 'cus_1'])->save();
    $current = paidSubscription($this->organization, $this->plan);

    subscribeTo($yearly)
        ->assertCreated()
        ->assertJsonPath('data.action', 'activated')
        ->assertJsonPath('data.checkout_url', 'https://app.example.test/subscriptions/success?session_id=sub_paid');

    $update = $this->stripe->lastCall('subscriptions.update');

    expect($this->stripe->lastCall('checkout.sessions.create'))->toBeNull()
        ->and($this->stripe->lastCall('subscriptions.cancel'))->toBeNull()
        ->and($update['id'])->toBe('sub_paid')
        ->and($update['payment_behavior'])->toBe('error_if_incomplete')
        ->and($update['cancel_at_period_end'])->toBeFalse()
        ->and(collect($update['items'])->pluck('price')->filter()->values()->all())->toBe(['price_pro_yearly'])
        ->and($current->fresh()->plan_id)->toBe($yearly->id)
        ->and($current->fresh()->stripe_price)->toBe('price_pro_yearly');

    Event::assertDispatched(SubscriptionUpgraded::class, fn (SubscriptionUpgraded $event) => $event->oldPlan->is($this->plan) && $event->newPlan->is($yearly));
});

it('keeps the plan when the card is declined for the change', function (): void {
    $yearly = Plan::factory()->create(['price' => 29000, 'stripe_price_id' => 'price_pro_yearly']);
    $this->organization->forceFill(['stripe_id' => 'cus_1'])->save();
    $current = paidSubscription($this->organization, $this->plan);
    $this->stripe->throw = CardException::factory('Your card was declined.', 402, stripeCode: 'card_declined');

    subscribeTo($yearly)
        ->assertUnprocessable()
        ->assertJsonPath('errors.plan_id.0', 'Your card was declined. Update your payment method and try again.');

    expect($current->fresh()->plan_id)->toBe($this->plan->id)
        ->and($current->fresh()->stripe_price)->toBe('price_pro');
});

it('resumes a paid plan scheduled to end when it is chosen again', function (): void {
    $this->organization->forceFill(['stripe_id' => 'cus_1'])->save();
    $paid = paidSubscription($this->organization, $this->plan);
    $paid->forceFill(['ends_at' => now()->addWeek()])->save();

    subscribeTo($this->plan)->assertCreated()->assertJsonPath('data.action', 'activated');

    expect($this->stripe->lastCall('subscriptions.update')['cancel_at_period_end'])->toBeFalse()
        ->and($this->stripe->lastCall('checkout.sessions.create'))->toBeNull()
        ->and($paid->fresh()->ends_at)->toBeNull();
});

it('moves to a free plan at the end of the paid period', function (): void {
    $free = Plan::factory()->create(['price' => 0, 'stripe_price_id' => null, 'trial_days' => 0]);
    $this->organization->forceFill(['stripe_id' => 'cus_1'])->save();
    $paid = paidSubscription($this->organization, $this->plan);

    subscribeTo($free)
        ->assertCreated()
        ->assertJsonPath('data.action', 'scheduled');

    expect($this->stripe->lastCall('subscriptions.update'))->toMatchArray(['id' => 'sub_paid', 'cancel_at_period_end' => true])
        ->and($this->stripe->lastCall('subscriptions.cancel'))->toBeNull()
        ->and($paid->fresh()->onGracePeriod())->toBeTrue()
        ->and($this->organization->activeSubscription()->plan_id)->toBe($this->plan->id);

    // Asking again changes nothing.
    $this->stripe->calls = [];
    subscribeTo($free)->assertCreated()->assertJsonPath('data.action', 'scheduled');
    expect($this->stripe->calls)->toBe([]);

    // When Stripe ends the paid subscription, the free plan takes over.
    $this->postJson('/stripe/webhook', [
        'type' => 'customer.subscription.deleted',
        'data' => ['object' => [
            'id' => 'sub_paid',
            'customer' => 'cus_1',
            'status' => 'canceled',
            'items' => ['data' => [['id' => 'si_paid', 'price' => ['id' => 'price_pro', 'product' => 'prod_pro']]]],
        ]],
    ])->assertOk();

    expect($paid->fresh()->stripe_status)->toBe('canceled')
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
