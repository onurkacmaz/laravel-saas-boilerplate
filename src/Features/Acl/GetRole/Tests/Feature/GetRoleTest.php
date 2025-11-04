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

it('shows a specific role', function (): void {
    $role = Role::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'Manager',
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->withHeader('X-Organization-Id', $this->organization->id)
        ->getJson("/api/v1/roles/{$role->id}");

    $response->assertOk()
        ->assertJson([
            'data' => [
                'id' => $role->id,
                'name' => 'Manager',
            ],
        ]);
});
