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

it('updates a role', function (): void {
    $role = Role::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'Editor',
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->withHeader('X-Organization-Id', $this->organization->id)
        ->putJson("/api/v1/roles/{$role->id}", [
            'name' => 'Senior Editor',
            'description' => 'Updated description',
        ]);

    $response->assertOk()
        ->assertJson([
            'data' => [
                'name' => 'Senior Editor',
            ],
        ]);

    $role->refresh();
    expect($role->name)->toBe('Senior Editor');
});
