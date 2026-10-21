<?php

use App\Models\Organization;
use Features\Subscription\Infrastructure\Eloquent\Models\Plan;
use Features\Subscription\Infrastructure\Eloquent\Models\Subscription;
use Features\Subscription\Shared\Events\OrganizationSubscriptionChanged;
use Features\Subscription\Shared\Events\SubscriptionCanceled;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

function subscriptionChangedPayload(string $type, Organization $organization, string $stripeId, Plan $plan, string $status = 'active'): array
{
    return [
        'id' => 'evt_'.uniqid(),
        'type' => $type,
        'data' => ['object' => [
            'id' => $stripeId,
            'object' => 'subscription',
            'customer' => $organization->stripe_id,
            'status' => $status,
            'current_period_start' => now()->getTimestamp(),
            'items' => ['data' => [[
                'id' => 'si_'.$stripeId,
                'quantity' => 1,
                'price' => ['id' => $plan->stripe_price_id, 'product' => 'prod_test'],
            ]]],
            'metadata' => [],
        ]],
    ];
}

beforeEach(function (): void {
    $this->organization = Organization::factory()->create(['stripe_id' => 'cus_changed_test']);
    $this->basic = Plan::factory()->create(['price' => 1000, 'stripe_price_id' => 'price_basic_changed']);
    $this->pro = Plan::factory()->create(['price' => 5000, 'stripe_price_id' => 'price_pro_changed']);
});

it('reports the plan in effect after a plan switch', function (): void {
    Event::fake([OrganizationSubscriptionChanged::class]);
    Subscription::factory()->create([
        'organization_id' => $this->organization->id,
        'stripe_id' => 'sub_switch',
        'plan_id' => $this->pro->id,
    ]);

    $this->postJson('/stripe/webhook', subscriptionChangedPayload('customer.subscription.updated', $this->organization, 'sub_switch', $this->basic))
        ->assertOk();

    Event::assertDispatched(OrganizationSubscriptionChanged::class, fn (OrganizationSubscriptionChanged $event) => $event->organization->is($this->organization)
        && $event->plan?->is($this->basic));
});

it('reports no plan once the subscription has ended', function (): void {
    Event::fake([OrganizationSubscriptionChanged::class]);
    Subscription::factory()->create([
        'organization_id' => $this->organization->id,
        'stripe_id' => 'sub_ended',
        'plan_id' => $this->pro->id,
    ]);

    $this->postJson('/stripe/webhook', subscriptionChangedPayload('customer.subscription.deleted', $this->organization, 'sub_ended', $this->pro, 'canceled'))
        ->assertOk();

    Event::assertDispatched(OrganizationSubscriptionChanged::class, fn (OrganizationSubscriptionChanged $event) => $event->plan === null);
});

it('reports the plan of a new subscription', function (): void {
    Event::fake([OrganizationSubscriptionChanged::class]);
    [$owner, $organization] = userInOrganization('owner');
    $free = Plan::factory()->create(['price' => 0, 'stripe_price_id' => null, 'trial_days' => 0]);

    $this->actingAs($owner, 'sanctum')->withHeader('X-Organization-Id', $organization->id)
        ->postJson('/api/v1/subscriptions', ['plan_id' => $free->id])
        ->assertSuccessful();

    Event::assertDispatched(OrganizationSubscriptionChanged::class, fn (OrganizationSubscriptionChanged $event) => $event->organization->is($organization)
        && $event->plan?->is($free));
});

it('ignores subscriptions without an organization', function (): void {
    Event::fake([OrganizationSubscriptionChanged::class]);
    $subscription = Subscription::factory()->make(['organization_id' => '99999999-9999-4999-9999-999999999999']);

    event(new SubscriptionCanceled($subscription));

    Event::assertNotDispatched(OrganizationSubscriptionChanged::class);
});
