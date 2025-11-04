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
    $this->role = Role::factory()->create([
        'organization_id' => $this->organization->id,
        'slug' => 'owner',
    ]);

    $this->user->organizations()->attach($this->organization->id, ['role_id' => $this->role->id]);
});

it('creates a role with valid data', function (): void {
    $response = $this->actingAs($this->user, 'sanctum')
        ->withHeader('X-Organization-Id', $this->organization->id)
        ->postJson('/api/v1/roles', [
            'name' => 'Admin',
            'slug' => 'admin',
            'description' => 'Administrator role',
        ]);

    $response->assertCreated()
        ->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'slug',
                'description',
            ],
        ]);

    expect(Role::where('slug', 'admin')->exists())->toBeTrue();
});

it('rejects creating role without tenant context', function (): void {
    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/roles', [
            'name' => 'Admin',
            'slug' => 'admin',
        ]);

    $response->assertForbidden();
});
