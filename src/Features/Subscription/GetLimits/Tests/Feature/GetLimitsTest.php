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

it('returns plan limits for subscribed organizations', function (): void {
    $plan = Plan::factory()->create([
        'limits' => [
            'users' => 10,
            'storage' => 5000,
            'projects' => 3,
        ],
    ]);

    Subscription::factory()->create([
        'organization_id' => $this->organization->id,
        'plan_id' => $plan->id,
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->withHeader('X-Organization-Id', $this->organization->id)
        ->getJson('/api/v1/usage/limits');

    $response->assertOk()
        ->assertJsonPath('data.users', 10)
        ->assertJsonPath('data.storage', 5000)
        ->assertJsonPath('data.projects', 3);
});

it('falls back to default limits without an active subscription', function (): void {
    $response = $this->actingAs($this->user, 'sanctum')
        ->withHeader('X-Organization-Id', $this->organization->id)
        ->getJson('/api/v1/usage/limits');

    $response->assertOk()
        ->assertJsonPath('data.users', 5)
        ->assertJsonPath('data.projects', 1)
        ->assertJsonPath('data.api_calls', 1000);
});
