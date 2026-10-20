<?php

use App\Models\Invitation;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Features\Acl\Shared\Support\SystemRoles;
use Features\Tenant\Shared\Enums\InvitationStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('never hands out the owner role', function (): void {
    [$admin, $organization] = userInOrganization('admin');
    $target = User::factory()->create();
    $ownerRole = SystemRoles::ensure(SystemRoles::OWNER);

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Organization-Id', $organization->id)
        ->postJson("/api/v1/organizations/{$organization->id}/users", ['user_id' => $target->id, 'role_id' => $ownerRole->id])
        ->assertUnprocessable();

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Organization-Id', $organization->id)
        ->postJson('/api/v1/invitations', ['email' => 'x@example.com', 'role_id' => $ownerRole->id])
        ->assertUnprocessable();
});

it('rejects roles that belong to another organization', function (): void {
    [$admin, $organization] = userInOrganization('admin');
    $foreignRole = Role::factory()->create(['organization_id' => Organization::factory()->create()->id]);
    $target = User::factory()->create();

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Organization-Id', $organization->id)
        ->postJson("/api/v1/organizations/{$organization->id}/users", ['user_id' => $target->id, 'role_id' => $foreignRole->id])
        ->assertUnprocessable();
});

it('protects the owner from removal and role changes', function (): void {
    [$admin, $organization, $role] = userInOrganization('admin');
    $owner = $organization->owner;
    $organization->users()->attach($owner->id, ['role_id' => $role->id]);
    $memberRole = Role::factory()->create(['organization_id' => $organization->id]);

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Organization-Id', $organization->id)
        ->deleteJson("/api/v1/organizations/{$organization->id}/users/{$owner->id}")
        ->assertUnprocessable();

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Organization-Id', $organization->id)
        ->putJson("/api/v1/organizations/{$organization->id}/users/{$owner->id}/role", ['role_id' => $memberRole->id])
        ->assertUnprocessable();

    expect($organization->hasUser($owner))->toBeTrue();
});

it('does not let admins remove other admins', function (): void {
    [$admin, $organization] = userInOrganization('admin');
    [$otherAdmin] = userInOrganization('admin', $organization);

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Organization-Id', $organization->id)
        ->deleteJson("/api/v1/organizations/{$organization->id}/users/{$otherAdmin->id}")
        ->assertForbidden();
});

it('only updates the fields that were sent', function (): void {
    [$admin, $organization] = userInOrganization('admin');
    $organization->update(['description' => 'Keep me']);
    $slug = $organization->slug;

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Organization-Id', $organization->id)
        ->putJson("/api/v1/organizations/{$organization->id}", ['description' => 'Changed'])
        ->assertOk();

    $organization->refresh();

    expect($organization->is_active)->toBeTrue()
        ->and($organization->slug)->toBe($slug)
        ->and($organization->description)->toBe('Changed');
});

it('generates a slug for names without slug-able characters', function (): void {
    SystemRoles::ensure(SystemRoles::OWNER);
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/organizations', ['name' => '🚀🚀'])
        ->assertCreated();

    expect($response->json('data.slug'))->toStartWith('organization');
});

it('creates organizations even when the owner role was never seeded', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/organizations', ['name' => 'Acme'])
        ->assertCreated();

    expect(SystemRoles::find(SystemRoles::OWNER))->not->toBeNull();
});

it('accepts invitations regardless of e-mail casing and only once', function (): void {
    $organization = Organization::factory()->create();
    $role = Role::factory()->create(['organization_id' => $organization->id]);
    $user = User::factory()->create(['email' => 'invitee@example.com']);
    $invitation = Invitation::factory()->create([
        'organization_id' => $organization->id,
        'role_id' => $role->id,
        'email' => 'Invitee@Example.com',
    ]);

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/invitations/token/{$invitation->token}/accept")
        ->assertOk();

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/invitations/token/{$invitation->token}/accept")
        ->assertUnprocessable();
});

it('only lets the invitee reject an invitation', function (): void {
    $invitation = Invitation::factory()->create(['email' => 'invitee@example.com']);
    $stranger = User::factory()->create();

    $this->actingAs($stranger, 'sanctum')
        ->postJson("/api/v1/invitations/token/{$invitation->token}/reject")
        ->assertUnprocessable();

    expect($invitation->fresh()->status)->toBe(InvitationStatus::PENDING->value);
});

it('does not let an expired pending invitation block a new one', function (): void {
    [$admin, $organization] = userInOrganization('admin');
    $role = Role::factory()->create(['organization_id' => $organization->id]);
    $stale = Invitation::factory()->create([
        'organization_id' => $organization->id,
        'role_id' => $role->id,
        'email' => 'late@example.com',
        'expires_at' => now()->subDay(),
    ]);

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Organization-Id', $organization->id)
        ->postJson('/api/v1/invitations', ['email' => 'LATE@example.com', 'role_id' => $role->id])
        ->assertCreated();

    expect($stale->fresh()->status)->toBe(InvitationStatus::EXPIRED->value);
});

it('keeps the final status of answered invitations', function (): void {
    $invitation = Invitation::factory()->accepted()->create(['expires_at' => now()->subDay()]);

    expect($invitation->isExpired())->toBeFalse();

    $invitation->markAsExpired();

    expect($invitation->fresh()->status)->toBe(InvitationStatus::ACCEPTED->value);
});

it('refuses to delete a role that is still assigned', function (): void {
    [$admin, $organization] = userInOrganization('admin');
    [, , $memberRole] = userInOrganization('editor', $organization);

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Organization-Id', $organization->id)
        ->deleteJson("/api/v1/roles/{$memberRole->id}")
        ->assertUnprocessable();
});

it('treats system roles as read-only for tenants', function (): void {
    [$admin, $organization] = userInOrganization('admin');
    $systemRole = SystemRoles::ensure(SystemRoles::MEMBER);

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Organization-Id', $organization->id)
        ->putJson("/api/v1/roles/{$systemRole->id}", ['name' => 'Renamed'])
        ->assertForbidden();

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Organization-Id', $organization->id)
        ->getJson('/api/v1/roles')
        ->assertOk()
        ->assertJsonFragment(['id' => $systemRole->id]);
});

it('clamps pagination parameters', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/organizations?per_page=100000&page=abc')
        ->assertOk()
        ->assertJsonPath('meta.per_page', 100)
        ->assertJsonPath('meta.current_page', 1);
});
