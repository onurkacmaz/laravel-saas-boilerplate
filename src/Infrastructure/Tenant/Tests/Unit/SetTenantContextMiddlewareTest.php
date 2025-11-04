<?php

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Infrastructure\Tenant\Middleware\SetTenantContext;
use Infrastructure\Tenant\TenantContext;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    TenantContext::clear();
});

afterEach(function (): void {
    TenantContext::clear();
});

it('sets tenant context for authorized users and clears it afterwards', function (): void {
    $user = User::factory()->create();
    $organization = Organization::factory()->ownedBy($user)->create();
    $role = Role::factory()->create(['organization_id' => $organization->id, 'slug' => 'admin']);
    $organization->users()->attach($user->id, ['role_id' => $role->id]);

    $request = Request::create('/api/resource', 'GET');
    $request->setUserResolver(fn () => $user);
    $request->headers->set('X-Organization-Id', $organization->id);

    $middleware = new SetTenantContext;

    $captured = [];
    $response = $middleware->handle($request, function () use (&$captured) {
        $captured['organization'] = TenantContext::getOrganization()?->id;
        $captured['user'] = TenantContext::getUser()?->id;

        return response('ok');
    });

    expect($response->getContent())->toBe('ok');
    expect($captured['organization'])->toBe($organization->id);
    expect($captured['user'])->toBe($user->id);
    expect(TenantContext::getOrganization())->toBeNull();
    expect(TenantContext::getUser())->toBeNull();
});

it('resolves user from sanctum guard when default guard returns null', function (): void {
    $user = User::factory()->create();
    $organization = Organization::factory()->ownedBy($user)->create();
    $role = Role::factory()->create(['organization_id' => $organization->id, 'slug' => 'admin']);
    $organization->users()->attach($user->id, ['role_id' => $role->id]);

    $request = Request::create('/api/resource', 'GET');
    $request->setUserResolver(fn () => null);
    $request->headers->set('X-Organization-Id', $organization->id);

    Auth::shouldUse('web');
    Auth::guard('sanctum')->setUser($user);

    $middleware = new SetTenantContext;

    $captured = [];
    $middleware->handle($request, function () use (&$captured) {
        $captured['organization'] = TenantContext::getOrganization()?->id;
        $captured['user'] = TenantContext::getUser()?->id;

        return response('ok');
    });

    expect($captured['organization'])->toBe($organization->id);
    expect($captured['user'])->toBe($user->id);
});

it('does not set organization for users without access', function (): void {
    $user = User::factory()->create();
    $organization = Organization::factory()->create(); // different owner

    $request = Request::create('/api/resource', 'GET');
    $request->setUserResolver(fn () => $user);
    $request->headers->set('X-Organization-Id', $organization->id);

    $middleware = new SetTenantContext;

    $captured = [];
    $middleware->handle($request, function () use (&$captured) {
        $captured['organization'] = TenantContext::getOrganization();
        $captured['user'] = TenantContext::getUser()?->id;

        return response('ok');
    });

    expect($captured['organization'])->toBeNull();
    expect($captured['user'])->toBe($user->id);
});

it('allows guests to set tenant context when header is present', function (): void {
    $organization = Organization::factory()->create();

    $request = Request::create('/api/resource', 'GET');
    $request->headers->set('X-Organization-Id', $organization->id);

    $middleware = new SetTenantContext;

    $captured = [];
    $middleware->handle($request, function () use (&$captured) {
        $captured['organization'] = TenantContext::getOrganization()?->id;
        $captured['user'] = TenantContext::getUser();

        return response('ok');
    });

    expect($captured['organization'])->toBe($organization->id);
    expect($captured['user'])->toBeNull();
});

it('populates tenant context during sanctum authenticated request', function (): void {
    $user = User::factory()->create();
    $organization = Organization::factory()->ownedBy($user)->create();
    $role = Role::factory()->create(['organization_id' => $organization->id, 'slug' => 'admin']);
    $organization->users()->attach($user->id, ['role_id' => $role->id]);

    Route::middleware(['auth:sanctum', 'tenant.context'])
        ->get('/__tenant-test', fn () => response()->json([
            'organization' => TenantContext::getOrganization()?->id,
            'user' => TenantContext::getUser()?->id,
        ]));

    Sanctum::actingAs($user);

    $response = $this->withHeaders([
        'X-Organization-Id' => $organization->id,
    ])->getJson('/__tenant-test');

    $response->assertOk()
        ->assertJson([
            'organization' => $organization->id,
            'user' => $user->id,
        ]);
});

it('populates tenant context for bearer token requests', function (): void {
    $user = User::factory()->create();
    $organization = Organization::factory()->ownedBy($user)->create();
    $role = Role::factory()->create(['organization_id' => $organization->id, 'slug' => 'admin']);
    $organization->users()->attach($user->id, ['role_id' => $role->id]);

    $token = $user->createToken('test-token');

    Route::middleware(['auth:sanctum', 'tenant.context'])
        ->get('/__tenant-test-token', fn () => response()->json([
            'organization' => TenantContext::getOrganization()?->id,
            'user' => TenantContext::getUser()?->id,
        ]));

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token->plainTextToken,
        'X-Organization-Id' => $organization->id,
    ])->getJson('/__tenant-test-token');

    $response->assertOk()
        ->assertJson([
            'organization' => $organization->id,
            'user' => $user->id,
        ]);
});
