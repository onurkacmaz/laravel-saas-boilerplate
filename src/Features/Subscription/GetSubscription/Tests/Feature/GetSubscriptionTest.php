<?php

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Features\Subscription\Infrastructure\Eloquent\Models\Plan;
use Features\Subscription\Infrastructure\Eloquent\Models\Subscription;
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

it('returns the current active subscription', function (): void {
    $plan = Plan::factory()->create();
    $subscription = Subscription::factory()->create([
        'organization_id' => $this->organization->id,
        'plan_id' => $plan->id,
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->withHeader('X-Organization-Id', $this->organization->id)
        ->getJson('/api/v1/subscriptions');

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('message', 'Subscription retrieved')
        ->assertJsonPath('data.id', $subscription->id)
        ->assertJsonPath('data.plan.id', $plan->id)
        ->assertJsonPath('data.status', 'active');
});

it('returns not found when no active subscription exists', function (): void {
    $response = $this->actingAs($this->user, 'sanctum')
        ->withHeader('X-Organization-Id', $this->organization->id)
        ->getJson('/api/v1/subscriptions');

    $response->assertNotFound()
        ->assertJsonPath('message', 'No active subscription');
});
