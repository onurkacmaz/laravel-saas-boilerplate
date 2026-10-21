<?php

use App\Models\Organization;
use Features\Subscription\Infrastructure\Eloquent\Models\Plan;
use Features\Subscription\Infrastructure\Eloquent\Models\Subscription;
use Features\Subscription\Shared\Support\UsageTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

afterEach(function (): void {
    UsageTracker::forgetCounter('projects');
    UsageTracker::forgetCounter('users');
});

it('counts resources with registered counters', function (): void {
    $organization = Organization::factory()->create();
    $other = Organization::factory()->create();
    $projects = [$organization->id => 4, $other->id => 1];

    expect(UsageTracker::hasCounter('projects'))->toBeFalse()
        ->and((new UsageTracker($organization))->getCurrentCount('projects'))->toBe(0);

    UsageTracker::registerCounter('projects', fn (Organization $organization) => $projects[$organization->id]);

    expect(UsageTracker::hasCounter('projects'))->toBeTrue()
        ->and((new UsageTracker($organization))->getCurrentCount('projects'))->toBe(4)
        ->and((new UsageTracker($other))->getCurrentCount('projects'))->toBe(1)
        ->and((new UsageTracker(null))->getCurrentCount('projects'))->toBe(0);
});

it('lets a registered counter replace a built-in one', function (): void {
    [, $organization] = userInOrganization('member');

    UsageTracker::registerCounter('users', fn () => 42);

    expect((new UsageTracker($organization))->getCurrentCount('users'))->toBe(42);
});

it('reports registered resources in the usage summary', function (): void {
    [$admin, $organization] = userInOrganization('admin');
    $plan = Plan::factory()->create(['limits' => ['users' => 10, 'projects' => 5]]);
    Subscription::factory()->create(['organization_id' => $organization->id, 'plan_id' => $plan->id]);
    UsageTracker::registerCounter('projects', fn () => 3);

    $this->actingAs($admin, 'sanctum')->withHeader('X-Organization-Id', $organization->id)
        ->getJson('/api/v1/usage')
        ->assertOk()
        ->assertJsonPath('data.projects.current', 3)
        ->assertJsonPath('data.projects.remaining', 2);
});

it('enforces limit:<resource> with registered counters', function (): void {
    [$admin, $organization] = userInOrganization('admin');
    $plan = Plan::factory()->create(['limits' => ['projects' => 2]]);
    Subscription::factory()->create(['organization_id' => $organization->id, 'plan_id' => $plan->id]);
    $count = 1;
    UsageTracker::registerCounter('projects', function () use (&$count) {
        return $count;
    });

    Route::middleware(['api', 'auth:sanctum', 'tenant.context', 'tenant.required', 'limit:projects'])
        ->post('api/v1/test-projects', fn () => response()->json(['ok' => true], 201));

    $create = fn () => $this->actingAs($admin, 'sanctum')->withHeader('X-Organization-Id', $organization->id)->postJson('/api/v1/test-projects');

    $create()->assertCreated();

    $count = 2;

    $create()->assertStatus(429)
        ->assertJsonPath('resource', 'projects')
        ->assertJsonPath('current', 2)
        ->assertJsonPath('limit', 2);
});
