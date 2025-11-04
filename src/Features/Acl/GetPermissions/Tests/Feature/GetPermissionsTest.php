<?php

use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->organization = Organization::factory()->create();
    $this->role = Role::factory()->create([
        'organization_id' => $this->organization->id,
        'slug' => 'owner',
    ]);

    $this->user->organizations()->attach($this->organization->id, ['role_id' => $this->role->id]);
});

it('lists all permissions for the tenant', function (): void {
    Permission::factory()->count(5)->create([
        'organization_id' => $this->organization->id,
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->withHeader('X-Organization-Id', $this->organization->id)
        ->getJson('/api/v1/permissions');

    $response->assertOk()
        ->assertJsonCount(5, 'data');
});

it('prevents unauthenticated access to permissions', function (): void {
    $response = $this->getJson('/api/v1/permissions');

    $response->assertUnauthorized();
});
