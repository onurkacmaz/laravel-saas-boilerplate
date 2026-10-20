<?php

use Features\Subscription\Infrastructure\Eloquent\Models\Plan;
use Features\Subscription\Infrastructure\Eloquent\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('does not throttle organizations on unlimited plans', function (): void {
    [$user, $organization] = userInOrganization('member');
    $plan = Plan::factory()->create(['limits' => ['requests_per_minute' => -1]]);
    Subscription::factory()->create(['organization_id' => $organization->id, 'plan_id' => $plan->id]);

    foreach (range(1, 3) as $attempt) {
        $this->actingAs($user, 'sanctum')
            ->withHeader('X-Organization-Id', $organization->id)
            ->getJson('/api/v1/user')
            ->assertOk();
    }
});

it('falls back to free tier limits when the subscription has no plan', function (): void {
    [$user, $organization] = userInOrganization('member');
    Subscription::factory()->create(['organization_id' => $organization->id, 'plan_id' => null]);

    $this->actingAs($user, 'sanctum')
        ->withHeader('X-Organization-Id', $organization->id)
        ->getJson('/api/v1/usage/limits')
        ->assertOk()
        ->assertHeader('X-RateLimit-Limit', '60');
});
