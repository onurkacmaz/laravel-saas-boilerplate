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
    $this->adminRole = Role::factory()->create(['organization_id' => $this->organization->id, 'slug' => 'admin']);

    $this->user->organizations()->attach($this->organization->id, ['role_id' => $this->adminRole->id]);
});

it('updates a user role within the organization', function (): void {
    $targetUser = User::factory()->create();
    $oldRole = Role::factory()->create(['organization_id' => $this->organization->id]);
    $newRole = Role::factory()->create(['organization_id' => $this->organization->id]);

    $this->organization->users()->attach($targetUser->id, ['role_id' => $oldRole->id]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->withHeader('X-Organization-Id', $this->organization->id)
        ->putJson("/api/v1/organizations/{$this->organization->id}/users/{$targetUser->id}/role", [
            'role_id' => $newRole->id,
        ]);

    $response->assertOk();

    $pivot = $this->organization->users()->where('users.id', $targetUser->id)->first()->pivot;
    expect($pivot->role_id)->toBe($newRole->id);
});
