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

it('removes a user from the organization', function (): void {
    $userToRemove = User::factory()->create();
    $role = Role::factory()->create(['organization_id' => $this->organization->id]);

    $this->organization->users()->attach($userToRemove->id, ['role_id' => $role->id]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->withHeader('X-Organization-Id', $this->organization->id)
        ->deleteJson("/api/v1/organizations/{$this->organization->id}/users/{$userToRemove->id}");

    $response->assertNoContent();

    expect($this->organization->users()->where('users.id', $userToRemove->id)->exists())->toBeFalse();
});
