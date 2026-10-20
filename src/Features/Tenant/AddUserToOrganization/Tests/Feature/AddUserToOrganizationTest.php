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

it('adds a user to the organization', function (): void {
    $newUser = User::factory()->create();
    $role = Role::factory()->create(['organization_id' => $this->organization->id]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->withHeader('X-Organization-Id', $this->organization->id)
        ->postJson("/api/v1/organizations/{$this->organization->id}/users", [
            'user_id' => $newUser->id,
            'role_id' => $role->id,
        ]);

    $response->assertCreated();

    expect($this->organization->users()->where('users.id', $newUser->id)->exists())->toBeTrue();
});

it('prevents adding a non-existent user', function (): void {
    $role = Role::factory()->create(['organization_id' => $this->organization->id]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->withHeader('X-Organization-Id', $this->organization->id)
        ->postJson("/api/v1/organizations/{$this->organization->id}/users", [
            'user_id' => '99999999-9999-9999-9999-999999999999',
            'role_id' => $role->id,
        ]);

    $response->assertUnprocessable();
});
