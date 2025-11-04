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

it('assigns a role to a user', function (): void {
    $targetUser = User::factory()->create();
    $this->organization->users()->attach($targetUser->id, ['role_id' => $this->ownerRole->id]);

    $role = Role::factory()->create([
        'organization_id' => $this->organization->id,
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->withHeader('X-Organization-Id', $this->organization->id)
        ->postJson("/api/v1/users/{$targetUser->id}/roles", [
            'role_id' => $role->id,
        ]);

    $response->assertOk();

    expect($targetUser->roles()->where('roles.id', $role->id)->exists())->toBeTrue();
});
