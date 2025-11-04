<?php

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Features\Subscription\Cancel\Application\CancelHandler;
use Features\Subscription\Infrastructure\Eloquent\Models\Plan;
use Features\Subscription\Infrastructure\Eloquent\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->organization = Organization::factory()->create();
    $this->adminRole = Role::factory()->create([
        'organization_id' => $this->organization->id,
        'slug' => 'admin',
    ]);

    $this->user->organizations()->attach($this->organization->id, [
        'role_id' => $this->adminRole->id,
    ]);
});

it('cancels the current subscription', function (): void {
    $plan = Plan::factory()->create();

    $subscription = Subscription::factory()->make([
        'id' => (string) Str::uuid(),
        'organization_id' => $this->organization->id,
        'plan_id' => $plan->id,
        'stripe_status' => 'canceled',
    ]);
    $subscription->setRelation('plan', $plan);

    $this->mock(CancelHandler::class)
        ->shouldReceive('__invoke')
        ->once()
        ->andReturn($subscription);

    $response = $this->actingAs($this->user, 'sanctum')
        ->withHeader('X-Organization-Id', $this->organization->id)
        ->deleteJson('/api/v1/subscriptions');

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('message', 'Subscription cancelled successfully')
        ->assertJsonPath('data.status', 'canceled');
});

it('returns not found when canceling without an active subscription', function (): void {
    $this->mock(CancelHandler::class)
        ->shouldReceive('__invoke')
        ->once()
        ->andThrow(new NotFoundHttpException('No active subscription'));

    $response = $this->actingAs($this->user, 'sanctum')
        ->withHeader('X-Organization-Id', $this->organization->id)
        ->deleteJson('/api/v1/subscriptions');

    $response->assertNotFound()
        ->assertJsonPath('message', 'No active subscription');
});
