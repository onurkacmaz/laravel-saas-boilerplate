<?php

use App\Models\Invitation;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->organization = Organization::factory()->create();
    $this->role = Role::factory()->create(['organization_id' => $this->organization->id]);
});

it('accepts an invitation for the matching user', function (): void {
    $invitedUser = User::factory()->create(['email' => 'invited@example.com']);

    $invitation = Invitation::factory()->create([
        'organization_id' => $this->organization->id,
        'role_id' => $this->role->id,
        'email' => 'invited@example.com',
        'status' => 'pending',
    ]);

    $response = $this->actingAs($invitedUser, 'sanctum')
        ->postJson("/api/v1/invitations/token/{$invitation->token}/accept");

    $response->assertOk();

    $invitation->refresh();
    expect($invitation->status)->toBe('accepted')
        ->and($this->organization->users()->where('users.id', $invitedUser->id)->exists())->toBeTrue();
});

it('rejects accepting expired invitations', function (): void {
    $invitedUser = User::factory()->create(['email' => 'invited@example.com']);

    $invitation = Invitation::factory()->create([
        'organization_id' => $this->organization->id,
        'role_id' => $this->role->id,
        'email' => 'invited@example.com',
        'status' => 'pending',
        'expires_at' => now()->subDay(),
    ]);

    $response = $this->actingAs($invitedUser, 'sanctum')
        ->postJson("/api/v1/invitations/token/{$invitation->token}/accept");

    $response->assertUnprocessable();
});

it('rejects accepting invitation with mismatched email', function (): void {
    $wrongUser = User::factory()->create(['email' => 'wrong@example.com']);

    $invitation = Invitation::factory()->create([
        'organization_id' => $this->organization->id,
        'role_id' => $this->role->id,
        'email' => 'invited@example.com',
        'status' => 'pending',
    ]);

    $response = $this->actingAs($wrongUser, 'sanctum')
        ->postJson("/api/v1/invitations/token/{$invitation->token}/accept");

    $response->assertUnprocessable();
});

it('rejects accepting already accepted invitation', function (): void {
    $invitedUser = User::factory()->create(['email' => 'invited@example.com']);

    $invitation = Invitation::factory()->create([
        'organization_id' => $this->organization->id,
        'role_id' => $this->role->id,
        'email' => 'invited@example.com',
        'status' => 'accepted',
    ]);

    $response = $this->actingAs($invitedUser, 'sanctum')
        ->postJson("/api/v1/invitations/token/{$invitation->token}/accept");

    $response->assertUnprocessable();
});
