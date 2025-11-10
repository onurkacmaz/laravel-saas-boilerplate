<?php

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Features\Subscription\Infrastructure\Eloquent\Models\Plan;
use Features\Subscription\Subscribe\Application\CheckoutSession;
use Features\Subscription\Subscribe\Application\Subscribe;
use Features\Subscription\Subscribe\Application\SubscribeHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->organization = Organization::factory()->create();
    $this->adminRole = Role::factory()->create([
        'organization_id' => $this->organization->id,
        'slug' => 'admin',
    ]);

    $this->user->organizations()->attach($this->organization->id, [
        'role_id' => $this->adminRole->id,
    ]);
});

it('subscribes the organization to a plan', function (): void {
    $plan = Plan::factory()->create();

    $session = new CheckoutSession(
        id: 'cs_test_123',
        url: 'https://stripe.test/checkout/cs_test_123',
        expiresAt: now()->addMinutes(30)->getTimestamp(),
        customerId: 'cus_test_123'
    );

    $this->mock(SubscribeHandler::class)
        ->shouldReceive('__invoke')
        ->once()
        ->withArgs(function (Subscribe $command) use ($plan): bool {
            return $command->planId === $plan->id
                && $command->useTrial === true
                && $command->quantity === 5;
        })
        ->andReturn($session);

    $response = $this->actingAs($this->user, 'sanctum')
        ->withHeader('X-Organization-Id', $this->organization->id)
        ->postJson('/api/v1/subscriptions', [
            'plan_id' => $plan->id,
            'use_trial' => true,
            'quantity' => 5,
        ]);

    $response->assertCreated()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('message', 'Checkout session created')
        ->assertJsonPath('data.session_id', $session->id)
        ->assertJsonPath('data.checkout_url', $session->url)
        ->assertJsonPath('data.expires_at', $session->expiresAt)
        ->assertJsonPath('data.customer_id', $session->customerId);
});

it('activates free plans without creating a Stripe checkout', function (): void {
    config(['app.frontend_url' => 'https://frontend.test']);

    $plan = Plan::factory()->create([
        'price' => 0,
        'stripe_price_id' => null,
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->withHeader('X-Organization-Id', $this->organization->id)
        ->postJson('/api/v1/subscriptions', [
            'plan_id' => $plan->id,
        ]);

    $response->assertCreated()
        ->assertJsonPath('status', 'success');

    $sessionId = $response->json('data.session_id');

    expect($sessionId)->toStartWith('local_sub_');

    $response->assertJsonPath('data.checkout_url', "https://frontend.test/subscriptions/success?session_id={$sessionId}")
        ->assertJsonPath('data.customer_id', null);

    $this->assertDatabaseHas('subscriptions', [
        'organization_id' => $this->organization->id,
        'plan_id' => $plan->id,
        'stripe_id' => $sessionId,
        'stripe_status' => 'active',
    ]);
});

it('requires tenant context when subscribing', function (): void {
    $plan = Plan::factory()->create();

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/subscriptions', [
            'plan_id' => $plan->id,
        ]);

    $response->assertForbidden()
        ->assertJsonPath('message', 'Organization context is required. Please provide X-Organization-Id header.');
});

it('rejects subscribing to inactive plans', function (): void {
    $plan = Plan::factory()->create(['is_active' => false]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->withHeader('X-Organization-Id', $this->organization->id)
        ->postJson('/api/v1/subscriptions', [
            'plan_id' => $plan->id,
        ]);

    $response->assertUnprocessable()
        ->assertJsonPath('status', 'error')
        ->assertJsonPath('errors.plan_id.0', 'This plan is not currently available.');
});

it('validates optional fields', function (): void {
    $plan = Plan::factory()->create();

    $response = $this->actingAs($this->user, 'sanctum')
        ->withHeader('X-Organization-Id', $this->organization->id)
        ->postJson('/api/v1/subscriptions', [
            'plan_id' => $plan->id,
            'use_trial' => 'yes',
            'quantity' => 0,
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['use_trial', 'quantity']);
});
