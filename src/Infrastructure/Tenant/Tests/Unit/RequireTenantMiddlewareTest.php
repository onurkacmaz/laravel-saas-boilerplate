<?php

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Infrastructure\Tenant\Middleware\RequireTenant;
use Infrastructure\Tenant\TenantContext;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    TenantContext::clear();
});

afterEach(function (): void {
    TenantContext::clear();
});

it('rejects requests without tenant context', function (): void {
    $middleware = new RequireTenant;
    $request = Request::create('/api/resource', 'GET');

    $response = $middleware->handle($request, fn () => response('ok'));

    expect($response->getStatusCode())->toBe(403);
    expect($response->getContent())->toContain('Organization context is required');
});

it('rejects users that do not belong to the organization', function (): void {
    $middleware = new RequireTenant;
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    TenantContext::setOrganization($organization);
    TenantContext::setUser($user);

    expect(TenantContext::userBelongsToOrganization())->toBeFalse();

    $request = Request::create('/api/resource', 'GET');
    $request->setUserResolver(fn () => $user);

    $response = $middleware->handle($request, fn () => response('ok'));

    expect($response->getStatusCode())->toBe(403);
    expect($response->getContent())->toContain('You do not have access');
});

it('allows requests when context is valid', function (): void {
    $middleware = new RequireTenant;

    $user = User::factory()->create();
    $organization = Organization::factory()->ownedBy($user)->create();
    $role = Role::factory()->create(['organization_id' => $organization->id, 'slug' => 'admin']);
    $organization->users()->attach($user->id, ['role_id' => $role->id]);

    TenantContext::setOrganization($organization->fresh());
    TenantContext::setUser($user->fresh());

    expect(TenantContext::userBelongsToOrganization())->toBeTrue();

    $request = Request::create('/api/resource', 'GET');
    $request->setUserResolver(fn () => $user->fresh());

    $response = $middleware->handle($request, fn () => response('ok'));

    expect($response->getStatusCode())->toBe(200);
    expect($response->getContent())->toBe('ok');
});
