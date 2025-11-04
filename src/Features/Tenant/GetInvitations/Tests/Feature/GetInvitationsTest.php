<?php

use App\Models\Invitation;
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
    ]);

    $this->user->organizations()->attach($this->organization->id, ['role_id' => $this->role->id]);
});

it('lists invitations for the organization', function (): void {
    $targetRole = Role::factory()->create(['organization_id' => $this->organization->id]);

    Invitation::factory()->count(3)->create([
        'organization_id' => $this->organization->id,
        'role_id' => $targetRole->id,
        'invited_by' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->withHeader('X-Organization-Id', $this->organization->id)
        ->getJson('/api/v1/invitations');

    $response->assertOk()
        ->assertJsonCount(3, 'data');
});
