<?php

use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Features\Acl\Shared\Support\SystemRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Infrastructure\Tenant\TenantContext;

uses(RefreshDatabase::class);

afterEach(fn () => TenantContext::clear());

function systemPermission(string $slug): Permission
{
    return Permission::factory()->create(['organization_id' => null, 'slug' => $slug]);
}

it('grants every permission to owners and admins', function (): void {
    $owner = User::factory()->create();
    $organization = Organization::factory()->create(['owner_id' => $owner->id]);
    $admin = User::factory()->create();
    $organization->addUser($admin, SystemRoles::ensure(SystemRoles::ADMIN)->id);

    expect($organization->userHasPermission($owner, 'projects.manage'))->toBeTrue()
        ->and($organization->userHasPermission($admin, 'anything.at.all'))->toBeTrue();
});

it('grants the permissions of the membership role', function (): void {
    [$member, $organization, $role] = userInOrganization('editor');
    $permission = systemPermission('projects.manage');

    expect($organization->userHasPermission($member, 'projects.manage'))->toBeFalse();

    $role->permissions()->attach($permission->id);

    expect($organization->userHasPermission($member, 'projects.manage'))->toBeTrue()
        ->and($organization->userHasPermission($member, 'projects.delete'))->toBeFalse();
});

it('grants the permissions of roles assigned with the ACL API', function (): void {
    [$member, $organization] = userInOrganization('member');
    $role = Role::factory()->create(['organization_id' => $organization->id, 'slug' => 'on-call']);
    $role->permissions()->attach(systemPermission('incidents.manage')->id);

    $member->roles()->attach($role->id);

    expect($organization->userHasPermission($member, 'incidents.manage'))->toBeTrue();
});

it('counts the organization own permissions but not those of other organizations', function (): void {
    [$member, $organization, $role] = userInOrganization('editor');
    $other = Organization::factory()->create();

    $own = Permission::factory()->create(['organization_id' => $organization->id, 'slug' => 'reports.view']);
    $foreign = Permission::factory()->create(['organization_id' => $other->id, 'slug' => 'reports.export']);
    $role->permissions()->attach([$own->id, $foreign->id]);

    expect($organization->userHasPermission($member, 'reports.view'))->toBeTrue()
        ->and($organization->userHasPermission($member, 'reports.export'))->toBeFalse();
});

it('ignores roles of other organizations', function (): void {
    [$member, $organization] = userInOrganization('member');
    $foreignRole = Role::factory()->create(['organization_id' => Organization::factory()->create()->id]);
    $foreignRole->permissions()->attach(systemPermission('projects.manage')->id);

    $member->roles()->attach($foreignRole->id);

    expect($organization->userHasPermission($member, 'projects.manage'))->toBeFalse();
});

it('ignores deleted roles and permissions', function (): void {
    [$member, $organization, $role] = userInOrganization('editor');
    $permission = systemPermission('projects.manage');
    $role->permissions()->attach($permission->id);

    $permission->delete();
    expect($organization->userHasPermission($member, 'projects.manage'))->toBeFalse();

    $permission->restore();
    $role->delete();
    expect($organization->userHasPermission($member, 'projects.manage'))->toBeFalse();
});

it('grants nothing to users outside the organization', function (): void {
    [$member, $organization] = userInOrganization('member');
    $role = Role::factory()->create(['organization_id' => $organization->id]);
    $role->permissions()->attach(systemPermission('projects.manage')->id);

    // A former member keeps the role assigned with the ACL API.
    $member->roles()->attach($role->id);
    $organization->removeUser($member);

    expect($organization->userHasPermission($member, 'projects.manage'))->toBeFalse()
        ->and($organization->userHasPermission(User::factory()->create(), 'projects.manage'))->toBeFalse();
});

it('enforces permissions granted through the API', function (): void {
    [$owner, $organization] = userInOrganization('owner');
    $member = User::factory()->create();
    $organization->addUser($member, SystemRoles::ensure(SystemRoles::MEMBER)->id);
    $permission = systemPermission('projects.manage');

    Route::middleware(['api', 'auth:sanctum', 'tenant.context', 'tenant.required', 'tenant.permission:projects.manage'])
        ->post('api/v1/test-projects', fn () => response()->json(['ok' => true]));

    $asMember = fn () => actingWithToken(null)->actingAs($member, 'sanctum')->withHeader('X-Organization-Id', $organization->id);
    $asOwner = fn () => actingWithToken(null)->actingAs($owner, 'sanctum')->withHeader('X-Organization-Id', $organization->id);

    $asMember()->postJson('/api/v1/test-projects')
        ->assertForbidden()
        ->assertJsonPath('permission', 'projects.manage');

    $roleId = $asOwner()->postJson('/api/v1/roles', ['name' => 'Editor', 'slug' => 'editor'])->assertCreated()->json('data.id');
    $asOwner()->postJson("/api/v1/roles/{$roleId}/permissions", ['permission_id' => $permission->id])->assertSuccessful();
    $asOwner()->putJson("/api/v1/organizations/{$organization->id}/users/{$member->id}/role", ['role_id' => $roleId])->assertOk();

    $asMember()->postJson('/api/v1/test-projects')->assertOk();
});
