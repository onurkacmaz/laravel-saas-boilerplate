<?php

use App\Models\User;
use Features\Webhooks\DeliverWebhook\Application\DeliverWebhook;
use Features\Webhooks\DeliverWebhook\Application\DeliverWebhookHandler;
use Features\Webhooks\Infrastructure\Eloquent\Models\Webhook;
use Features\Webhooks\Shared\Jobs\DispatchWebhookJob;
use Features\Webhooks\Shared\Support\WebhookUrlGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('refuses private and reserved webhook targets', function (string $url): void {
    expect(WebhookUrlGuard::isAllowed($url))->toBeFalse();
})->with([
    'http://127.0.0.1/hook',
    'http://localhost:8080/hook',
    'http://169.254.169.254/latest/meta-data',
    'http://10.0.0.5/hook',
    'http://192.168.1.10/hook',
    'http://[::1]/hook',
    'http://[::ffff:10.0.0.1]/hook',
    'http://100.64.0.1/hook',
    'ftp://example.com/hook',
    'https://user:pass@example.com/hook',
    'file:///etc/passwd',
]);

it('accepts public webhook targets', function (): void {
    expect(WebhookUrlGuard::isAllowed('https://8.8.8.8/hook'))->toBeTrue();
});

it('rejects private webhook URLs on creation', function (): void {
    [$admin, $organization] = userInOrganization('admin');

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Organization-Id', $organization->id)
        ->postJson('/api/v1/webhooks', [
            'name' => 'Metadata',
            'url' => 'http://169.254.169.254/latest/meta-data',
            'events' => ['member.added'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['url']);
});

it('honors is_active when creating a webhook', function (): void {
    [$admin, $organization] = userInOrganization('admin');

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Organization-Id', $organization->id)
        ->postJson('/api/v1/webhooks', [
            'name' => 'Disabled',
            'url' => 'https://example.com/hook',
            'events' => ['member.added'],
            'is_active' => false,
        ])
        ->assertCreated()
        ->assertJsonPath('data.is_active', false);
});

it('signs deliveries so receivers can verify them', function (): void {
    Http::fake(['https://example.com/*' => Http::response('ok')]);

    $webhook = Webhook::factory()->create([
        'url' => 'https://example.com/hook',
        'secret' => 'super-secret-signing-key',
        'events' => ['member.added'],
    ]);

    (new DeliverWebhookHandler)(new DeliverWebhook($webhook, 'member.added', ['hello' => 'world']));

    Http::assertSent(function ($request) {
        $expected = hash_hmac('sha256', $request->header('X-Webhook-Timestamp')[0].'.'.$request->body(), 'super-secret-signing-key');

        return hash_equals($expected, $request->header('X-Webhook-Signature')[0])
            && $request->header('X-Webhook-Event')[0] === 'member.added';
    });
});

it('queues webhooks for domain events of the organization', function (): void {
    Queue::fake();

    [$admin, $organization] = userInOrganization('admin');
    $subscribed = Webhook::factory()->forOrganization($organization)->withEvents(['member.*'])->create();
    Webhook::factory()->forOrganization($organization)->withEvents(['invoice.paid'])->create();
    Webhook::factory()->withEvents(['*'])->create();

    $newMember = User::factory()->create();
    [, , $role] = userInOrganization('member', $organization);

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Organization-Id', $organization->id)
        ->postJson("/api/v1/organizations/{$organization->id}/users", ['user_id' => $newMember->id, 'role_id' => $role->id])
        ->assertCreated();

    Queue::assertPushed(DispatchWebhookJob::class, 1);
    Queue::assertPushed(DispatchWebhookJob::class, fn (DispatchWebhookJob $job) => $job->webhook->is($subscribed)
        && $job->eventName === 'member.added'
        && $job->payload['data']['user_id'] === $newMember->id);
});

it('schedules a retry after a failed delivery', function (): void {
    Queue::fake();
    Http::fake(['https://example.com/*' => Http::response('down', 503)]);

    $webhook = Webhook::factory()->create([
        'url' => 'https://example.com/hook',
        'events' => ['member.added'],
        'retry_limit' => 3,
    ]);

    (new DispatchWebhookJob($webhook, 'member.added', ['a' => 1]))->handle();

    Queue::assertPushed(DispatchWebhookJob::class, fn (DispatchWebhookJob $job) => $job->attempt === 2);
    expect($webhook->deliveries()->first()->status)->toBe('failed');
});
