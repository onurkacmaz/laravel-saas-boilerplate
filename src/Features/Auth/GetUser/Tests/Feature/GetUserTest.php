<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('returns the authenticated user profile', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/user');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => ['id', 'name', 'email'],
            'status',
            'message',
        ])
        ->assertJson([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
});

it('rejects unauthenticated profile requests', function (): void {
    $response = $this->getJson('/api/v1/user');

    $response->assertUnauthorized();
});
