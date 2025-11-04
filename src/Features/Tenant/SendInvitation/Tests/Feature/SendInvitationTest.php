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

it('creates an invitation for the organization', function (): void {
    $targetRole = Role::factory()->create(['organization_id' => $this->organization->id]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->withHeader('X-Organization-Id', $this->organization->id)
        ->postJson('/api/v1/invitations', [
            'email' => 'newuser@example.com',
            'role_id' => $targetRole->id,
        ]);

    $response->assertCreated()
        ->assertJsonStructure([
            'data' => ['id', 'email', 'status', 'expires_at'],
        ]);

    expect(Invitation::where('email', 'newuser@example.com')->exists())->toBeTrue();
});

it('rejects creating an invitation without tenant context', function (): void {
    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/invitations', [
            'email' => 'test@example.com',
        ]);

    $response->assertForbidden();
});

it('rejects duplicate pending invitations for the same email', function (): void {
    $targetRole = Role::factory()->create(['organization_id' => $this->organization->id]);

    Invitation::factory()->create([
        'organization_id' => $this->organization->id,
        'email' => 'duplicate@example.com',
        'role_id' => $targetRole->id,
        'status' => 'pending',
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->withHeader('X-Organization-Id', $this->organization->id)
        ->postJson('/api/v1/invitations', [
            'email' => 'duplicate@example.com',
            'role_id' => $targetRole->id,
        ]);

    $response->assertUnprocessable();
});
