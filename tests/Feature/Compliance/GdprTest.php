<?php

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('schedules and cancels an account deletion', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/gdpr/request-deletion')
        ->assertOk()
        ->assertJsonPath('data.deletion_date', now()->addDays(30)->toDateString());

    expect($user->fresh()->deletion_requested_at)->not->toBeNull();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/gdpr/cancel-deletion')
        ->assertOk();

    expect($user->fresh()->deletion_requested_at)->toBeNull();
});

it('does not allow destructive actions through GET', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')->getJson('/api/v1/gdpr/delete')->assertMethodNotAllowed();
    $this->actingAs($user, 'sanctum')->getJson('/api/v1/gdpr/request-deletion')->assertMethodNotAllowed();
});

it('exports the user data with the role names', function (): void {
    [$user, $organization, $role] = userInOrganization('admin');

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/gdpr/export')
        ->assertOk()
        ->assertJsonPath('data.personal_information.email', $user->email)
        ->assertJsonPath('data.organizations.0.role', $role->name)
        ->assertJsonMissingPath('data.personal_information.password');
});

it('deletes the account immediately only with the password and anonymizes it', function (): void {
    $user = User::factory()->create(['password' => 'Password123!']);
    $organization = Organization::factory()->ownedBy($user)->create();
    $user->createToken('device');
    $originalEmail = $user->email;

    $this->actingAs($user, 'sanctum')
        ->deleteJson('/api/v1/gdpr/delete', ['password' => 'wrong'])
        ->assertUnprocessable();

    $this->actingAs($user, 'sanctum')
        ->deleteJson('/api/v1/gdpr/delete', ['password' => 'Password123!'])
        ->assertNoContent();

    $deleted = User::withTrashed()->find($user->id);

    expect($deleted->trashed())->toBeTrue()
        ->and($deleted->email)->not->toBe($originalEmail)
        ->and($deleted->name)->toBe('Deleted User')
        ->and($deleted->tokens()->count())->toBe(0)
        ->and(Organization::find($organization->id))->toBeNull();
});

it('transfers owned organizations to an admin', function (): void {
    [$admin, $organization] = userInOrganization('admin');
    $owner = $organization->owner;

    $this->artisan('gdpr:process-deletions')->assertSuccessful();

    $owner->forceFill(['deletion_requested_at' => now()->subDays(31)])->save();

    $this->artisan('gdpr:process-deletions')->assertSuccessful();

    expect($organization->fresh()->owner_id)->toBe($admin->id)
        ->and(User::find($owner->id))->toBeNull();
});
