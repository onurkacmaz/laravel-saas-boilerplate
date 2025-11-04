<?php

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->organization = Organization::factory()->create();
    $this->ownerRole = Role::factory()->create([
        'organization_id' => $this->organization->id,
        'slug' => 'owner',
    ]);

    $this->user->organizations()->attach($this->organization->id, ['role_id' => $this->ownerRole->id]);
});

it('lists all roles for the tenant', function (): void {
    Role::factory()->count(3)->create([
        'organization_id' => $this->organization->id,
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->withHeader('X-Organization-Id', $this->organization->id)
        ->getJson('/api/v1/roles');

    $response->assertOk()
        ->assertJsonCount(4, 'data');
});

it('prevents unauthenticated access to roles', function (): void {
    $response = $this->getJson('/api/v1/roles');

    $response->assertUnauthorized();
});
