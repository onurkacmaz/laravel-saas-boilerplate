<?php

use App\Models\Invitation;
use App\Models\Organization;
use App\Models\OrganizationSetting;
use App\Models\Role;
use App\Models\User;
use Features\Subscription\Infrastructure\Eloquent\Models\Subscription;
use Features\Tenant\SendInvitation\Notifications\OrganizationInvitation;
use Features\Tenant\Shared\Enums\InvitationStatus;
use Features\Tenant\Shared\Support\OrganizationRoles;
use Features\Tenant\Shared\Support\OrganizationSlug;
use Features\Webhooks\Infrastructure\Eloquent\Models\Webhook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Infrastructure\Tenant\TenantService;

uses(RefreshDatabase::class);

it('stops billing and integrations when an organization is deleted', function (): void {
    $organization = Organization::factory()->create();
    $subscription = Subscription::factory()->create([
        'organization_id' => $organization->id,
        'stripe_id' => Subscription::makeLocalStripeId(),
    ]);
    $webhook = Webhook::factory()->forOrganization($organization)->create();
    Invitation::factory()->create(['organization_id' => $organization->id]);
    Subscription::factory()->canceled()->create(['organization_id' => $organization->id]);

    $this->actingAs($organization->owner, 'sanctum')
        ->withHeader('X-Organization-Id', $organization->id)
        ->deleteJson("/api/v1/organizations/{$organization->id}")
        ->assertNoContent();

    expect($subscription->fresh()->stripe_status)->toBe('canceled')
        ->and($webhook->fresh()->is_active)->toBeFalse()
        ->and(Invitation::query()->where('organization_id', $organization->id)->count())->toBe(0);
});

it('renders invitation e-mails with frontend links', function (): void {
    config(['app.frontend_url' => 'https://app.example.test/']);
    $invitation = Invitation::factory()->create();
    $notifiable = (new AnonymousNotifiable)->route('mail', $invitation->email);
    $notification = new OrganizationInvitation($invitation);
    $mail = $notification->toMail($notifiable);

    expect($notification->via($notifiable))->toBe(['mail'])
        ->and($mail->actionUrl)->toBe("https://app.example.test/invitations/{$invitation->token}/accept")
        ->and(implode(' ', $mail->introLines))->toContain($invitation->invitedBy->name)
        ->and($notification->toArray($notifiable))->toMatchArray([
            'invitation_id' => $invitation->id,
            'organization_name' => $invitation->organization->name,
        ]);
});

it('refuses invitations that cannot be accepted anymore', function (): void {
    $user = User::factory()->create(['email' => 'invitee@example.com']);
    $organization = Organization::factory()->create();
    $role = Role::factory()->create(['organization_id' => $organization->id]);

    $alreadyMember = Invitation::factory()->create(['organization_id' => $organization->id, 'role_id' => $role->id, 'email' => $user->email]);
    $organization->users()->attach($user->id, ['role_id' => $role->id]);

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/invitations/token/{$alreadyMember->token}/accept")
        ->assertUnprocessable()
        ->assertJsonPath('message', 'You are already a member of this organization.');

    $otherOrganization = Organization::factory()->create();
    $deletedRole = Role::factory()->create(['organization_id' => $otherOrganization->id]);
    $orphan = Invitation::factory()->create(['organization_id' => $otherOrganization->id, 'role_id' => $deletedRole->id, 'email' => $user->email]);
    $deletedRole->delete();

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/invitations/token/{$orphan->token}/accept")
        ->assertUnprocessable();

    $expired = Invitation::factory()->create(['email' => $user->email, 'expires_at' => now()->subMinute()]);

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/invitations/token/{$expired->token}/accept")
        ->assertUnprocessable();

    expect($expired->fresh()->status)->toBe(InvitationStatus::EXPIRED->value);

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/invitations/token/{$expired->token}/reject")
        ->assertUnprocessable();
});

it('marks expired invitations when they are looked up', function (): void {
    $invitation = Invitation::factory()->create(['expires_at' => now()->subDay()]);

    $this->actingAs(User::factory()->create(), 'sanctum')
        ->getJson("/api/v1/invitations/token/{$invitation->token}")
        ->assertOk()
        ->assertJsonPath('data.status', 'expired')
        ->assertJsonPath('data.token', $invitation->token);
});

