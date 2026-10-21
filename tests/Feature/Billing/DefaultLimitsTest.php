<?php

use App\Models\Organization;
use Features\Subscription\Infrastructure\Eloquent\Models\Plan;
use Features\Subscription\Infrastructure\Eloquent\Models\Subscription;
use Features\Subscription\Shared\Support\ResourceLimits;
use Features\Subscription\Shared\Support\UsageTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

afterEach(function (): void {
    ResourceLimits::forgetDefault('reports');
    ResourceLimits::forgetDefault('users');
    UsageTracker::forgetCounter('reports');
});

it('falls back to registered defaults when the plan omits a limit', function (): void {
    $organization = Organization::factory()->create();
    $plan = Plan::factory()->create(['limits' => ['users' => 10]]);
    Subscription::factory()->create(['organization_id' => $organization->id, 'plan_id' => $plan->id]);
    $limits = new ResourceLimits($organization);

    expect($limits->getLimit('reports'))->toBeNull()
        ->and($limits->hasReached('reports', 1000))->toBeFalse();

    ResourceLimits::registerDefault('reports', 2);

    expect($limits->getLimit('reports'))->toBe(2)
        ->and($limits->hasReached('reports', 2))->toBeTrue()
        ->and($limits->getAllLimits())->toMatchArray(['reports' => 2, 'users' => 10]);
});

it('prefers the plan limit over a registered default', function (): void {
    $organization = Organization::factory()->create();
    $plan = Plan::factory()->create(['limits' => ['reports' => 50]]);
    Subscription::factory()->create(['organization_id' => $organization->id, 'plan_id' => $plan->id]);

    ResourceLimits::registerDefault('reports', 2);

    expect((new ResourceLimits($organization))->getLimit('reports'))->toBe(50)
        ->and((new ResourceLimits($organization))->getAllLimits())->toMatchArray(['reports' => 50]);
});

it('applies registered defaults to organizations without a subscription', function (): void {
    $organization = Organization::factory()->create();

    ResourceLimits::registerDefault('reports', 3);
    ResourceLimits::registerDefault('users', 7);

    $limits = new ResourceLimits($organization);

    expect($limits->getLimit('reports'))->toBe(3)
        ->and($limits->getLimit('users'))->toBe(7)
        ->and($limits->getAllLimits())->toMatchArray(['reports' => 3, 'users' => 7, 'projects' => 1]);
});

it('enforces limit:<resource> with a registered default', function (): void {
    [$admin, $organization] = userInOrganization('admin');
    $plan = Plan::factory()->create(['limits' => ['users' => 10]]);
    Subscription::factory()->create(['organization_id' => $organization->id, 'plan_id' => $plan->id]);
    ResourceLimits::registerDefault('reports', 1);
    UsageTracker::registerCounter('reports', fn () => 1);

    Route::middleware(['api', 'auth:sanctum', 'tenant.context', 'tenant.required', 'limit:reports'])
        ->post('api/v1/test-reports', fn () => response()->json(['ok' => true], 201));

    $this->actingAs($admin, 'sanctum')->withHeader('X-Organization-Id', $organization->id)
        ->postJson('/api/v1/test-reports')
        ->assertStatus(429)
        ->assertJsonPath('resource', 'reports')
        ->assertJsonPath('limit', 1);

    $this->actingAs($admin, 'sanctum')->withHeader('X-Organization-Id', $organization->id)
        ->getJson('/api/v1/usage')
        ->assertOk()
        ->assertJsonPath('data.reports.current', 1)
        ->assertJsonPath('data.reports.can_add', false);
});
