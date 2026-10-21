<?php

use Features\Subscription\Infrastructure\Eloquent\Models\Plan;
use Features\Subscription\Infrastructure\Eloquent\Models\Subscription;
use Features\Subscription\Infrastructure\Middleware\RequirePlanFeature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Infrastructure\Tenant\TenantContext;

uses(RefreshDatabase::class);

afterEach(fn () => TenantContext::clear());

beforeEach(function (): void {
    Route::middleware(['api', 'auth:sanctum', 'tenant.context', 'tenant.required', 'plan.feature:webhooks,audit-logs'])
        ->get('api/v1/test-plan-feature', fn () => response()->json(['ok' => true]));
});

it('requires every listed feature of the plan', function (): void {
    [$admin, $organization] = userInOrganization('admin');
    $plan = Plan::factory()->create(['features' => ['webhooks']]);
    Subscription::factory()->create(['organization_id' => $organization->id, 'plan_id' => $plan->id]);
    $request = fn () => $this->actingAs($admin, 'sanctum')->withHeader('X-Organization-Id', $organization->id)->getJson('/api/v1/test-plan-feature');

    $request()->assertStatus(402)->assertJson([
        'message' => 'Your plan does not include the audit-logs feature.',
        'feature' => 'audit-logs',
        'upgrade_required' => true,
    ]);

    $plan->forceFill(['features' => ['webhooks', 'audit-logs']])->save();

    $request()->assertOk();
});

it('rejects organizations without a subscription', function (): void {
    [$admin, $organization] = userInOrganization('admin');

    $this->actingAs($admin, 'sanctum')->withHeader('X-Organization-Id', $organization->id)
        ->getJson('/api/v1/test-plan-feature')
        ->assertStatus(402)
        ->assertJsonPath('feature', 'webhooks');
});

it('requires a tenant', function (): void {
    $response = (new RequirePlanFeature)->handle(Request::create('/'), fn () => response('ok'), 'webhooks');

    expect($response->getStatusCode())->toBe(403);
});
