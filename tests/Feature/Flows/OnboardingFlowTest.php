<?php

use App\Models\Invitation;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Features\Acl\Shared\Support\SystemRoles;
use Features\Tenant\SendInvitation\Notifications\OrganizationInvitation;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

/**
 * Sign-up → e-mail verification → team setup → invitation → collaboration →
 * promotion → removal, exercised end to end through the public API.
 */
it('takes a team from sign-up to collaboration', function (): void {
    Notification::fake();
    $this->seed(RoleSeeder::class);

    // 1. The founder signs up and gets a personal organization.
    $this->postJson('/api/v1/auth/register', [
        'name' => 'Founder',
        'email' => 'Founder@Example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ])->assertCreated()->assertJsonPath('data.email', 'founder@example.com');

    $founder = User::query()->where('email', 'founder@example.com')->firstOrFail();
    expect($founder->hasVerifiedEmail())->toBeFalse();

    // 2. The founder verifies the e-mail address with the link from the e-mail.
    $verificationUrl = null;
    Notification::assertSentTo($founder, VerifyEmail::class, function (VerifyEmail $notification) use ($founder, &$verificationUrl) {
        $verificationUrl = $notification->toMail($founder)->actionUrl;

        return true;
    });
    $this->getJson($verificationUrl)->assertOk();
    expect($founder->fresh()->hasVerifiedEmail())->toBeTrue();

    // 3. The founder logs in and sees the personal organization.
    $token = $this->postJson('/api/v1/auth/login', ['email' => 'founder@example.com', 'password' => 'Password123!'])
        ->assertOk()
        ->json('data.token');

    $organizations = actingWithToken($token)->getJson('/api/v1/organizations')->assertOk()->json('data');
    expect($organizations)->toHaveCount(1);

    // 4. The founder creates the company organization and configures it.
    $organizationId = actingWithToken($token)
        ->postJson('/api/v1/organizations', ['name' => 'Acme Inc', 'description' => 'Rockets'])
        ->assertCreated()
        ->json('data.id');

    $tenant = fn () => actingWithToken($token)->withHeader('X-Organization-Id', $organizationId);

    $tenant()->putJson("/api/v1/organizations/{$organizationId}/settings", ['timezone' => 'Europe/Berlin', 'locale' => 'de'])
        ->assertOk()
        ->assertJsonPath('data.timezone', 'Europe/Berlin');

    // 5. The founder invites a colleague as a member.
    $memberRole = SystemRoles::find(SystemRoles::MEMBER);

    $tenant()->postJson('/api/v1/invitations', ['email' => 'colleague@example.com', 'role_id' => $memberRole->id])
        ->assertCreated();

    $invitationToken = null;
    Notification::assertSentOnDemand(OrganizationInvitation::class, function (OrganizationInvitation $notification) use (&$invitationToken) {
        $invitationToken = $notification->invitation->token;

        return $notification->invitation->email === 'colleague@example.com';
    });

    // 6. The colleague signs up, previews and accepts the invitation.
    actingWithToken(null)->postJson('/api/v1/auth/register', [
        'name' => 'Colleague',
        'email' => 'colleague@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ])->assertCreated();

    $colleagueToken = actingWithToken(null)->postJson('/api/v1/auth/login', ['email' => 'colleague@example.com', 'password' => 'Password123!'])
        ->json('data.token');

    actingWithToken($colleagueToken)->getJson("/api/v1/invitations/token/{$invitationToken}")
        ->assertOk()
        ->assertJsonPath('data.organization.name', 'Acme Inc');

    actingWithToken($colleagueToken)->postJson("/api/v1/invitations/token/{$invitationToken}/accept")
        ->assertOk()
        ->assertJsonPath('data.status', 'accepted');

    $colleague = User::query()->where('email', 'colleague@example.com')->firstOrFail();
    $asColleague = fn () => actingWithToken($colleagueToken)->withHeader('X-Organization-Id', $organizationId);

    // 7. As a member the colleague can read, but not administer.
    $asColleague()->getJson("/api/v1/organizations/{$organizationId}")
        ->assertOk()
        ->assertJsonCount(2, 'data.users');
    $asColleague()->getJson('/api/v1/invitations')->assertForbidden();

    // 8. The founder promotes the colleague to admin; now administration works.
    $adminRole = SystemRoles::find(SystemRoles::ADMIN);

    $tenant()->putJson("/api/v1/organizations/{$organizationId}/users/{$colleague->id}/role", ['role_id' => $adminRole->id])
        ->assertOk();

    $asColleague()->getJson('/api/v1/invitations')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    // ...but an admin still cannot touch the owner or delete the organization.
    $asColleague()->deleteJson("/api/v1/organizations/{$organizationId}/users/{$founder->id}")->assertUnprocessable();
    $asColleague()->deleteJson("/api/v1/organizations/{$organizationId}")->assertForbidden();

    // 9. The founder removes the colleague, who immediately loses access.
    $tenant()->deleteJson("/api/v1/organizations/{$organizationId}/users/{$colleague->id}")->assertNoContent();

    $asColleague()->getJson("/api/v1/organizations/{$organizationId}")->assertForbidden();

    expect(Organization::find($organizationId)->hasUser($colleague))->toBeFalse()
        ->and(Invitation::query()->where('email', 'colleague@example.com')->value('status'))->toBe('accepted');
});
