<?php

use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Infrastructure\Tenant\Middleware\RequireOrganizationPermission;
use Infrastructure\Tenant\TenantContext;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(fn () => TenantContext::clear());

afterEach(fn () => TenantContext::clear());

function permissionRequest(?User $user): Request
{
    $request = Request::create('/api/resource', 'POST');
    $request->setUserResolver(fn () => $user);

    return $request;
}

it('rejects requests without tenant context or user', function (): void {
    $middleware = new RequireOrganizationPermission;

    $response = $middleware->handle(permissionRequest(User::factory()->create()), fn () => response('ok'), 'projects.manage');

    expect($response->getStatusCode())->toBe(403)
        ->and($response->getContent())->toContain('Organization context is required');

    [, $organization] = userInOrganization('member');
    TenantContext::setOrganization($organization);

    expect($middleware->handle(permissionRequest(null), fn () => response('ok'), 'projects.manage')->getStatusCode())->toBe(403);
});

it('requires every listed permission', function (): void {
    [$member, $organization, $role] = userInOrganization('editor');
    $role->permissions()->attach(Permission::factory()->create(['organization_id' => null, 'slug' => 'projects.view'])->id);
    TenantContext::setOrganization($organization);

    $middleware = new RequireOrganizationPermission;

    expect($middleware->handle(permissionRequest($member), fn () => response('ok'), 'projects.view')->getContent())->toBe('ok');

    $response = $middleware->handle(permissionRequest($member), fn () => response('ok'), 'projects.view', 'projects.manage');

    expect($response->getStatusCode())->toBe(403)
        ->and(json_decode($response->getContent(), true))->toBe([
            'message' => 'You do not have permission to perform this action.',
            'permission' => 'projects.manage',
        ]);
});
