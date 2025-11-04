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

it('creates a permission with valid data', function (): void {
    $response = $this->actingAs($this->user, 'sanctum')
        ->withHeader('X-Organization-Id', $this->organization->id)
        ->postJson('/api/v1/permissions', [
            'name' => 'Create Posts',
            'slug' => 'posts.create',
            'description' => 'Can create new posts',
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

    expect(Permission::where('slug', 'posts.create')->exists())->toBeTrue();
});

it('rejects creating permission without tenant context', function (): void {
    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/permissions', [
            'name' => 'Create Posts',
            'slug' => 'posts.create',
        ]);

    $response->assertForbidden();
});

it('rejects duplicate permission slug within same tenant', function (): void {
    Permission::factory()->create([
        'organization_id' => $this->organization->id,
        'slug' => 'posts.create',
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->withHeader('X-Organization-Id', $this->organization->id)
        ->postJson('/api/v1/permissions', [
            'name' => 'Create Posts Duplicate',
            'slug' => 'posts.create',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['slug']);
});
