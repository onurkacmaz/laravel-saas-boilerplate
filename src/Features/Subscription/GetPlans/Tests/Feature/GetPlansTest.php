<?php

use App\Models\User;
use Features\Subscription\Infrastructure\Eloquent\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('returns only active subscription plans', function (): void {
    $user = User::factory()->create();
    $activePlans = Plan::factory()->count(2)->create();
    Plan::factory()->create(['is_active' => false]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/plans');

    $response->assertOk()
        ->assertJsonCount(2, 'data');

    $returnedIds = collect($response->json('data'))->pluck('id')->sort()->values()->all();
    $expectedIds = $activePlans->pluck('id')->sort()->values()->all();

    expect($returnedIds)->toEqual($expectedIds);
});
