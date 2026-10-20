<?php

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Export → request deletion → change of mind → request again → grace period
 * elapses → scheduled erasure hands the organization to an admin.
 */
it('erases an account after the grace period', function (): void {
    [$admin, $organization] = userInOrganization('admin');
    $owner = $organization->owner;
    $owner->forceFill(['password' => 'Password123!'])->save();
    $ownerEmail = $owner->email;
    $owner->createToken('laptop');

    $asOwner = fn () => $this->actingAs($owner, 'sanctum');

    $asOwner()->getJson('/api/v1/gdpr/export')
        ->assertOk()
        ->assertJsonPath('data.personal_information.email', $ownerEmail)
        ->assertJsonPath('data.owned_organizations.0.id', $organization->id);

    $asOwner()->postJson('/api/v1/gdpr/request-deletion')->assertOk();
    $asOwner()->postJson('/api/v1/gdpr/cancel-deletion')->assertOk();

    // Cancelled requests are never processed.
    $this->travel(40)->days();
    $this->artisan('gdpr:process-deletions')->expectsOutput('No users scheduled for deletion.');
    $this->travelBack();

    $asOwner()->postJson('/api/v1/gdpr/request-deletion')->assertOk();

    // Still inside the grace period: nothing happens.
    $this->travel(29)->days();
    $this->artisan('gdpr:process-deletions')->expectsOutput('No users scheduled for deletion.');

    // Grace period over: the account is erased, the organization survives.
    $this->travel(2)->days();
    $this->artisan('gdpr:process-deletions')->assertSuccessful();

    $erased = User::withTrashed()->find($owner->id);

    expect($erased->trashed())->toBeTrue()
        ->and($erased->email)->not->toBe($ownerEmail)
        ->and($erased->tokens()->count())->toBe(0)
        ->and(Organization::find($organization->id)->owner_id)->toBe($admin->id)
        ->and(Organization::find($organization->id)->isOwner($admin))->toBeTrue();

    actingWithToken(null)->postJson('/api/v1/auth/login', ['email' => $ownerEmail, 'password' => 'Password123!'])->assertUnauthorized();
});
