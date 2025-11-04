<?php

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Infrastructure\Tenant\TenantContext;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    TenantContext::clear();
});

afterEach(function (): void {
    TenantContext::clear();
});

it('scopes queries to the current tenant', function (): void {
    $owner = User::factory()->create();
    $orgA = Organization::factory()->ownedBy($owner)->create(['name' => 'Org A', 'slug' => 'org-a']);
    $orgB = Organization::factory()->ownedBy($owner)->create(['name' => 'Org B', 'slug' => 'org-b']);

    Role::factory()->create(['organization_id' => $orgA->id]);
    Role::factory()->create(['organization_id' => $orgB->id]);

    TenantContext::setOrganization($orgA);

    expect(Role::all())->toHaveCount(1);
    expect(Role::first()->organization_id)->toBe($orgA->id);
});

it('provides query macros to bypass global scope', function (): void {
    $owner = User::factory()->create();
    $orgA = Organization::factory()->ownedBy($owner)->create(['name' => 'Org C', 'slug' => 'org-c']);
    $orgB = Organization::factory()->ownedBy($owner)->create(['name' => 'Org D', 'slug' => 'org-d']);

    $roleA = Role::factory()->create(['organization_id' => $orgA->id]);
    $roleB = Role::factory()->create(['organization_id' => $orgB->id]);

    TenantContext::setOrganization($orgA);

    expect(Role::query()->withoutOrganization()->count())->toBe(2);
    expect(Role::query()->forOrganization($orgB->id)->pluck('id'))->toContain($roleB->id);
    expect(Role::query()->forAllOrganizations()->count())->toBe(2);
});

it('auto assigns organization identifier on creation when context exists', function (): void {
    $owner = User::factory()->create();
    $organization = Organization::factory()->ownedBy($owner)->create(['name' => 'Org E', 'slug' => 'org-e']);

    TenantContext::setOrganization($organization);

    $role = Role::factory()->create([
        'organization_id' => null,
    ]);

    expect($role->organization_id)->toBe($organization->id);
});