it('only cancels pending invitations of the current organization', function (): void {
    [$admin, $organization] = userInOrganization('admin');
    $accepted = Invitation::factory()->accepted()->create(['organization_id' => $organization->id]);
    $foreign = Invitation::factory()->create();

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Organization-Id', $organization->id)
        ->deleteJson("/api/v1/invitations/{$accepted->id}")
        ->assertUnprocessable();

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Organization-Id', $organization->id)
        ->deleteJson("/api/v1/invitations/{$foreign->id}")
        ->assertNotFound();
});

it('filters invitations by status', function (): void {
    [$admin, $organization] = userInOrganization('admin');
    Invitation::factory()->count(2)->create(['organization_id' => $organization->id]);
    Invitation::factory()->accepted()->create(['organization_id' => $organization->id]);

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Organization-Id', $organization->id)
        ->getJson('/api/v1/invitations?status=accepted')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('rejects inviting yourself or existing members', function (): void {
    [$admin, $organization, $role] = userInOrganization('admin');
    [$member] = userInOrganization('member', $organization);

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Organization-Id', $organization->id)
        ->postJson('/api/v1/invitations', ['email' => strtoupper($admin->email), 'role_id' => $role->id])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'You cannot invite yourself.');

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Organization-Id', $organization->id)
        ->postJson('/api/v1/invitations', ['email' => $member->email, 'role_id' => $role->id])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'User is already a member of this organization.');
});

it('rejects adding a user who is already a member', function (): void {
    [$admin, $organization, $role] = userInOrganization('admin');
    [$member] = userInOrganization('member', $organization);

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Organization-Id', $organization->id)
        ->postJson("/api/v1/organizations/{$organization->id}/users", ['user_id' => $member->id, 'role_id' => $role->id])
        ->assertUnprocessable();
});

it('lets the owner manage admins and nobody manage the owner', function (): void {
    [$admin, $organization] = userInOrganization('admin');
    $owner = $organization->owner;

    expect(OrganizationRoles::canManage($organization, $owner, $admin))->toBeTrue()
        ->and(OrganizationRoles::canManage($organization, $admin, $owner))->toBeFalse();

    $memberRole = Role::factory()->create(['organization_id' => $organization->id]);

    $this->actingAs($owner, 'sanctum')
        ->withHeader('X-Organization-Id', $organization->id)
        ->putJson("/api/v1/organizations/{$organization->id}/users/{$admin->id}/role", ['role_id' => $memberRole->id])
        ->assertOk();

    expect($organization->roleOf($admin)->id)->toBe($memberRole->id);
});

it('keeps slugs unique, including soft deleted organizations', function (): void {
    $deleted = Organization::factory()->create(['slug' => 'acme']);
    $deleted->delete();

    $slug = OrganizationSlug::generate('Acme');

    expect($slug)->not->toBe('acme')->toStartWith('acme-')
        ->and(OrganizationSlug::generate('Acme', $deleted->id))->toBe('acme');
});

it('renames the slug when the organization is renamed', function (): void {
    [$admin, $organization] = userInOrganization('admin');

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Organization-Id', $organization->id)
        ->putJson("/api/v1/organizations/{$organization->id}", ['name' => 'Brand New Name', 'is_active' => false])
        ->assertOk()
        ->assertJsonPath('data.slug', 'brand-new-name')
        ->assertJsonPath('data.is_active', false);
});

it('merges preference updates instead of replacing them', function (): void {
    [$admin, $organization] = userInOrganization('admin');
    OrganizationSetting::factory()->forOrganization($organization)->withNotifications(['email' => true, 'sms' => true])->create();

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Organization-Id', $organization->id)
        ->putJson("/api/v1/organizations/{$organization->id}/settings", [
            'notification_preferences' => ['sms' => false],
            'currency' => 'eur',
        ])
        ->assertOk()
        ->assertJsonPath('data.notification_preferences', ['email' => true, 'sms' => false])
        ->assertJsonPath('data.currency', 'EUR');
});

it('lists organizations owned or joined by the user', function (): void {
    $user = User::factory()->create();
    Organization::factory()->ownedBy($user)->create(['name' => 'B Owned']);
    [, $joined] = userInOrganization('member');
    $joined->users()->attach($user->id, ['role_id' => $joined->roleOf($joined->users()->first())->id]);
    Organization::factory()->create();

    $service = app(TenantService::class);

    expect($service->getUserOrganizations($user))->toHaveCount(2)
        ->and($service->switchOrganization($user, 'not-a-uuid'))->toBeFalse();

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/organizations')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});
