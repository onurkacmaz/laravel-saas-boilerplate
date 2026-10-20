<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * Register an endpoint → domain events are delivered and signed → failures are
 * retried and logged → a paused endpoint receives nothing.
 */
it('delivers organization events to a registered endpoint', function (): void {
    $endpointStatus = 200;
    Http::fake(['https://hooks.example.com/*' => function () use (&$endpointStatus) {
        return Http::response(['received' => true], $endpointStatus);
    }]);

    [$admin, $organization] = userInOrganization('admin');
    $role = Role::factory()->create(['organization_id' => $organization->id, 'slug' => 'member']);
    $tenant = fn () => $this->actingAs($admin, 'sanctum')->withHeader('X-Organization-Id', $organization->id);

    $webhook = $tenant()->postJson('/api/v1/webhooks', [
        'name' => 'CRM sync',
        'url' => 'https://hooks.example.com/saas',
        'events' => ['member.*'],
        'retry_limit' => 3,
    ])->assertCreated()->json('data');

    // A new member is added: the endpoint receives a signed member.added event.
    $newMember = User::factory()->create();
    $tenant()->postJson("/api/v1/organizations/{$organization->id}/users", ['user_id' => $newMember->id, 'role_id' => $role->id])
        ->assertCreated();

    Http::assertSent(function (Request $request) use ($webhook, $newMember) {
        $signature = hash_hmac('sha256', $request->header('X-Webhook-Timestamp')[0].'.'.$request->body(), $webhook['secret']);

        return $request->url() === 'https://hooks.example.com/saas'
            && $request['event'] === 'member.added'
            && $request['data']['user_id'] === $newMember->id
            && hash_equals($signature, $request->header('X-Webhook-Signature')[0]);
    });

    // Events the endpoint did not subscribe to are not sent.
    $tenant()->putJson("/api/v1/organizations/{$organization->id}", ['description' => 'Updated'])->assertOk();
    Http::assertSentCount(1);

    // The endpoint goes down: the delivery is retried up to the limit.
    $endpointStatus = 503;
    $tenant()->deleteJson("/api/v1/organizations/{$organization->id}/users/{$newMember->id}")->assertNoContent();

    $deliveries = $tenant()->getJson("/api/v1/webhooks/{$webhook['id']}/deliveries?per_page=50")->assertOk()->json('data');

    expect(collect($deliveries)->where('event_name', 'member.removed')->pluck('attempt')->sort()->values()->all())->toBe([1, 2, 3])
        ->and(collect($deliveries)->where('event_name', 'member.removed')->pluck('status')->unique()->all())->toBe(['failed'])
        ->and(collect($deliveries)->firstWhere('event_name', 'member.added')['status'])->toBe('success');

    // Paused endpoints receive nothing.
    $tenant()->putJson("/api/v1/webhooks/{$webhook['id']}", ['is_active' => false])->assertOk();
    $sentBefore = count(Http::recorded());

    $tenant()->postJson("/api/v1/organizations/{$organization->id}/users", ['user_id' => $newMember->id, 'role_id' => $role->id])
        ->assertCreated();

    expect(count(Http::recorded()))->toBe($sentBefore);
});
