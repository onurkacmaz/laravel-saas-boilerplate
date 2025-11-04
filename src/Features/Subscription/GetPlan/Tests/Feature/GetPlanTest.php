<?php

use App\Models\User;
use Features\Subscription\Infrastructure\Eloquent\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('returns plan details with features and limits', function (): void {
    $user = User::factory()->create();
    $plan = Plan::factory()->create([
        'features' => ['Feature A', 'Feature B'],
        'limits' => ['users' => 10, 'projects' => 5],
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson("/api/v1/plans/{$plan->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $plan->id)
        ->assertJsonPath('data.features.0', 'Feature A')
        ->assertJsonPath('data.limits.users', 10)
        ->assertJsonPath('data.formatted_price', $plan->getFormattedPrice());
});

it('returns not found for inactive plans', function (): void {
    $user = User::factory()->create();
    $plan = Plan::factory()->create(['is_active' => false]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson("/api/v1/plans/{$plan->id}");

    $response->assertNotFound();
});
