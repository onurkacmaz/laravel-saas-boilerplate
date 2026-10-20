<?php

use App\Models\Organization;
use App\Models\User;
use Features\Audit\Infrastructure\Eloquent\Models\AuditLog;
use Features\Webhooks\Infrastructure\Eloquent\Models\Webhook;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('prevents acting on another organization by swapping the URL', function (): void {
    [$user, $organization] = userInOrganization('owner');
    $victim = Organization::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->withHeader('X-Organization-Id', $organization->id)
        ->deleteJson("/api/v1/organizations/{$victim->id}")
        ->assertForbidden();

    $this->actingAs($user, 'sanctum')
        ->withHeader('X-Organization-Id', $organization->id)
        ->getJson("/api/v1/organizations/{$victim->id}")
        ->assertForbidden();

    expect(Organization::find($victim->id))->not->toBeNull();
});

it('does not set a tenant context for organizations the user does not belong to', function (): void {
    $user = User::factory()->create();
    $victim = Organization::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->withHeader('X-Organization-Id', $victim->id)
        ->getJson("/api/v1/organizations/{$victim->id}")
        ->assertForbidden();
});

it('forbids regular members from administrative actions', function (): void {
    [$member, $organization] = userInOrganization('member');

    $this->actingAs($member, 'sanctum')
        ->withHeader('X-Organization-Id', $organization->id)
        ->putJson("/api/v1/organizations/{$organization->id}", ['name' => 'Hijacked'])
        ->assertForbidden();

    $this->actingAs($member, 'sanctum')
        ->withHeader('X-Organization-Id', $organization->id)
        ->deleteJson("/api/v1/organizations/{$organization->id}")
        ->assertForbidden();

    $this->actingAs($member, 'sanctum')
        ->withHeader('X-Organization-Id', $organization->id)
        ->postJson('/api/v1/roles', ['name' => 'Super'])
        ->assertForbidden();

    expect($organization->fresh()->name)->not->toBe('Hijacked');
});

it('only lets the owner delete the organization', function (): void {
    [$admin, $organization] = userInOrganization('admin');

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Organization-Id', $organization->id)
        ->deleteJson("/api/v1/organizations/{$organization->id}")
        ->assertForbidden();

    $this->actingAs($organization->owner, 'sanctum')
        ->withHeader('X-Organization-Id', $organization->id)
        ->deleteJson("/api/v1/organizations/{$organization->id}")
        ->assertNoContent();
});

it('never exposes two-factor secrets of organization members', function (): void {
    [$user, $organization] = userInOrganization('member');
    $user->forceFill([
        'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
        'two_factor_recovery_codes' => '["secret"]',
    ])->save();

    $response = $this->actingAs($user, 'sanctum')
        ->withHeader('X-Organization-Id', $organization->id)
        ->getJson("/api/v1/organizations/{$organization->id}")
        ->assertOk();

    expect($response->getContent())
        ->not->toContain('JBSWY3DPEHPK3PXP')
        ->not->toContain('two_factor_secret')
        ->not->toContain('two_factor_recovery_codes');
});

it('hides webhooks of other organizations', function (): void {
    [$admin, $organization] = userInOrganization('admin');
    $foreignWebhook = Webhook::factory()->create();

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Organization-Id', $organization->id)
        ->getJson("/api/v1/webhooks/{$foreignWebhook->id}")
        ->assertNotFound();

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Organization-Id', $organization->id)
        ->deleteJson("/api/v1/webhooks/{$foreignWebhook->id}")
        ->assertNotFound();

    expect(Webhook::find($foreignWebhook->id))->not->toBeNull();
});

it('hides audit logs of other organizations', function (): void {
    [$admin, $organization] = userInOrganization('admin');
    $foreignLog = AuditLog::factory()->create();

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Organization-Id', $organization->id)
        ->getJson("/api/v1/audit-logs/{$foreignLog->id}")
        ->assertNotFound()
        ->assertJsonPath('message', 'Resource not found.');
});

it('lists audit logs of the current organization only', function (): void {
    [$admin, $organization] = userInOrganization('admin');
    AuditLog::factory()->count(2)->forOrganization($organization)->create();
    AuditLog::factory()->create();

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Organization-Id', $organization->id)
        ->getJson('/api/v1/audit-logs?per_page=50')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', 2);

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Organization-Id', $organization->id)
        ->getJson('/api/v1/audit-logs/events')
        ->assertOk();
});

it('handles malformed identifiers gracefully', function (): void {
    [$user, $organization] = userInOrganization('admin');

    $this->actingAs($user, 'sanctum')
        ->withHeader('X-Organization-Id', 'not-a-uuid')
        ->getJson('/api/v1/invitations')
        ->assertForbidden();

    $this->actingAs($user, 'sanctum')
        ->withHeader('X-Organization-Id', $organization->id)
        ->getJson('/api/v1/organizations/not-a-uuid')
        ->assertNotFound();

    $this->getJson('/api/v1/plans/not-a-uuid')->assertNotFound();
});

it('records the organization on audit entries of tenant requests', function (): void {
    [$admin, $organization] = userInOrganization('admin');

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Organization-Id', $organization->id)
        ->putJson("/api/v1/organizations/{$organization->id}", ['description' => 'Audited'])
        ->assertOk();

    $log = AuditLog::query()->where('event', 'organizations.update')->first();

    expect($log)->not->toBeNull()
        ->and($log->organization_id)->toBe($organization->id)
        ->and($log->user_id)->toBe($admin->id);
});
