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

it('shows a specific permission', function (): void {
    $permission = Permission::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'Edit Posts',
        'slug' => 'posts.edit',
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->withHeader('X-Organization-Id', $this->organization->id)
        ->getJson("/api/v1/permissions/{$permission->id}");

    $response->assertOk()
        ->assertJson([
            'data' => [
                'id' => $permission->id,
                'name' => 'Edit Posts',
                'slug' => 'posts.edit',
            ],
        ]);
});
