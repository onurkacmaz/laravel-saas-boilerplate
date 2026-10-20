<?php

use App\Models\Organization;
use Features\Subscription\Infrastructure\Eloquent\Models\Plan;
use Features\Subscription\Infrastructure\Eloquent\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Route::middleware(['api', 'auth:sanctum', 'tenant.context', 'subscription.required'])
        ->get('/api/_test/premium', fn () => response()->json(['ok' => true]));

    Route::middleware(['api', 'auth:sanctum', 'tenant.context', 'limit:users'])
        ->post('/api/_test/members', fn () => response()->json(['ok' => true]));

    Route::middleware(['api', 'auth:sanctum', 'tenant.context', 'rate.limit'])
        ->get('/api/_test/limited', fn () => response()->json(['ok' => true]));

    [$this->user, $this->organization] = userInOrganization('admin');
});

function asTenant()
{
    return test()->actingAs(test()->user, 'sanctum')->withHeader('X-Organization-Id', test()->organization->id);
}

it('requires an active subscription', function (): void {
    asTenant()->getJson('/api/_test/premium')
        ->assertStatus(402)
        ->assertJsonPath('requires_subscription', true);

    Subscription::factory()->create(['organization_id' => $this->organization->id]);

    asTenant()->getJson('/api/_test/premium')->assertOk();
});

it('rejects subscriptions that are no longer valid', function (): void {
    Subscription::factory()->create([
        'organization_id' => $this->organization->id,
        'stripe_status' => 'active',
        'ends_at' => now()->subDay(),
    ]);

    asTenant()->getJson('/api/_test/premium')
        ->assertStatus(402)
        ->assertJsonPath('subscription_status', 'active');
});

it('needs an organization context for billing middleware', function (): void {
    $this->actingAs($this->user, 'sanctum')->getJson('/api/_test/premium')->assertForbidden();
    $this->actingAs($this->user, 'sanctum')->postJson('/api/_test/members')->assertForbidden();
});

it('enforces plan resource limits', function (): void {
    $plan = Plan::factory()->create(['limits' => ['users' => 2]]);
    Subscription::factory()->create(['organization_id' => $this->organization->id, 'plan_id' => $plan->id]);

    asTenant()->postJson('/api/_test/members')->assertOk();

    userInOrganization('member', $this->organization);

    asTenant()->postJson('/api/_test/members')
        ->assertStatus(429)
        ->assertJson(['resource' => 'users', 'current' => 2, 'limit' => 2, 'upgrade_required' => true]);
});

it('throttles requests above the plan rate limit', function (): void {
    $plan = Plan::factory()->create(['limits' => ['requests_per_minute' => 2]]);
    Subscription::factory()->create(['organization_id' => $this->organization->id, 'plan_id' => $plan->id]);

    asTenant()->getJson('/api/_test/limited')->assertOk()->assertHeader('X-RateLimit-Remaining', '1');
    asTenant()->getJson('/api/_test/limited')->assertOk()->assertHeader('X-RateLimit-Remaining', '0');

    asTenant()->getJson('/api/_test/limited')
        ->assertStatus(429)
        ->assertHeader('Retry-After')
        ->assertJsonPath('limit', 2);
});

it('uses separate rate limit buckets per organization', function (): void {
    $plan = Plan::factory()->create(['limits' => ['requests_per_minute' => 1]]);
    Subscription::factory()->create(['organization_id' => $this->organization->id, 'plan_id' => $plan->id]);

    $other = Organization::factory()->create();
    $other->users()->attach($this->user->id, ['role_id' => $this->organization->roleOf($this->user)->id]);
    Subscription::factory()->create(['organization_id' => $other->id, 'plan_id' => $plan->id]);

    asTenant()->getJson('/api/_test/limited')->assertOk();
    asTenant()->getJson('/api/_test/limited')->assertStatus(429);

    $this->actingAs($this->user, 'sanctum')
        ->withHeader('X-Organization-Id', $other->id)
        ->getJson('/api/_test/limited')
        ->assertOk();
});
