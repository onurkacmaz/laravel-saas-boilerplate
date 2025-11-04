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

    $this->role = Role::factory()->create([
        'organization_id' => $this->organization->id,
        'slug' => 'admin',
    ]);

    $this->user->organizations()->attach($this->organization->id, [
        'role_id' => $this->role->id,
    ]);
});

it('returns current usage with plan limits', function (): void {
    $plan = Plan::factory()->create([
        'limits' => [
            'users' => 3,
            'projects' => 10,
        ],
    ]);

    Subscription::factory()->create([
        'organization_id' => $this->organization->id,
        'plan_id' => $plan->id,
    ]);

    $additionalUser = User::factory()->create();
    $this->organization->users()->attach($additionalUser->id, [
        'role_id' => $this->role->id,
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->withHeader('X-Organization-Id', $this->organization->id)
        ->getJson('/api/v1/usage');

    $response->assertOk()
        ->assertJsonPath('data.users.limit', 3)
        ->assertJsonPath('data.users.current', 2)
        ->assertJsonPath('data.users.remaining', 1)
        ->assertJsonPath('data.users.can_add', true);

    $summary = $response->json('data');

    expect($summary['users']['percentage'])
        ->toBeGreaterThan(60.0)
        ->toBeLessThan(70.0);
});

it('marks limits as reached when capacity is met', function (): void {
    $plan = Plan::factory()->create([
        'limits' => [
            'users' => 1,
        ],
    ]);

    Subscription::factory()->create([
        'organization_id' => $this->organization->id,
        'plan_id' => $plan->id,
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->withHeader('X-Organization-Id', $this->organization->id)
        ->getJson('/api/v1/usage');

    $response->assertOk()
        ->assertJsonPath('data.users.limit', 1)
        ->assertJsonPath('data.users.current', 1)
        ->assertJsonPath('data.users.remaining', 0)
        ->assertJsonPath('data.users.can_add', false);

    expect((float) $response->json('data.users.percentage'))->toBe(100.0);
});

it('requires tenant context for usage endpoint', function (): void {
    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/usage');

    $response->assertForbidden()
        ->assertJsonPath('message', 'Organization context is required. Please provide X-Organization-Id header.');
});
