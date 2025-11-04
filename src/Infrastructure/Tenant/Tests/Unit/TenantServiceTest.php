<?php

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Infrastructure\Tenant\TenantContext;
use Infrastructure\Tenant\TenantService;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    TenantContext::clear();
    $this->service = app(TenantService::class);
});

afterEach(function (): void {
    TenantContext::clear();
});

it('returns active organizations accessible to the user', function (): void {
    $user = User::factory()->create();
    $ownedOrg = Organization::factory()->ownedBy($user)->create();

    $memberOrg = Organization::factory()->create();
    $memberRole = Role::factory()->create(['organization_id' => $memberOrg->id]);
    $memberOrg->users()->attach($user->id, ['role_id' => $memberRole->id]);

    Organization::factory()->ownedBy($user)->inactive()->create(); // should be filtered out

    $organizations = $this->service->getUserOrganizations($user);

    expect($organizations)->toHaveCount(2);
    expect($organizations->pluck('id'))->toContain($ownedOrg->id, $memberOrg->id);
});

it('detects the user role within an organization', function (): void {
    $user = User::factory()->create();
    $organization = Organization::factory()->ownedBy($user)->create();

    expect($this->service->getUserRoleInOrganization($user, $organization))->toBe('owner');

    $member = User::factory()->create();
    $role = Role::factory()->create(['organization_id' => $organization->id, 'slug' => 'admin']);
    $organization->users()->attach($member->id, ['role_id' => $role->id]);

    expect($this->service->getUserRoleInOrganization($member, $organization))->toBe($role->id);
});

it('confirms whether the user can access an organization', function (): void {
    $user = User::factory()->create();
    $organization = Organization::factory()->ownedBy($user)->create();

    expect($this->service->canAccessOrganization($user, $organization))->toBeTrue();

    $otherUser = User::factory()->create();
    expect($this->service->canAccessOrganization($otherUser, $organization))->toBeFalse();

    $role = Role::factory()->create(['organization_id' => $organization->id]);
    $organization->users()->attach($otherUser->id, ['role_id' => $role->id]);
    expect($this->service->canAccessOrganization($otherUser, $organization))->toBeTrue();
});

it('switches tenant context when the user can access the organization', function (): void {
    $user = User::factory()->create();
    $organization = Organization::factory()->ownedBy($user)->create();

    $result = $this->service->switchOrganization($user, $organization->id);

    expect($result)->toBeTrue();
    expect(TenantContext::getOrganization()?->id)->toBe($organization->id);
    expect(TenantContext::getUser()?->id)->toBe($user->id);
});

it('prevents switching to inaccessible or inactive organizations', function (): void {
    $user = User::factory()->create();
    $organization = Organization::factory()->create(); // other owner
    Organization::factory()->ownedBy($user)->inactive()->create(); // inactive

    expect($this->service->switchOrganization($user, $organization->id))->toBeFalse();

    $inactive = Organization::query()->where('is_active', false)->first();
    expect($inactive)->not->toBeNull();
    expect($this->service->switchOrganization($user, $inactive->id))->toBeFalse();
});

it('proxies helper methods to the tenant context', function (): void {
    $user = User::factory()->create();
    $organization = Organization::factory()->ownedBy($user)->create();

    $role = Role::factory()->create(['organization_id' => $organization->id, 'slug' => 'admin']);
    $organization->users()->attach($user->id, ['role_id' => $role->id]);

    $this->service->switchOrganization($user, $organization->id);

    expect($this->service->getCurrentOrganization()?->id)->toBe($organization->id);
    expect($this->service->currentUserIsOwner())->toBeTrue();
    expect($this->service->currentUserIsAdmin())->toBeTrue();
});
